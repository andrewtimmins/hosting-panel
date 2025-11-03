<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

class PhpConfigService
{
    private PDO $db;
    private string $phpFpmPoolDir;

    public function __construct(PDO $db, string $phpFpmPoolDir = '/etc/php/8.3/fpm/pool.d')
    {
        $this->db = $db;
        $this->phpFpmPoolDir = $phpFpmPoolDir;
    }

    /**
     * Get PHP configuration for a site
     */
    public function getConfig(string $serverName): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                php_version,
                php_memory_limit,
                php_upload_max_filesize,
                php_post_max_size,
                php_max_execution_time,
                php_max_input_time,
                php_custom_settings
            FROM site_configurations
            WHERE server_name = ?
        ");
        $stmt->execute([$serverName]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            throw new RuntimeException("Site not found: {$serverName}");
        }

        $config['php_custom_settings'] = $config['php_custom_settings'] 
            ? json_decode($config['php_custom_settings'], true) 
            : [];

        return $config;
    }

    /**
     * Update PHP configuration for a site
     */
    public function updateConfig(string $serverName, array $config): array
    {
        $updates = [];
        $params = [];

        if (isset($config['php_version'])) {
            if (!$this->isPhpVersionAvailable($config['php_version'])) {
                throw new RuntimeException("PHP version not available: {$config['php_version']}");
            }
            $updates[] = "php_version = ?";
            $params[] = $config['php_version'];
        }

        if (isset($config['php_memory_limit'])) {
            $updates[] = "php_memory_limit = ?";
            $params[] = $config['php_memory_limit'];
        }

        if (isset($config['php_upload_max_filesize'])) {
            $updates[] = "php_upload_max_filesize = ?";
            $params[] = $config['php_upload_max_filesize'];
        }

        if (isset($config['php_post_max_size'])) {
            $updates[] = "php_post_max_size = ?";
            $params[] = $config['php_post_max_size'];
        }

        if (isset($config['php_max_execution_time'])) {
            $updates[] = "php_max_execution_time = ?";
            $params[] = (int) $config['php_max_execution_time'];
        }

        if (isset($config['php_max_input_time'])) {
            $updates[] = "php_max_input_time = ?";
            $params[] = (int) $config['php_max_input_time'];
        }

        if (isset($config['php_custom_settings'])) {
            $updates[] = "php_custom_settings = ?";
            $params[] = json_encode($config['php_custom_settings']);
        }

        if (empty($updates)) {
            return $this->getConfig($serverName);
        }

        $params[] = $serverName;
        $sql = "UPDATE site_configurations SET " . implode(', ', $updates) . " WHERE server_name = ?";
        $this->db->prepare($sql)->execute($params);

        // Generate PHP-FPM pool configuration
        $this->generatePhpFpmPool($serverName);

        return $this->getConfig($serverName);
    }

    /**
     * Generate PHP-FPM pool configuration file
     */
    private function generatePhpFpmPool(string $serverName): void
    {
        $config = $this->getConfig($serverName);
        $poolName = str_replace('.', '_', $serverName);
        $socketPath = "/run/php/php{$config['php_version']}-fpm-{$poolName}.sock";

        $poolConfig = "[{$poolName}]\n";
        $poolConfig .= "user = www-data\n";
        $poolConfig .= "group = www-data\n";
        $poolConfig .= "listen = {$socketPath}\n";
        $poolConfig .= "listen.owner = www-data\n";
        $poolConfig .= "listen.group = www-data\n";
        $poolConfig .= "listen.mode = 0660\n\n";
        
        $poolConfig .= "pm = dynamic\n";
        $poolConfig .= "pm.max_children = 5\n";
        $poolConfig .= "pm.start_servers = 2\n";
        $poolConfig .= "pm.min_spare_servers = 1\n";
        $poolConfig .= "pm.max_spare_servers = 3\n\n";

        // PHP settings
        $poolConfig .= "php_admin_value[memory_limit] = {$config['php_memory_limit']}\n";
        $poolConfig .= "php_admin_value[upload_max_filesize] = {$config['php_upload_max_filesize']}\n";
        $poolConfig .= "php_admin_value[post_max_size] = {$config['php_post_max_size']}\n";
        $poolConfig .= "php_admin_value[max_execution_time] = {$config['php_max_execution_time']}\n";
        $poolConfig .= "php_admin_value[max_input_time] = {$config['php_max_input_time']}\n";

        // Custom settings
        foreach ($config['php_custom_settings'] as $key => $value) {
            $poolConfig .= "php_admin_value[{$key}] = {$value}\n";
        }

        // Write pool configuration
        $poolFile = "{$this->phpFpmPoolDir}/{$poolName}.conf";
        if (!file_put_contents($poolFile, $poolConfig)) {
            throw new RuntimeException("Failed to write PHP-FPM pool configuration");
        }

        // Reload PHP-FPM
        $version = str_replace('.', '', $config['php_version']);
        $command = "sudo systemctl reload php{$config['php_version']}-fpm";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException("Failed to reload PHP-FPM: " . implode("\n", $output));
        }
    }

    /**
     * Get available PHP versions
     */
    public function getAvailableVersions(): array
    {
        $versions = [];
        
        // Check for installed PHP versions
        $possibleVersions = ['7.4', '8.0', '8.1', '8.2', '8.3'];
        
        foreach ($possibleVersions as $version) {
            $command = "php{$version} --version 2>/dev/null";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0) {
                $versions[] = [
                    'version' => $version,
                    'label' => "PHP {$version}",
                    'available' => true
                ];
            }
        }

        return $versions;
    }

    /**
     * Check if PHP version is available
     */
    private function isPhpVersionAvailable(string $version): bool
    {
        $command = "php{$version} --version 2>/dev/null";
        exec($command, $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Get common PHP settings presets
     */
    public function getPresets(): array
    {
        return [
            'small' => [
                'label' => 'Small (Blog/Portfolio)',
                'php_memory_limit' => '128M',
                'php_upload_max_filesize' => '16M',
                'php_post_max_size' => '16M',
                'php_max_execution_time' => 60,
                'php_max_input_time' => 60
            ],
            'medium' => [
                'label' => 'Medium (Business Site)',
                'php_memory_limit' => '256M',
                'php_upload_max_filesize' => '64M',
                'php_post_max_size' => '64M',
                'php_max_execution_time' => 300,
                'php_max_input_time' => 300
            ],
            'large' => [
                'label' => 'Large (E-commerce)',
                'php_memory_limit' => '512M',
                'php_upload_max_filesize' => '128M',
                'php_post_max_size' => '128M',
                'php_max_execution_time' => 600,
                'php_max_input_time' => 600
            ],
        ];
    }

    /**
     * Get PHP socket path for a site
     */
    public function getSocketPath(string $serverName): string
    {
        $config = $this->getConfig($serverName);
        $poolName = str_replace('.', '_', $serverName);
        return "unix:/run/php/php{$config['php_version']}-fpm-{$poolName}.sock";
    }
}
