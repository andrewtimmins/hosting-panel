#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Backup Worker - Long-running process that processes backup queue jobs
 * Managed by Supervisor for reliability and auto-restart
 */

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

use App\Database\Connection;
use App\Services\BackupService;
use App\Support\CommandRunner;

// Load configuration
$config = require __DIR__ . '/config/config.php';

// Database connection (config uses 'mysql' key, not 'database')
$db = Connection::get($config['mysql']);

$backupService = new BackupService(
    $config['mysql'],
    $config['security']['allowed_commands'] ?? []
);

echo "[" . date('Y-m-d H:i:s') . "] Backup Worker started\n";

// Main worker loop
while (true) {
    try {
        // Get next pending job (ordered by priority desc, created_at asc)
        $stmt = $db->prepare("
            SELECT * FROM backup_queue
            WHERE status = 'pending'
            ORDER BY priority DESC, created_at ASC
            LIMIT 1
            FOR UPDATE SKIP LOCKED
        ");
        $stmt->execute();
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job) {
            echo "[" . date('Y-m-d H:i:s') . "] Processing job #{$job['id']} - {$job['type']}\n";

            // Mark as processing
            $stmt = $db->prepare("
                UPDATE backup_queue
                SET status = 'processing', started_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$job['id']]);

            try {
                // Decode items
                $items = json_decode($job['items'], true);

                // Create the backup
                $result = $backupService->createBackup(
                    $job['type'],
                    $items,
                    (int)$job['destination_id']
                );

                // Update job as completed
                $stmt = $db->prepare("
                    UPDATE backup_queue
                    SET status = 'completed',
                        history_id = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$result['id'], $job['id']]);

                echo "[" . date('Y-m-d H:i:s') . "] Job #{$job['id']} completed successfully (history_id: {$result['id']})\n";

            } catch (\Exception $e) {
                // Update job as failed
                $stmt = $db->prepare("
                    UPDATE backup_queue
                    SET status = 'failed',
                        error_message = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$e->getMessage(), $job['id']]);

                echo "[" . date('Y-m-d H:i:s') . "] Job #{$job['id']} failed: {$e->getMessage()}\n";
            }

        } else {
            // No jobs pending, sleep for a bit
            sleep(2);
        }

    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Worker error: {$e->getMessage()}\n";
        sleep(5); // Sleep longer on error to avoid tight loop
    }
}
