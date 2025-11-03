<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;
use App\Support\CommandRunner;

class BackupService
{
    private PDO $db;
    private array $mysqlConfig;
    private CommandRunner $commandRunner;
    private ?int $currentHistoryId = null;

    public function __construct(array $mysqlConfig, array $allowedCommands = [])
    {
        $this->db = Connection::get($mysqlConfig);
        $this->mysqlConfig = $mysqlConfig;
        $this->commandRunner = new CommandRunner($allowedCommands);
    }

    /**
     * Get backup progress for real-time updates
     */
    public function getBackupProgress(int $historyId): array
    {
        $backup = $this->getBackupById($historyId);
        if (!$backup) {
            throw new \Exception('Backup not found');
        }

        $progress = json_decode($backup['progress_data'] ?? '{}', true) ?: [];
        
        return [
            'history_id' => $historyId,
            'status' => $backup['status'],
            'progress' => $progress,
            'error_message' => $backup['error_message'],
            'completed_at' => $backup['completed_at']
        ];
    }

    /**
     * Update progress information
     */
    private function updateProgress(string $message, ?string $itemType = null, ?string $itemName = null, ?string $itemStatus = null): void
    {
        if (!$this->currentHistoryId) {
            return;
        }

        // Get current progress
        $backup = $this->getBackupById($this->currentHistoryId);
        $progress = json_decode($backup['progress_data'] ?? '{}', true) ?: [
            'message' => '',
            'items' => []
        ];

        $progress['message'] = $message;
        $progress['timestamp'] = date('Y-m-d H:i:s');

        // Update specific item progress
        if ($itemType && $itemName) {
            $found = false;
            foreach ($progress['items'] as &$item) {
                if ($item['type'] === $itemType && $item['name'] === $itemName) {
                    $item['status'] = $itemStatus ?? 'completed';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $progress['items'][] = [
                    'type' => $itemType,
                    'name' => $itemName,
                    'status' => $itemStatus ?? 'completed'
                ];
            }
        }

        // Update in database
        $stmt = $this->db->prepare('UPDATE backup_history SET progress_data = ? WHERE id = ?');
        $stmt->execute([json_encode($progress), $this->currentHistoryId]);
    }

    /**
     * Create a manual backup
     */
    public function createBackup(string $type, array $items, int $destinationId): array
    {
        $destination = $this->getDestination($destinationId);
        if (!$destination) {
            throw new \Exception('Backup destination not found');
        }

        $timestamp = date('Y-m-d_His');
        $fileName = "backup_{$type}_{$timestamp}.tar.gz";
        
        // Get the final destination path directly
        $destinationPath = $this->getDestinationPath($destination, $fileName);
        
        // Create backup history record
        $historyId = $this->createHistoryRecord(null, $type, $items, $destination['type'], $fileName);
        $this->currentHistoryId = $historyId;

        try {
            $this->updateHistoryStatus($historyId, 'in_progress');
            $this->updateProgress('Starting backup...');

            // Create the backup directly at destination
            $backupData = match($type) {
                'site' => $this->backupSites($items, $destinationPath),
                'database' => $this->backupDatabases($items, $destinationPath),
                'domain' => $this->backupDomains($items, $destinationPath),
                'mixed' => $this->backupMixed($items, $destinationPath),
                default => throw new \Exception('Invalid backup type')
            };

            // Get file size
            $fileSize = filesize($destinationPath);

            // Update history
            $this->updateHistoryRecord($historyId, [
                'items' => json_encode($backupData),
                'destination_path' => $destinationPath,
                'file_size' => $fileSize,
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'id' => $historyId,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'destination_path' => $destinationPath,
                'items' => $backupData
            ];

        } catch (\Exception $e) {
            $this->updateHistoryStatus($historyId, 'failed', $e->getMessage());
            @unlink($destinationPath);
            throw $e;
        }
    }

    /**
     * List all backups with optional filtering
     */
    public function listBackups(?string $type = null, ?int $limit = 100): array
    {
        $sql = 'SELECT * FROM backup_history';
        $params = [];

        if ($type) {
            $sql .= ' WHERE backup_type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY created_at DESC';

        if ($limit) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($backups as &$backup) {
            $backup['items'] = json_decode($backup['items'], true);
        }

        return $backups;
    }

    /**
     * Restore from a backup
     */
    public function restoreBackup(int $historyId, array $itemsToRestore = []): array
    {
        $backup = $this->getBackupById($historyId);
        if (!$backup) {
            throw new \Exception('Backup not found');
        }

        if ($backup['status'] !== 'completed') {
            throw new \Exception('Cannot restore from incomplete backup');
        }

        // Use destination directory for restore temp files
        $backupDir = dirname($backup['destination_path']);
        $tempPath = $backupDir . '/.tmp_restore_' . time();
        mkdir($tempPath, 0755, true);
        
        $tempFile = "{$tempPath}/restore_{$historyId}.tar.gz";
        $this->downloadBackupFile($backup, $tempFile);

        try {
            $restored = [];
            $backupItems = json_decode($backup['items'], true);

            // If no specific items specified, restore all
            if (empty($itemsToRestore)) {
                $itemsToRestore = $this->getAllItemsFromBackup($backupItems);
            }

            // Extract and restore based on type
            $extractPath = "{$tempPath}/extract";
            mkdir($extractPath, 0755, true);
            
            $command = sprintf(
                'sudo tar -xzpf %s -C %s',
                escapeshellarg($tempFile),
                escapeshellarg($extractPath)
            );
            $result = $this->commandRunner->run($command, 300);
            
            if ($result['exit_code'] !== 0) {
                throw new \Exception('Failed to extract backup: ' . $result['stderr']);
            }

            foreach ($itemsToRestore as $item) {
                $restored[] = match($item['type']) {
                    'site' => $this->restoreSite($item['name'], $extractPath),
                    'database' => $this->restoreDatabase($item['name'], $extractPath),
                    'domain' => $this->restoreDomain($item['name'], $extractPath),
                    default => null
                };
            }

            // Cleanup
            $command = 'sudo rm -rf ' . escapeshellarg($tempPath);
            $this->commandRunner->run($command, 30);

            return array_filter($restored);

        } catch (\Exception $e) {
            $command = 'sudo rm -rf ' . escapeshellarg($tempPath);
            $this->commandRunner->run($command, 30);
            throw $e;
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(int $historyId): void
    {
        $backup = $this->getBackupById($historyId);
        if (!$backup) {
            throw new \Exception('Backup not found');
        }

        // Delete file from destination
        $this->deleteBackupFile($backup);

        // Delete history record
        $stmt = $this->db->prepare('DELETE FROM backup_history WHERE id = ?');
        $stmt->execute([$historyId]);
    }

    /**
     * Backup sites (web files)
     */
    private function backupSites(array $siteNames, string $outputFile): array
    {
        $items = [];
        $paths = [];

        $this->updateProgress('Preparing to backup websites...');

        foreach ($siteNames as $siteName) {
            $this->updateProgress("Processing website: {$siteName}", 'site', $siteName, 'in_progress');
            
            $site = $this->getSiteByName($siteName);
            if ($site && is_dir($site['root'])) {
                $paths[] = $site['root'];
                $items[] = [
                    'type' => 'site',
                    'name' => $siteName,
                    'path' => $site['root']
                ];
                $this->updateProgress("Website ready: {$siteName}", 'site', $siteName, 'completed');
            } else {
                $this->updateProgress("Website not found: {$siteName}", 'site', $siteName, 'error');
            }
        }

        if (empty($paths)) {
            throw new \Exception('No valid sites to backup');
        }

        $this->updateProgress('Creating archive of websites...');

        // Create tar.gz archive
        $escapedPaths = array_map('escapeshellarg', $paths);
        $command = 'sudo tar -czpf ' . escapeshellarg($outputFile) . ' -C / ' . implode(' ', $escapedPaths);
        $result = $this->commandRunner->run($command, 300);
        
        if ($result['exit_code'] !== 0) {
            throw new \Exception('Failed to create site backup: ' . $result['stderr']);
        }

        $this->updateProgress('Websites backup completed');

        return $items;
    }

    /**
     * Backup databases
     */
    private function backupDatabases(array $databaseNames, string $outputFile): array
    {
        $items = [];
        // Create temp dir in same location as output file (same filesystem)
        $destDir = dirname($outputFile);
        $tempDir = $destDir . '/.tmp_db_' . time();
        mkdir($tempDir, 0755, true);

        $this->updateProgress('Preparing to backup databases...');

        foreach ($databaseNames as $dbName) {
            $this->updateProgress("Dumping database: {$dbName}", 'database', $dbName, 'in_progress');
            
            $dumpFile = "{$tempDir}/{$dbName}.sql";
            
            // Build mysqldump command with credentials - use --result-file to avoid shell redirect issues
            $command = sprintf(
                'sudo mysqldump -h %s -u %s -p%s --single-transaction --quick --lock-tables=false --result-file=%s %s',
                escapeshellarg($this->mysqlConfig['host'] ?? 'localhost'),
                escapeshellarg($this->mysqlConfig['username']),
                escapeshellarg($this->mysqlConfig['password']),
                escapeshellarg($dumpFile),
                escapeshellarg($dbName)
            );
            $result = $this->commandRunner->run($command, 300);
            
            if ($result['exit_code'] !== 0) {
                $this->updateProgress("Failed to dump database: {$dbName}", 'database', $dbName, 'error');
                throw new \Exception("Failed to dump database {$dbName}: " . $result['stderr']);
            }

            $items[] = [
                'type' => 'database',
                'name' => $dbName,
                'size' => filesize($dumpFile)
            ];
            
            $this->updateProgress("Database dump completed: {$dbName}", 'database', $dbName, 'completed');
        }

        $this->updateProgress('Compressing database backups...');

        // Compress all dumps directly to final destination
        $command = sprintf(
            'sudo tar -czpf %s -C %s .',
            escapeshellarg($outputFile),
            escapeshellarg($tempDir)
        );
        $result = $this->commandRunner->run($command, 300);
        
        if ($result['exit_code'] !== 0) {
            throw new \Exception('Failed to compress database backups: ' . $result['stderr']);
        }
        
        // Cleanup temp dumps
        $command = 'sudo rm -rf ' . escapeshellarg($tempDir);
        $this->commandRunner->run($command, 30);

        $this->updateProgress('Database backups completed');

        return $items;
    }

    /**
     * Backup DNS domains
     */
    private function backupDomains(array $domainNames, string $outputFile): array
    {
        $items = [];
        // Create temp dir in same location as output file (same filesystem)
        $destDir = dirname($outputFile);
        $tempDir = $destDir . '/.tmp_dns_' . time();
        mkdir($tempDir, 0755, true);

        $this->updateProgress('Preparing to backup domains...');

        // Export DNS zones from PowerDNS
        foreach ($domainNames as $domainName) {
            $this->updateProgress("Exporting domain: {$domainName}", 'domain', $domainName, 'in_progress');
            
            $zoneFile = "{$tempDir}/{$domainName}.zone";
            
            // Query PowerDNS records
            $stmt = $this->db->prepare('
                SELECT name, type, content, ttl, prio
                FROM records
                WHERE domain_id = (SELECT id FROM domains WHERE name = ?)
                ORDER BY type, name
            ');
            $stmt->execute([$domainName]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Write zone file
            $zoneContent = "; Zone file for {$domainName}\n";
            $zoneContent .= "; Exported: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($records as $record) {
                $zoneContent .= sprintf(
                    "%s\t%d\tIN\t%s\t%s%s\n",
                    $record['name'],
                    $record['ttl'],
                    $record['type'],
                    $record['prio'] ? $record['prio'] . ' ' : '',
                    $record['content']
                );
            }

            file_put_contents($zoneFile, $zoneContent);

            $items[] = [
                'type' => 'domain',
                'name' => $domainName,
                'records' => count($records)
            ];
            
            $this->updateProgress("Domain exported: {$domainName}", 'domain', $domainName, 'completed');
        }

        $this->updateProgress('Compressing domain backups...');

        // Compress all zone files
        $command = sprintf(
            'sudo tar -czpf %s -C %s .',
            escapeshellarg($outputFile),
            escapeshellarg($tempDir)
        );
        $result = $this->commandRunner->run($command, 300);
        
        if ($result['exit_code'] !== 0) {
            throw new \Exception('Failed to compress domain backups: ' . $result['stderr']);
        }
        
        // Cleanup
        $command = 'sudo rm -rf ' . escapeshellarg($tempDir);
        $this->commandRunner->run($command, 30);

        $this->updateProgress('Domain backups completed');

        return $items;
    }

    /**
     * Backup mixed items (sites, databases, domains)
     */
    private function backupMixed(array $items, string $outputFile): array
    {
        $result = [];
        // Create temp dir in same location as output file (same filesystem)
        $destDir = dirname($outputFile);
        $tempDir = $destDir . '/.tmp_mixed_' . time();
        mkdir($tempDir, 0755, true);

        // Backup each type to its own file
        if (!empty($items['sites'])) {
            $sitesFile = "{$tempDir}/sites.tar.gz";
            $result = array_merge($result, $this->backupSites($items['sites'], $sitesFile));
        }

        if (!empty($items['databases'])) {
            $dbFile = "{$tempDir}/databases.tar.gz";
            $result = array_merge($result, $this->backupDatabases($items['databases'], $dbFile));
        }

        if (!empty($items['domains'])) {
            $domainsFile = "{$tempDir}/domains.tar.gz";
            $result = array_merge($result, $this->backupDomains($items['domains'], $domainsFile));
        }

        // Compress everything together to final destination
        $command = sprintf(
            'sudo tar -czpf %s -C %s .',
            escapeshellarg($outputFile),
            escapeshellarg($tempDir)
        );
        $result_exec = $this->commandRunner->run($command, 300);
        
        if ($result_exec['exit_code'] !== 0) {
            throw new \Exception('Failed to compress mixed backup: ' . $result_exec['stderr']);
        }
        
        // Cleanup temp files
        $command = 'sudo rm -rf ' . escapeshellarg($tempDir);
        $this->commandRunner->run($command, 30);

        return $result;
    }

    /**
     * Get the full destination path for a backup file
     */
    private function getDestinationPath(array $destination, string $fileName): string
    {
        $config = json_decode($destination['config'], true);

        if ($destination['type'] === 'local') {
            $destPath = rtrim($config['path'], '/') . '/' . $fileName;
            
            // Ensure destination directory exists
            if (!is_dir($config['path'])) {
                mkdir($config['path'], 0755, true);
            }
            
            return $destPath;
        }

        if ($destination['type'] === 'sftp') {
            throw new \Exception('SFTP destination not yet supported');
        }

        throw new \Exception('Unknown destination type');
    }

    // Helper methods

    private function createHistoryRecord(?int $jobId, string $type, array $items, string $destType, string $fileName): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO backup_history (job_id, backup_type, items, destination_type, destination_path, file_name, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $jobId,
            $type,
            json_encode($items),
            $destType,
            '', // Will be updated after transfer
            $fileName,
            'pending'
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function updateHistoryRecord(int $id, array $data): void
    {
        $sets = [];
        $values = [];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $sql = 'UPDATE backup_history SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    private function updateHistoryStatus(int $id, string $status, ?string $errorMessage = null): void
    {
        $stmt = $this->db->prepare('UPDATE backup_history SET status = ?, error_message = ? WHERE id = ?');
        $stmt->execute([$status, $errorMessage, $id]);
    }

    private function getDestination(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM backup_destinations WHERE id = ? AND enabled = TRUE');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getSiteByName(string $serverName): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sites WHERE server_name = ?');
        $stmt->execute([$serverName]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getBackupById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM backup_history WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function downloadBackupFile(array $backup, string $destFile): void
    {
        if ($backup['destination_type'] === 'local') {
            copy($backup['destination_path'], $destFile);
        } else {
            throw new \Exception('SFTP download not yet implemented');
        }
    }

    private function deleteBackupFile(array $backup): void
    {
        if ($backup['destination_type'] === 'local' && file_exists($backup['destination_path'])) {
            unlink($backup['destination_path']);
        }
    }

    private function getAllItemsFromBackup(array $backupItems): array
    {
        return $backupItems;
    }

    private function restoreSite(string $siteName, string $extractPath): array
    {
        $site = $this->getSiteByName($siteName);
        if (!$site) {
            throw new \Exception("Site not found: {$siteName}");
        }

        $siteBackupPath = $extractPath . '/' . ltrim($site['root'], '/');
        
        if (!is_dir($siteBackupPath)) {
            throw new \Exception("Site backup files not found for: {$siteName}");
        }

        // Copy files to destination
        $command = sprintf(
            'sudo cp -a %s/* %s/',
            escapeshellarg($siteBackupPath),
            escapeshellarg($site['root'])
        );
        $result = $this->commandRunner->run($command, 300);
        
        if ($result['exit_code'] !== 0) {
            throw new \Exception("Failed to restore site {$siteName}: " . $result['stderr']);
        }

        return ['type' => 'site', 'name' => $siteName, 'status' => 'restored'];
    }

    private function restoreDatabase(string $dbName, string $extractPath): array
    {
        // Handle both direct SQL files and compressed database backups
        $sqlFile = "{$extractPath}/{$dbName}.sql";
        $dbArchive = "{$extractPath}/databases.tar.gz";
        
        // If databases are in a tar.gz (from mixed backup), extract first
        if (!file_exists($sqlFile) && file_exists($dbArchive)) {
            $dbExtractPath = "{$extractPath}/db_temp";
            mkdir($dbExtractPath, 0755, true);
            
            $command = sprintf(
                'sudo tar -xzpf %s -C %s',
                escapeshellarg($dbArchive),
                escapeshellarg($dbExtractPath)
            );
            $this->commandRunner->run($command, 60);
            
            $sqlFile = "{$dbExtractPath}/{$dbName}.sql";
        }
        
        if (!file_exists($sqlFile)) {
            throw new \Exception("Database dump file not found: {$dbName}.sql");
        }

        // Restore database - use shell here because we need input redirection
        $command = sprintf(
            'sudo sh -c "mysql -h %s -u %s -p%s %s < %s"',
            escapeshellarg($this->mysqlConfig['host'] ?? 'localhost'),
            escapeshellarg($this->mysqlConfig['username']),
            escapeshellarg($this->mysqlConfig['password']),
            escapeshellarg($dbName),
            escapeshellarg($sqlFile)
        );
        $result = $this->commandRunner->run($command, 300);
        
        if ($result['exit_code'] !== 0) {
            throw new \Exception("Failed to restore database {$dbName}: " . $result['stderr']);
        }

        return ['type' => 'database', 'name' => $dbName, 'status' => 'restored'];
    }

    private function restoreDomain(string $domainName, string $extractPath): array
    {
        // Handle both direct zone files and compressed domain backups
        $zoneFile = "{$extractPath}/{$domainName}.zone";
        $domainArchive = "{$extractPath}/domains.tar.gz";
        
        // If domains are in a tar.gz (from mixed backup), extract first
        if (!file_exists($zoneFile) && file_exists($domainArchive)) {
            $domainExtractPath = "{$extractPath}/dns_temp";
            mkdir($domainExtractPath, 0755, true);
            
            $command = sprintf(
                'sudo tar -xzpf %s -C %s',
                escapeshellarg($domainArchive),
                escapeshellarg($domainExtractPath)
            );
            $this->commandRunner->run($command, 60);
            
            $zoneFile = "{$domainExtractPath}/{$domainName}.zone";
        }
        
        if (!file_exists($zoneFile)) {
            throw new \Exception("Zone file not found: {$domainName}.zone");
        }

        // Parse zone file and restore records
        $zoneContent = file_get_contents($zoneFile);
        $lines = explode("\n", $zoneContent);
        
        // Get domain ID
        $stmt = $this->db->prepare('SELECT id FROM domains WHERE name = ?');
        $stmt->execute([$domainName]);
        $domain = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$domain) {
            throw new \Exception("Domain not found in DNS: {$domainName}");
        }

        // Delete existing records
        $stmt = $this->db->prepare('DELETE FROM records WHERE domain_id = ?');
        $stmt->execute([$domain['id']]);

        // Parse and insert records
        $recordsRestored = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, ';')) {
                continue; // Skip comments and empty lines
            }

            // Parse: name TTL IN type [prio] content
            if (preg_match('/^(\S+)\s+(\d+)\s+IN\s+(\S+)\s+(?:(\d+)\s+)?(.+)$/', $line, $matches)) {
                $name = $matches[1];
                $ttl = (int)$matches[2];
                $type = $matches[3];
                $prio = isset($matches[4]) && $matches[4] !== '' ? (int)$matches[4] : null;
                $content = $matches[5];

                $stmt = $this->db->prepare('
                    INSERT INTO records (domain_id, name, type, content, ttl, prio)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$domain['id'], $name, $type, $content, $ttl, $prio]);
                $recordsRestored++;
            }
        }

        return [
            'type' => 'domain',
            'name' => $domainName,
            'status' => 'restored',
            'records' => $recordsRestored
        ];
    }
}
