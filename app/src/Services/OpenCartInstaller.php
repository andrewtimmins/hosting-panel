<?php
namespace App\Services;

use App\Database\Connection;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use ZipArchive;

class OpenCartInstaller
{
    private PDO $db;
    private array $dbConfig;
    private ?SiteDatabaseService $siteDatabaseService;

    public function __construct(array $dbConfig, ?SiteDatabaseService $siteDatabaseService = null)
    {
        $this->db = Connection::get($dbConfig);
        $this->dbConfig = $dbConfig;
        $this->siteDatabaseService = $siteDatabaseService ?? new SiteDatabaseService($dbConfig);
    }

    /**
     * @param array{server_name:string, root:string} $site
     * @param array{install:bool, admin_username?:string, admin_password?:string, admin_email?:string} $options
     * @param array $defaults
     * @param bool $https
     */
    public function install(array $site, array $options, array $defaults, bool $https): array
    {
        if (!($options['install'] ?? false)) {
            throw new InvalidArgumentException('OpenCart installation requested without install flag');
        }

        $serverName = $site['server_name'];
        $root = rtrim($site['root'], '/');

        if (!is_dir($root)) {
            throw new RuntimeException('Document root does not exist: ' . $root);
        }

        $downloadUrl = $defaults['download_url'] ?? 'https://github.com/opencart/opencart/releases/download/4.0.2.3/opencart-4.0.2.3.zip';
        $adminUser = $options['admin_username'] ?? $defaults['default_admin_username'] ?? 'admin';
        $adminPassword = $options['admin_password'] ?? $defaults['default_admin_password'] ?? 'Admin123!';
        $adminEmail = $options['admin_email'] ?? $defaults['default_admin_email'] ?? 'admin@example.com';

        $adminUser = trim($adminUser);
        $adminPassword = trim($adminPassword);
        $adminEmail = trim($adminEmail);

        if ($adminUser === '' || $adminPassword === '' || $adminEmail === '') {
            throw new InvalidArgumentException('OpenCart admin credentials cannot be empty');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid admin email for OpenCart install');
        }

        $siteUrl = ($https ? 'https://' : 'http://') . $serverName . '/';
        $storeName = $defaults['default_store_name'] ?? str_replace('{server_name}', $serverName, 'OpenCart Store for {server_name}');

        [$archivePath, $extractPath] = $this->downloadAndExtract($downloadUrl);
        try {
            $this->copyOpenCartFiles($extractPath, $root);
            $this->prepareConfigFiles($root);
            $dbCredentials = $this->prepareDatabase($serverName);
            $this->runCliInstall($root, $siteUrl, $storeName, $adminUser, $adminEmail, $adminPassword, $dbCredentials);
            $this->cleanupInstaller($root);
            
            // Link the database to the site
            $this->linkDatabaseToSite($serverName, $dbCredentials);
        } finally {
            $this->cleanupFilesystem($archivePath, $extractPath);
        }

        return [
            'admin_username' => $adminUser,
            'admin_password' => $adminPassword,
            'admin_email' => $adminEmail,
            'site_url' => $siteUrl,
            'login_url' => $siteUrl . 'admin/',
            'db_name' => $dbCredentials['database'],
            'db_user' => $dbCredentials['username'],
            'db_password' => $dbCredentials['password'],
        ];
    }

    private function downloadAndExtract(string $url): array
    {
        $tempBase = sys_get_temp_dir() . '/oc_' . bin2hex(random_bytes(6));
        $archivePath = $tempBase . '.zip';
        $extractDir = $tempBase;

        if (!@mkdir($extractDir, 0755, true) && !is_dir($extractDir)) {
            throw new RuntimeException('Unable to create extraction directory: ' . $extractDir);
        }

        // Download with follow redirects for GitHub releases
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => 'Mozilla/5.0 (compatible; OpenCartInstaller/1.0)',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if (!$stream) {
            throw new RuntimeException('Unable to download OpenCart archive from ' . $url);
        }

        $archive = @fopen($archivePath, 'wb');
        if (!$archive) {
            fclose($stream);
            throw new RuntimeException('Unable to write temporary archive to ' . $archivePath);
        }

        stream_copy_to_stream($stream, $archive);
        fclose($stream);
        fclose($archive);

        try {
            $zip = new ZipArchive();
            if ($zip->open($archivePath) !== true) {
                throw new RuntimeException('Unable to open OpenCart ZIP archive');
            }
            if (!$zip->extractTo($extractDir)) {
                $zip->close();
                throw new RuntimeException('Unable to extract OpenCart ZIP archive');
            }
            $zip->close();
        } catch (\Throwable $e) {
            @unlink($archivePath);
            throw new RuntimeException('Unable to extract OpenCart archive: ' . $e->getMessage(), 0, $e);
        }

        // OpenCart 4.x has upload/ subdirectory
        $uploadDir = $extractDir . '/upload';
        if (!is_dir($uploadDir)) {
            // Try to find the upload directory in case structure changes
            $contents = scandir($extractDir);
            foreach ($contents as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $potentialUpload = $extractDir . '/' . $item . '/upload';
                if (is_dir($potentialUpload)) {
                    $uploadDir = $potentialUpload;
                    break;
                }
            }
            
            if (!is_dir($uploadDir)) {
                throw new RuntimeException('Unable to locate OpenCart upload directory in extracted archive');
            }
        }

        return [$archivePath, $uploadDir];
    }

    private function copyOpenCartFiles(string $sourceDir, string $destinationDir): void
    {
        if (!is_dir($sourceDir)) {
            throw new RuntimeException('OpenCart source directory missing: ' . $sourceDir);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destinationDir . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($targetPath) && !@mkdir($targetPath, 0755, true)) {
                    throw new RuntimeException('Unable to create directory: ' . $targetPath);
                }
            } else {
                if (!is_dir(dirname($targetPath)) && !@mkdir(dirname($targetPath), 0755, true)) {
                    throw new RuntimeException('Unable to create directory: ' . dirname($targetPath));
                }

                if (!@copy($item->getPathname(), $targetPath)) {
                    throw new RuntimeException('Unable to copy file: ' . $targetPath);
                }
                
                // Set appropriate permissions
                @chmod($targetPath, 0644);
            }
        }
        
        // Set writable permissions for OpenCart required directories
        $writableDirs = [
            $destinationDir . '/image',
            $destinationDir . '/system/storage/cache',
            $destinationDir . '/system/storage/logs',
            $destinationDir . '/system/storage/download',
            $destinationDir . '/system/storage/upload',
            $destinationDir . '/system/storage/modification',
        ];
        
        foreach ($writableDirs as $dir) {
            if (is_dir($dir)) {
                @chmod($dir, 0755);
                // Recursively set permissions
                $this->setPermissionsRecursive($dir, 0755, 0644);
            }
        }
    }

    private function setPermissionsRecursive(string $path, int $dirMode, int $fileMode): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @chmod($item->getPathname(), $dirMode);
            } else {
                @chmod($item->getPathname(), $fileMode);
            }
        }
    }

    private function prepareConfigFiles(string $root): void
    {
        // OpenCart CLI installer requires config-dist.php to be renamed to config.php before running
        $configFiles = [
            $root . '/config-dist.php' => $root . '/config.php',
            $root . '/admin/config-dist.php' => $root . '/admin/config.php',
        ];

        foreach ($configFiles as $source => $destination) {
            if (!is_file($source)) {
                throw new RuntimeException('OpenCart config template not found: ' . $source);
            }

            if (!@copy($source, $destination)) {
                throw new RuntimeException('Unable to copy config file from ' . $source . ' to ' . $destination);
            }

            @chmod($destination, 0644);
        }
    }

    private function prepareDatabase(string $serverName): array
    {
        $safe = preg_replace('/[^a-z0-9]/', '', strtolower($serverName)) ?: 'site';
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

        $database = sprintf('oc_%s_%s', substr($safe, 0, 8), $suffix);
        $username = sprintf('oc_%s_%s', substr($safe, 0, 6), substr($suffix, 0, 4));
        $username = substr($username, 0, 30);
        $password = bin2hex(random_bytes(10));

        $database = substr($database, 0, 63);

        $dbName = $this->quoteIdentifier($database);
        $userHost = "'{$username}'@'localhost'";

        $this->db->exec("CREATE DATABASE IF NOT EXISTS {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $this->db->exec("CREATE USER IF NOT EXISTS {$userHost} IDENTIFIED BY " . $this->db->quote($password));
        $this->db->exec("GRANT ALL PRIVILEGES ON {$dbName}.* TO {$userHost}");
        $this->db->exec('FLUSH PRIVILEGES');

        return [
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function runCliInstall(string $root, string $siteUrl, string $storeName, string $adminUser, string $adminEmail, string $adminPassword, array $dbCredentials): void
    {
        $dbHost = $this->dbConfig['host'] ?? '127.0.0.1';
        $dbPort = (int) ($this->dbConfig['port'] ?? 3306);
        if ($dbPort !== 3306) {
            $dbHost .= ':' . $dbPort;
        }

        // OpenCart 4.x uses install/cli_install.php
        $cliInstaller = $root . '/install/cli_install.php';
        
        if (!is_file($cliInstaller)) {
            throw new RuntimeException('OpenCart CLI installer not found at: ' . $cliInstaller);
        }

        $command = sprintf(
            'php %s install ' .
            '--db_hostname %s ' .
            '--db_username %s ' .
            '--db_password %s ' .
            '--db_database %s ' .
            '--db_driver mysqli ' .
            '--db_port %d ' .
            '--username %s ' .
            '--password %s ' .
            '--email %s ' .
            '--http_server %s',
            escapeshellarg($cliInstaller),
            escapeshellarg($dbHost),
            escapeshellarg($dbCredentials['username']),
            escapeshellarg($dbCredentials['password']),
            escapeshellarg($dbCredentials['database']),
            3306,
            escapeshellarg($adminUser),
            escapeshellarg($adminPassword),
            escapeshellarg($adminEmail),
            escapeshellarg($siteUrl)
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            throw new RuntimeException('OpenCart CLI installation failed (exit code ' . $returnCode . '): ' . $errorOutput);
        }

        // Verify config files were created
        $configFiles = [
            $root . '/config.php',
            $root . '/admin/config.php',
        ];

        foreach ($configFiles as $configFile) {
            if (!is_file($configFile)) {
                // Log the output for debugging
                error_log('OpenCart CLI output: ' . implode("\n", $output));
                throw new RuntimeException('OpenCart config file not created: ' . $configFile . '. CLI output: ' . implode("\n", $output));
            }
        }
    }

    private function cleanupInstaller(string $root): void
    {
        $installDir = $root . '/install';
        if (is_dir($installDir)) {
            $this->removeDirectory($installDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $item->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function cleanupFilesystem(string $archivePath, string $extractPath): void
    {
        if (is_file($archivePath)) {
            @unlink($archivePath);
        }

        if (is_dir($extractPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($extractPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $path = $item->getPathname();
                $item->isDir() ? @rmdir($path) : @unlink($path);
            }
            @rmdir($extractPath);
            $parent = dirname($extractPath);
            if ($parent && is_dir($parent)) {
                @rmdir($parent);
            }
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function linkDatabaseToSite(string $serverName, array $dbCredentials): void
    {
        try {
            $this->siteDatabaseService->linkDatabase(
                $serverName,
                $dbCredentials['database'],
                $dbCredentials['username'],
                'localhost',
                'OpenCart database (auto-linked)'
            );
        } catch (\Exception $e) {
            // Log but don't fail the installation if linking fails
            error_log("Warning: Failed to link database to site: " . $e->getMessage());
        }
    }
}
