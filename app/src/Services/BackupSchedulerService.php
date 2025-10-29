<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

class BackupSchedulerService
{
    private PDO $db;
    private BackupService $backupService;

    public function __construct(array $mysqlConfig, BackupService $backupService)
    {
        $this->db = Connection::get($mysqlConfig);
        $this->backupService = $backupService;
    }

    /**
     * List all backup jobs
     */
    public function listJobs(): array
    {
        $stmt = $this->db->query('
            SELECT j.*, d.name as destination_name, d.type as destination_type
            FROM backup_jobs j
            LEFT JOIN backup_destinations d ON j.destination_id = d.id
            ORDER BY j.enabled DESC, j.name ASC
        ');
        
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jobs as &$job) {
            $job['items'] = json_decode($job['items'], true);
            $job['enabled'] = (bool) $job['enabled'];
        }
        
        return $jobs;
    }

    /**
     * Get a single job
     */
    public function getJob(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT j.*, d.name as destination_name, d.type as destination_type
            FROM backup_jobs j
            LEFT JOIN backup_destinations d ON j.destination_id = d.id
            WHERE j.id = ?
        ');
        $stmt->execute([$id]);
        
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($job) {
            $job['items'] = json_decode($job['items'], true);
            $job['enabled'] = (bool) $job['enabled'];
        }
        
        return $job ?: null;
    }

    /**
     * Create a new backup job
     */
    public function createJob(string $name, string $backupType, array $items, string $scheduleCron, int $destinationId, int $retentionDays = 30, ?string $description = null): array
    {
        // Validate cron expression
        $this->validateCronExpression($scheduleCron);

        // Validate destination exists
        $this->validateDestination($destinationId);

        // Calculate next run time
        $nextRun = $this->calculateNextRun($scheduleCron);

        $stmt = $this->db->prepare('
            INSERT INTO backup_jobs (name, description, backup_type, items, schedule_cron, destination_id, retention_days, next_run, enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ');
        
        $stmt->execute([
            $name,
            $description,
            $backupType,
            json_encode($items),
            $scheduleCron,
            $destinationId,
            $retentionDays,
            $nextRun
        ]);

        $id = (int) $this->db->lastInsertId();
        
        return $this->getJob($id);
    }

    /**
     * Update an existing job
     */
    public function updateJob(int $id, array $data): array
    {
        $job = $this->getJob($id);
        if (!$job) {
            throw new \Exception('Job not found');
        }

        $updates = [];
        $values = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $values[] = $data['name'];
        }

        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $values[] = $data['description'];
        }

        if (isset($data['backup_type'])) {
            $updates[] = 'backup_type = ?';
            $values[] = $data['backup_type'];
        }

        if (isset($data['items'])) {
            $updates[] = 'items = ?';
            $values[] = json_encode($data['items']);
        }

        if (isset($data['schedule_cron'])) {
            $this->validateCronExpression($data['schedule_cron']);
            $updates[] = 'schedule_cron = ?';
            $values[] = $data['schedule_cron'];
            
            // Recalculate next run
            $nextRun = $this->calculateNextRun($data['schedule_cron']);
            $updates[] = 'next_run = ?';
            $values[] = $nextRun;
        }

        if (isset($data['destination_id'])) {
            $this->validateDestination($data['destination_id']);
            $updates[] = 'destination_id = ?';
            $values[] = $data['destination_id'];
        }

        if (isset($data['retention_days'])) {
            $updates[] = 'retention_days = ?';
            $values[] = $data['retention_days'];
        }

        if (isset($data['enabled'])) {
            $updates[] = 'enabled = ?';
            $values[] = $data['enabled'];
        }

        if (empty($updates)) {
            return $job;
        }

        $values[] = $id;

        $sql = 'UPDATE backup_jobs SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return $this->getJob($id);
    }

    /**
     * Delete a job
     */
    public function deleteJob(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM backup_jobs WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Execute a specific job manually
     */
    public function executeJob(int $jobId): array
    {
        $job = $this->getJob($jobId);
        if (!$job) {
            throw new \Exception('Job not found');
        }

        if (!$job['enabled']) {
            throw new \Exception('Job is disabled');
        }

        try {
            // Create the backup
            $result = $this->backupService->createBackup(
                $job['backup_type'],
                $job['items'],
                $job['destination_id']
            );

            // Update job's last_run and next_run
            $nextRun = $this->calculateNextRun($job['schedule_cron']);
            
            $stmt = $this->db->prepare('
                UPDATE backup_jobs 
                SET last_run = NOW(), next_run = ?
                WHERE id = ?
            ');
            $stmt->execute([$nextRun, $jobId]);

            // Clean up old backups based on retention policy
            if ($job['retention_days'] > 0) {
                $this->cleanupOldBackups($jobId, $job['retention_days']);
            }

            return $result;

        } catch (\Exception $e) {
            throw new \Exception('Job execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Get jobs that are due to run
     */
    public function getDueJobs(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM backup_jobs
            WHERE enabled = TRUE
            AND next_run <= NOW()
            ORDER BY next_run ASC
        ');
        
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jobs as &$job) {
            $job['items'] = json_decode($job['items'], true);
        }
        
        return $jobs;
    }

    /**
     * Run all due jobs (called by cron)
     */
    public function runDueJobs(): array
    {
        $jobs = $this->getDueJobs();
        $results = [];

        foreach ($jobs as $job) {
            try {
                $result = $this->executeJob($job['id']);
                $results[] = [
                    'job_id' => $job['id'],
                    'job_name' => $job['name'],
                    'status' => 'success',
                    'backup_id' => $result['id']
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'job_id' => $job['id'],
                    'job_name' => $job['name'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    // Private helper methods

    private function validateDestination(int $destinationId): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM backup_destinations WHERE id = ? AND enabled = TRUE');
        $stmt->execute([$destinationId]);
        
        if ($stmt->fetchColumn() == 0) {
            throw new \Exception('Invalid or disabled destination');
        }
    }

    private function validateCronExpression(string $cron): void
    {
        $parts = explode(' ', trim($cron));
        
        if (count($parts) !== 5) {
            throw new \Exception('Invalid cron expression. Expected format: "* * * * *" (minute hour day month weekday)');
        }

        // Basic validation - each part should be *, number, range, or list
        foreach ($parts as $part) {
            if (!preg_match('/^(\*|[0-9]+|[0-9]+-[0-9]+|[0-9]+(,[0-9]+)*)$/', $part)) {
                throw new \Exception('Invalid cron expression part: ' . $part);
            }
        }
    }

    private function calculateNextRun(string $cronExpression): string
    {
        // Simple cron parser - this is a basic implementation
        // For production, consider using a library like dragonmantank/cron-expression
        
        list($minute, $hour, $day, $month, $weekday) = explode(' ', $cronExpression);
        
        $now = time();
        $next = $now;

        // Simple logic: if all are *, run in 1 hour
        if ($minute === '*' && $hour === '*' && $day === '*' && $month === '*' && $weekday === '*') {
            $next = strtotime('+1 hour', $now);
        }
        // Daily at specific time
        elseif ($hour !== '*' && $minute !== '*' && $day === '*' && $month === '*' && $weekday === '*') {
            $targetHour = (int) $hour;
            $targetMinute = (int) $minute;
            
            $next = mktime($targetHour, $targetMinute, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
            
            // If time has passed today, schedule for tomorrow
            if ($next <= $now) {
                $next = strtotime('+1 day', $next);
            }
        }
        // Hourly at specific minute
        elseif ($minute !== '*' && $hour === '*') {
            $targetMinute = (int) $minute;
            $currentHour = (int)date('H');
            $currentMinute = (int)date('i');
            
            if ($currentMinute >= $targetMinute) {
                // Next hour
                $next = mktime($currentHour + 1, $targetMinute, 0);
            } else {
                // This hour
                $next = mktime($currentHour, $targetMinute, 0);
            }
        }
        // Default: 1 hour from now
        else {
            $next = strtotime('+1 hour', $now);
        }

        return date('Y-m-d H:i:s', $next);
    }

    private function cleanupOldBackups(int $jobId, int $retentionDays): void
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        // Get old backups
        $stmt = $this->db->prepare('
            SELECT id FROM backup_history
            WHERE job_id = ?
            AND status = "completed"
            AND created_at < ?
        ');
        $stmt->execute([$jobId, $cutoffDate]);
        $oldBackups = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Delete each old backup
        foreach ($oldBackups as $backupId) {
            try {
                $this->backupService->deleteBackup($backupId);
            } catch (\Exception $e) {
                // Log error but continue with other deletions
                error_log("Failed to delete backup {$backupId}: " . $e->getMessage());
            }
        }
    }
}
