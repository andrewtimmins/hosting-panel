<?php
namespace App\Services;

use App\Database\Connection;
use InvalidArgumentException;
use PDO;
use PharData;
use RuntimeException;

class WordPressInstaller
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
            throw new InvalidArgumentException('WordPress installation requested without install flag');
        }

        $serverName = $site['server_name'];
        $root = rtrim($site['root'], '/');

        if (!is_dir($root)) {
            throw new RuntimeException('Document root does not exist: ' . $root);
        }

        $downloadUrl = $defaults['download_url'] ?? 'https://wordpress.org/latest.tar.gz';
        $adminUser = $options['admin_username'] ?? $defaults['default_admin_username'] ?? 'admin';
        $adminPassword = $options['admin_password'] ?? $defaults['default_admin_password'] ?? 'ChangeMe123!';
        $adminEmail = $options['admin_email'] ?? $defaults['default_admin_email'] ?? 'admin@example.com';

        $adminUser = trim($adminUser);
        $adminPassword = trim($adminPassword);
        $adminEmail = trim($adminEmail);

        if ($adminUser === '' || $adminPassword === '' || $adminEmail === '') {
            throw new InvalidArgumentException('WordPress admin credentials cannot be empty');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid admin email for WordPress install');
        }

        $siteUrl = ($https ? 'https://' : 'http://') . $serverName;
        $siteTitleTemplate = $defaults['default_site_title'] ?? 'WordPress Site for {server_name}';
        $siteTitle = str_replace('{server_name}', $serverName, $siteTitleTemplate);

        $tablePrefix = $this->resolveTablePrefix($defaults['default_table_prefix'] ?? 'wp_', $serverName);

        [$archivePath, $extractPath] = $this->downloadAndExtract($downloadUrl);
        try {
            $this->copyWordPressFiles($extractPath, $root);
            $dbCredentials = $this->prepareDatabase($serverName);
            $this->writeConfig($root, $dbCredentials, $tablePrefix, $siteUrl, $defaults);
            $this->finaliseInstall($root, $siteUrl, $siteTitle, $adminUser, $adminEmail, $adminPassword);
            
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
            'login_url' => $siteUrl . '/wp-admin/',
            'db_name' => $dbCredentials['database'],
            'db_user' => $dbCredentials['username'],
            'db_password' => $dbCredentials['password'],
            'table_prefix' => $tablePrefix,
        ];
    }

    private function downloadAndExtract(string $url): array
    {
        $tempBase = sys_get_temp_dir() . '/wp_' . bin2hex(random_bytes(6));
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $isZip = str_ends_with($path, '.zip');
        $isTar = str_ends_with($path, '.tar.gz') || str_ends_with($path, '.tgz');

        if (!$isZip && !$isTar) {
            $isZip = true;
        }

        $archivePath = $tempBase . ($isZip ? '.zip' : '.tar.gz');
        $extractDir = $tempBase;

        if (!@mkdir($extractDir, 0755, true) && !is_dir($extractDir)) {
            throw new RuntimeException('Unable to create extraction directory: ' . $extractDir);
        }

        $stream = @fopen($url, 'rb');
        if (!$stream) {
            throw new RuntimeException('Unable to download WordPress archive from ' . $url);
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
            if ($isZip) {
                $zip = new \ZipArchive();
                if ($zip->open($archivePath) !== true) {
                    throw new RuntimeException('Unable to open WordPress ZIP archive');
                }
                if (!$zip->extractTo($extractDir)) {
                    $zip->close();
                    throw new RuntimeException('Unable to extract WordPress ZIP archive');
                }
                $zip->close();
            } else {
                $pharReadonly = ini_get('phar.readonly');
                if ($pharReadonly === '1') {
                    ini_set('phar.readonly', '0');
                }
                try {
                    $phar = new PharData($archivePath);
                    $phar->decompress();
                    $tarPath = substr($archivePath, 0, -3);
                    $tar = new PharData($tarPath);
                    $tar->extractTo($extractDir);
                    @unlink($tarPath);
                } finally {
                    if (($pharReadonly ?? null) === '1') {
                        ini_set('phar.readonly', '1');
                    }
                }
            }
        } catch (\Throwable $e) {
            @unlink($archivePath);
            throw new RuntimeException('Unable to extract WordPress archive: ' . $e->getMessage(), 0, $e);
        }

        return [$archivePath, $extractDir . '/wordpress'];
    }

    private function copyWordPressFiles(string $sourceDir, string $destinationDir): void
    {
        if (!is_dir($sourceDir)) {
            throw new RuntimeException('WordPress source directory missing: ' . $sourceDir);
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
            }
        }
    }

    private function prepareDatabase(string $serverName): array
    {
        $safe = preg_replace('/[^a-z0-9]/', '', strtolower($serverName)) ?: 'site';
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

        $database = sprintf('wp_%s_%s', substr($safe, 0, 8), $suffix);
        $username = sprintf('wp_%s_%s', substr($safe, 0, 6), substr($suffix, 0, 4));
        $username = substr($username, 0, 30);
        $password = bin2hex(random_bytes(10));

        $database = substr($database, 0, 63);

        $dbName = $this->quoteIdentifier($database);
        $userHost = "'{$username}'@'localhost'";

        $this->db->exec("CREATE DATABASE IF NOT EXISTS {$dbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->db->exec("CREATE USER IF NOT EXISTS {$userHost} IDENTIFIED BY " . $this->db->quote($password));
        $this->db->exec("GRANT ALL PRIVILEGES ON {$dbName}.* TO {$userHost}");
        $this->db->exec('FLUSH PRIVILEGES');

        return [
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function writeConfig(string $root, array $dbCredentials, string $tablePrefix, string $siteUrl, array $defaults): void
    {
        $samplePath = $root . '/wp-config-sample.php';
        $configPath = $root . '/wp-config.php';

        if (!is_file($samplePath)) {
            throw new RuntimeException('wp-config-sample.php not found in ' . $root);
        }

        $contents = file_get_contents($samplePath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read wp-config-sample.php');
        }

        $dbHost = $this->dbConfig['host'] ?? '127.0.0.1';
        if (!empty($this->dbConfig['port']) && (int) $this->dbConfig['port'] !== 3306) {
            $dbHost .= ':' . (int) $this->dbConfig['port'];
        }

        $replacements = [
            "define( 'DB_NAME', 'database_name_here' );" => "define( 'DB_NAME', '{$dbCredentials['database']}' );",
            "define( 'DB_USER', 'username_here' );" => "define( 'DB_USER', '{$dbCredentials['username']}' );",
            "define( 'DB_PASSWORD', 'password_here' );" => "define( 'DB_PASSWORD', '{$dbCredentials['password']}' );",
            "define( 'DB_HOST', 'localhost' );" => "define( 'DB_HOST', '{$dbHost}' );",
            "\$table_prefix = 'wp_';" => "\$table_prefix = '{$tablePrefix}';",
        ];

        $salts = $this->generateSalts();
        foreach ($salts as $key => $value) {
            $needle = "define( '{$key}',         'put your unique phrase here' );";
            $replacements[$needle] = "define( '{$key}',         '{$value}' );";
        }

        $contents = str_replace(array_keys($replacements), array_values($replacements), $contents);

        $append = [
            "define( 'WP_HOME', '{$siteUrl}' );",
            "define( 'WP_SITEURL', '{$siteUrl}' );",
            "define( 'FS_METHOD', 'direct' );",
        ];

    $contents .= PHP_EOL . implode(PHP_EOL, $append) . PHP_EOL;

        if (file_put_contents($configPath, $contents) === false) {
            throw new RuntimeException('Unable to write wp-config.php');
        }
    }

    private function finaliseInstall(string $root, string $siteUrl, string $siteTitle, string $adminUser, string $adminEmail, string $adminPassword): void
    {
        if (!defined('WP_INSTALLING')) {
            define('WP_INSTALLING', true);
        }

        $_SERVER['HTTP_HOST'] = parse_url($siteUrl, PHP_URL_HOST) ?? 'localhost';
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        if (str_starts_with($siteUrl, 'https://')) {
            $_SERVER['HTTPS'] = 'on';
        }

        require $root . '/wp-load.php';
        require_once $root . '/wp-admin/includes/upgrade.php';

        $result = wp_install($siteTitle, $adminUser, $adminEmail, true, '', $adminPassword, '', $siteUrl);
        if (is_wp_error($result)) {
            throw new RuntimeException('WordPress installation failed: ' . $result->get_error_message());
        }

        update_option('siteurl', $siteUrl);
        update_option('home', $siteUrl);
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

    private function generateSalts(): array
    {
        $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
        $salts = [];
        foreach ($keys as $key) {
            $salts[$key] = bin2hex(random_bytes(32));
        }
        return $salts;
    }

    private function resolveTablePrefix(string $defaultPrefix, string $serverName): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', $defaultPrefix) ?: 'wp_';
        if (!str_ends_with($prefix, '_')) {
            $prefix .= '_';
        }

        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($serverName)) ?: 'site';
        $suffix = substr($slug, 0, 6);
        if ($suffix === '') {
            $suffix = substr(bin2hex(random_bytes(3)), 0, 6);
        }

        return substr($prefix . $suffix . '_', 0, 16);
    }

    private function linkDatabaseToSite(string $serverName, array $dbCredentials): void
    {
        try {
            $this->siteDatabaseService->linkDatabase(
                $serverName,
                $dbCredentials['database'],
                $dbCredentials['username'],
                'localhost',
                'WordPress database (auto-linked)'
            );
        } catch (\Exception $e) {
            // Log but don't fail the installation if linking fails
            error_log("Warning: Failed to link database to site: " . $e->getMessage());
        }
    }
}
