<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Database\Connection;

class BackupDestinationService
{
    private PDO $db;

    public function __construct(array $mysqlConfig)
    {
        $this->db = Connection::get($mysqlConfig);
    }

    /**
     * List all backup destinations
     */
    public function listDestinations(): array
    {
        $stmt = $this->db->query('
            SELECT id, name, type, config, is_default, enabled, created_at, updated_at
            FROM backup_destinations
            ORDER BY is_default DESC, name ASC
        ');
        
        $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse config JSON and mask sensitive data
        foreach ($destinations as &$dest) {
            $config = json_decode($dest['config'], true);
            
            if ($dest['type'] === 'sftp' && isset($config['password'])) {
                $config['password'] = '********';
            }
            
            $dest['config'] = $config;
            $dest['is_default'] = (bool) $dest['is_default'];
            $dest['enabled'] = (bool) $dest['enabled'];
        }
        
        return $destinations;
    }

    /**
     * Get a single destination
     */
    public function getDestination(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM backup_destinations WHERE id = ?');
        $stmt->execute([$id]);
        
        $dest = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dest) {
            $dest['config'] = json_decode($dest['config'], true);
            $dest['is_default'] = (bool) $dest['is_default'];
            $dest['enabled'] = (bool) $dest['enabled'];
        }
        
        return $dest ?: null;
    }

    /**
     * Create a new backup destination
     */
    public function createDestination(string $name, string $type, array $config, bool $isDefault = false): array
    {
        // Validate config based on type
        $this->validateConfig($type, $config);

        // If setting as default, unset other defaults
        if ($isDefault) {
            $this->db->exec('UPDATE backup_destinations SET is_default = FALSE');
        }

        $stmt = $this->db->prepare('
            INSERT INTO backup_destinations (name, type, config, is_default, enabled)
            VALUES (?, ?, ?, ?, TRUE)
        ');
        
        $stmt->execute([
            $name,
            $type,
            json_encode($config),
            $isDefault
        ]);

        $id = (int) $this->db->lastInsertId();
        
        return $this->getDestination($id);
    }

    /**
     * Update an existing destination
     */
    public function updateDestination(int $id, array $data): array
    {
        $destination = $this->getDestination($id);
        if (!$destination) {
            throw new \Exception('Destination not found');
        }

        $updates = [];
        $values = [];

        if (isset($data['name'])) {
            $updates[] = 'name = ?';
            $values[] = $data['name'];
        }

        if (isset($data['config'])) {
            $this->validateConfig($destination['type'], $data['config']);
            $updates[] = 'config = ?';
            $values[] = json_encode($data['config']);
        }

        if (isset($data['enabled'])) {
            $updates[] = 'enabled = ?';
            $values[] = $data['enabled'];
        }

        if (isset($data['is_default']) && $data['is_default']) {
            $this->db->exec('UPDATE backup_destinations SET is_default = FALSE');
            $updates[] = 'is_default = ?';
            $values[] = true;
        }

        if (empty($updates)) {
            return $destination;
        }

        $values[] = $id;

        $sql = 'UPDATE backup_destinations SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return $this->getDestination($id);
    }

    /**
     * Delete a destination
     */
    public function deleteDestination(int $id): void
    {
        // Check if destination is used by any jobs
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM backup_jobs WHERE destination_id = ?');
        $stmt->execute([$id]);
        $jobCount = $stmt->fetchColumn();

        if ($jobCount > 0) {
            throw new \Exception('Cannot delete destination: it is used by ' . $jobCount . ' backup job(s)');
        }

        $stmt = $this->db->prepare('DELETE FROM backup_destinations WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Test connection to a destination
     */
    public function testConnection(int $id): array
    {
        $destination = $this->getDestination($id);
        if (!$destination) {
            throw new \Exception('Destination not found');
        }

        $config = $destination['config'];

        try {
            if ($destination['type'] === 'local') {
                return $this->testLocalConnection($config);
            }

            if ($destination['type'] === 'sftp') {
                return $this->testSftpConnection($config);
            }

            throw new \Exception('Unknown destination type');

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload a file to a destination
     */
    public function uploadFile(int $destinationId, string $localFile, string $remoteFileName): string
    {
        $destination = $this->getDestination($destinationId);
        if (!$destination) {
            throw new \Exception('Destination not found');
        }

        $config = $destination['config'];

        if ($destination['type'] === 'local') {
            $remotePath = rtrim($config['path'], '/') . '/' . $remoteFileName;
            
            if (!is_dir($config['path'])) {
                mkdir($config['path'], 0755, true);
            }
            
            if (!copy($localFile, $remotePath)) {
                throw new \Exception('Failed to copy file to destination');
            }
            
            return $remotePath;
        }

        if ($destination['type'] === 'sftp') {
            return $this->uploadViaSftp($localFile, $remoteFileName, $config);
        }

        throw new \Exception('Unknown destination type');
    }

    /**
     * List files in a destination
     */
    public function listFiles(int $destinationId, string $pattern = '*.tar.gz'): array
    {
        $destination = $this->getDestination($destinationId);
        if (!$destination) {
            throw new \Exception('Destination not found');
        }

        $config = $destination['config'];

        if ($destination['type'] === 'local') {
            return $this->listLocalFiles($config['path'], $pattern);
        }

        if ($destination['type'] === 'sftp') {
            return $this->listSftpFiles($config, $pattern);
        }

        throw new \Exception('Unknown destination type');
    }

    /**
     * Download a file from a destination
     */
    public function downloadFile(int $destinationId, string $remoteFile, string $localFile): void
    {
        $destination = $this->getDestination($destinationId);
        if (!$destination) {
            throw new \Exception('Destination not found');
        }

        $config = $destination['config'];

        if ($destination['type'] === 'local') {
            $sourcePath = rtrim($config['path'], '/') . '/' . $remoteFile;
            
            if (!file_exists($sourcePath)) {
                throw new \Exception('File not found in destination');
            }
            
            if (!copy($sourcePath, $localFile)) {
                throw new \Exception('Failed to copy file from destination');
            }
            
            return;
        }

        if ($destination['type'] === 'sftp') {
            $this->downloadViaSftp($remoteFile, $localFile, $config);
            return;
        }

        throw new \Exception('Unknown destination type');
    }

    // Private helper methods

    private function validateConfig(string $type, array $config): void
    {
        if ($type === 'local') {
            if (empty($config['path'])) {
                throw new \Exception('Local destination requires a path');
            }
        }

        if ($type === 'sftp') {
            $required = ['host', 'port', 'username', 'path'];
            foreach ($required as $field) {
                if (empty($config[$field])) {
                    throw new \Exception("SFTP destination requires: {$field}");
                }
            }

            if (empty($config['password']) && empty($config['private_key'])) {
                throw new \Exception('SFTP requires either password or private_key');
            }
        }
    }

    private function testLocalConnection(array $config): array
    {
        $path = $config['path'];

        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Directory does not exist and could not be created'
                ];
            }
        }

        if (!is_writable($path)) {
            return [
                'success' => false,
                'message' => 'Directory is not writable'
            ];
        }

        $testFile = $path . '/.test_' . time();
        if (!file_put_contents($testFile, 'test')) {
            return [
                'success' => false,
                'message' => 'Cannot write test file to directory'
            ];
        }

        unlink($testFile);

        $size = disk_free_space($path);
        $sizeFormatted = $this->formatBytes($size);

        return [
            'success' => true,
            'message' => "Connection successful. Free space: {$sizeFormatted}",
            'free_space' => $size
        ];
    }

    private function testSftpConnection(array $config): array
    {
        // Note: This requires phpseclib3 library
        // For now, return a placeholder response
        return [
            'success' => false,
            'message' => 'SFTP support requires phpseclib3 library. Install with: composer require phpseclib/phpseclib'
        ];
        
        // TODO: Implement with phpseclib when available
        /*
        $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port']);
        
        if (!empty($config['password'])) {
            if (!$sftp->login($config['username'], $config['password'])) {
                throw new \Exception('SFTP authentication failed');
            }
        } else {
            $key = \phpseclib3\Crypt\PublicKeyLoader::load($config['private_key']);
            if (!$sftp->login($config['username'], $key)) {
                throw new \Exception('SFTP key authentication failed');
            }
        }

        if (!$sftp->chdir($config['path'])) {
            throw new \Exception('Cannot access remote directory');
        }

        return [
            'success' => true,
            'message' => 'SFTP connection successful'
        ];
        */
    }

    private function uploadViaSftp(string $localFile, string $remoteFileName, array $config): string
    {
        throw new \Exception('SFTP upload requires phpseclib3 library');
        // TODO: Implement with phpseclib
    }

    private function downloadViaSftp(string $remoteFile, string $localFile, array $config): void
    {
        throw new \Exception('SFTP download requires phpseclib3 library');
        // TODO: Implement with phpseclib
    }

    private function listLocalFiles(string $path, string $pattern): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/' . $pattern);
        $result = [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $result[] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'path' => $file
                ];
            }
        }

        usort($result, fn($a, $b) => $b['modified'] - $a['modified']);

        return $result;
    }

    private function listSftpFiles(array $config, string $pattern): array
    {
        throw new \Exception('SFTP file listing requires phpseclib3 library');
        // TODO: Implement with phpseclib
    }

    private function formatBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
