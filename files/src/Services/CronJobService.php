<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

class CronJobService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * List all cron jobs
     */
    public function listJobs(?string $serverName = null): array
    {
        if ($serverName) {
            $stmt = $this->db->prepare("
                SELECT * FROM cron_jobs 
                WHERE server_name = ? OR server_name IS NULL
                ORDER BY enabled DESC, name ASC
            ");
            $stmt->execute([$serverName]);
        } else {
            $stmt = $this->db->query("
                SELECT * FROM cron_jobs 
                ORDER BY enabled DESC, name ASC
            ");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single cron job
     */
    public function getJob(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM cron_jobs WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new cron job
     */
    public function createJob(
        string $name,
        string $command,
        string $schedule,
        string $user = 'www-data',
        ?string $serverName = null,
        bool $enabled = true
    ): array {
        // Validate cron expression
        if (!$this->validateCronExpression($schedule)) {
            throw new RuntimeException("Invalid cron expression: {$schedule}");
        }

        // Insert into database
        $stmt = $this->db->prepare("
            INSERT INTO cron_jobs (name, command, schedule, user, server_name, enabled, next_run)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $nextRun = $this->calculateNextRun($schedule);
        
        $stmt->execute([
            $name,
            $command,
            $schedule,
            $user,
            $serverName,
            $enabled,
            $nextRun
        ]);

        $jobId = (int) $this->db->lastInsertId();

        // Add to system crontab
        if ($enabled) {
            $this->syncToSystemCrontab();
        }

        return $this->getJob($jobId);
    }

    /**
     * Update a cron job
     */
    public function updateJob(int $id, array $data): array
    {
        $job = $this->getJob($id);
        if (!$job) {
            throw new RuntimeException("Cron job not found");
        }

        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }

        if (isset($data['command'])) {
            $updates[] = "command = ?";
            $params[] = $data['command'];
        }

        if (isset($data['schedule'])) {
            if (!$this->validateCronExpression($data['schedule'])) {
                throw new RuntimeException("Invalid cron expression: {$data['schedule']}");
            }
            $updates[] = "schedule = ?";
            $params[] = $data['schedule'];
            
            $updates[] = "next_run = ?";
            $params[] = $this->calculateNextRun($data['schedule']);
        }

        if (isset($data['user'])) {
            $updates[] = "user = ?";
            $params[] = $data['user'];
        }

        if (isset($data['server_name'])) {
            $updates[] = "server_name = ?";
            $params[] = $data['server_name'];
        }

        if (isset($data['enabled'])) {
            $updates[] = "enabled = ?";
            $params[] = $data['enabled'];
        }

        if (empty($updates)) {
            return $job;
        }

        $params[] = $id;
        $sql = "UPDATE cron_jobs SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->prepare($sql)->execute($params);

        // Sync to system crontab
        $this->syncToSystemCrontab();

        return $this->getJob($id);
    }

    /**
     * Delete a cron job
     */
    public function deleteJob(int $id): void
    {
        $job = $this->getJob($id);
        if (!$job) {
            throw new RuntimeException("Cron job not found");
        }

        $stmt = $this->db->prepare("DELETE FROM cron_jobs WHERE id = ?");
        $stmt->execute([$id]);

        // Sync to system crontab
        $this->syncToSystemCrontab();
    }

    /**
     * Enable/disable a cron job
     */
    public function toggleJob(int $id, bool $enabled): array
    {
        $stmt = $this->db->prepare("UPDATE cron_jobs SET enabled = ? WHERE id = ?");
        $stmt->execute([$enabled, $id]);

        // Sync to system crontab
        $this->syncToSystemCrontab();

        return $this->getJob($id);
    }

    /**
     * Execute a cron job manually
     */
    public function executeJob(int $id): array
    {
        $job = $this->getJob($id);
        if (!$job) {
            throw new RuntimeException("Cron job not found");
        }

        // Execute command
        $command = $job['command'];
        $user = $job['user'];
        
        // If user is specified and not current user, use sudo
        if ($user !== 'www-data') {
            $command = "sudo -u " . escapeshellarg($user) . " " . $command;
        }

        $command .= " 2>&1";
        $output = shell_exec($command);
        $exitCode = 0; // shell_exec doesn't provide exit code

        // Update last run
        $stmt = $this->db->prepare("
            UPDATE cron_jobs 
            SET last_run = NOW(), last_output = ?, last_exit_code = ?, next_run = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $output,
            $exitCode,
            $this->calculateNextRun($job['schedule']),
            $id
        ]);

        return [
            'output' => $output,
            'exit_code' => $exitCode
        ];
    }

    /**
     * Sync all enabled jobs to system crontab
     */
    public function syncToSystemCrontab(): void
    {
        $stmt = $this->db->query("SELECT * FROM cron_jobs WHERE enabled = 1 ORDER BY id");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $crontabLines = [];
        $crontabLines[] = "# Managed by Admin Panel - Do not edit manually";
        $crontabLines[] = "# Last updated: " . date('Y-m-d H:i:s');
        $crontabLines[] = "";

        foreach ($jobs as $job) {
            $comment = "# Job ID: {$job['id']} - {$job['name']}";
            if ($job['server_name']) {
                $comment .= " (Site: {$job['server_name']})";
            }
            $crontabLines[] = $comment;
            $crontabLines[] = "{$job['schedule']} {$job['command']}";
            $crontabLines[] = "";
        }

        // Write to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'crontab');
        file_put_contents($tempFile, implode("\n", $crontabLines));

        // Install crontab for www-data user
        $command = "crontab -u www-data " . escapeshellarg($tempFile);
        exec($command, $output, $returnCode);

        unlink($tempFile);

        if ($returnCode !== 0) {
            throw new RuntimeException("Failed to sync crontab: " . implode("\n", $output));
        }
    }

    /**
     * Validate cron expression (basic validation)
     */
    private function validateCronExpression(string $expression): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        
        // Standard cron: minute hour day month weekday
        // Extended cron with seconds: second minute hour day month weekday
        if (count($parts) !== 5 && count($parts) !== 6) {
            return false;
        }

        return true;
    }

    /**
     * Calculate next run time from cron expression (simplified)
     */
    private function calculateNextRun(string $schedule): string
    {
        // This is a simplified calculation
        // For production, consider using a library like cron-expression
        $parts = preg_split('/\s+/', trim($schedule));
        
        // For now, just set it to run within the next hour
        // In production, you'd parse the cron expression properly
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    }

    /**
     * Get common cron schedule presets
     */
    public function getSchedulePresets(): array
    {
        return [
            'every_minute' => [
                'label' => 'Every Minute',
                'expression' => '* * * * *'
            ],
            'every_5_minutes' => [
                'label' => 'Every 5 Minutes',
                'expression' => '*/5 * * * *'
            ],
            'every_15_minutes' => [
                'label' => 'Every 15 Minutes',
                'expression' => '*/15 * * * *'
            ],
            'every_30_minutes' => [
                'label' => 'Every 30 Minutes',
                'expression' => '*/30 * * * *'
            ],
            'hourly' => [
                'label' => 'Every Hour',
                'expression' => '0 * * * *'
            ],
            'daily' => [
                'label' => 'Daily at Midnight',
                'expression' => '0 0 * * *'
            ],
            'daily_noon' => [
                'label' => 'Daily at Noon',
                'expression' => '0 12 * * *'
            ],
            'weekly' => [
                'label' => 'Weekly (Sunday Midnight)',
                'expression' => '0 0 * * 0'
            ],
            'monthly' => [
                'label' => 'Monthly (1st Midnight)',
                'expression' => '0 0 1 * *'
            ],
        ];
    }
}
