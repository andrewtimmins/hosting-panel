<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Database\Connection;
use App\Database\Migrator;
use App\Services\DatabaseService;
use App\Services\LogService;
use App\Services\NginxConfigService;
use App\Services\OpenCartInstaller;
use App\Services\PowerDNSService;
use App\Services\SettingsService;
use App\Services\WordPressInstaller;
use App\Services\ServiceControlService;
use App\Services\SiteService;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody ?: '[]', true);

$action = $body['action'] ?? $_GET['action'] ?? null;
$payload = $body['payload'] ?? [];

if (!$action) {
    sendError('No action provided', 400);
}

try {
    $paths = $config['paths'];
    $mysqlConfig = $config['mysql'];
    $siteDefaults = $config['site_defaults'] ?? [];

    // Database connection for queue operations
    $db = Connection::get($mysqlConfig);

    (new Migrator($mysqlConfig))->ensure();

    $allowedCommands = $config['security']['allowed_commands'] ?? [];
    $nginxService = new NginxConfigService($paths, $siteDefaults, $allowedCommands);
    $settingsService = new SettingsService($mysqlConfig, $config['wordpress'] ?? [], $config['opencart'] ?? []);
    $siteDatabaseService = new App\Services\SiteDatabaseService($mysqlConfig);
    $wordpressInstaller = new WordPressInstaller($mysqlConfig, $siteDatabaseService);
    $opencartInstaller = new OpenCartInstaller($mysqlConfig, $siteDatabaseService);
    $siteService = new SiteService($mysqlConfig, $nginxService, $settingsService, $wordpressInstaller, $opencartInstaller);

    $allowedLogs = $config['security']['allowed_log_files'];
    $logService = new LogService($allowedLogs);

    $serviceControl = new ServiceControlService($mysqlConfig, $config['security']['allowed_commands']);

    $certbotService = new App\Services\CertbotService($allowedCommands);
    $configService = new App\Services\SiteConfigurationService($mysqlConfig, $nginxService, $certbotService);
    $dnsService = new PowerDNSService($mysqlConfig);
    $databaseService = new DatabaseService($mysqlConfig);
    
    // Backup services
    $backupDestinationService = new App\Services\BackupDestinationService($mysqlConfig);
    $backupService = new App\Services\BackupService($mysqlConfig, $allowedCommands);
    $backupSchedulerService = new App\Services\BackupSchedulerService($mysqlConfig, $backupService);

    $data = match ($action) {
        'list_sites' => $siteService->listSites(),
        'create_site' => $siteService->createSite(validateCreateSitePayload($payload)),
        'toggle_site' => $siteService->toggleSite(requiredString($payload, 'server_name')), 
        'delete_site' => tap(null, fn () => $siteService->deleteSite(requiredString($payload, 'server_name'))),
        'get_site_config' => $configService->getConfiguration(requiredString($payload, 'server_name')),
        'update_site_config' => $configService->updateConfiguration(requiredString($payload, 'server_name'), $payload['config'] ?? []),
        'tail_log' => $logService->tail(requiredString($payload, 'log_file'), (int) ($payload['lines'] ?? 200)),
        'get_log_files' => $logService->getAvailableLogFiles($siteService->listSites()),
        'run_command' => $serviceControl->run(requiredString($payload, 'command')), 
        'service_history' => $serviceControl->history(),
        'get_wordpress_defaults' => $settingsService->getWordPressDefaults(),
        'update_wordpress_defaults' => $settingsService->updateWordPressDefaults(validateWordPressDefaultsPayload($payload)),
        'get_opencart_defaults' => $settingsService->getOpenCartDefaults(),
        'update_opencart_defaults' => $settingsService->updateOpenCartDefaults(validateOpenCartDefaultsPayload($payload)),
        
        // PowerDNS endpoints
        'list_domains' => $dnsService->listDomains(),
        'create_domain' => $dnsService->createDomain(validateDomainPayload($payload)),
        'delete_domain' => tap(null, fn () => $dnsService->deleteDomain(requiredString($payload, 'domain_name'))),
        'list_records' => $dnsService->getDomainRecords(requiredString($payload, 'domain_name')),
        'create_record' => $dnsService->createRecord(requiredString($payload, 'domain_name'), validateRecordPayload($payload)),
        'update_record' => $dnsService->updateRecord((int) requiredString($payload, 'record_id'), validateRecordPayload($payload)),
        'delete_record' => tap(null, fn () => $dnsService->deleteRecord((int) requiredString($payload, 'record_id'))),
        
        // Database management endpoints
        'list_databases' => $databaseService->listDatabases(),
        'create_database' => $databaseService->createDatabase(
            requiredString($payload, 'name'),
            $payload['charset'] ?? 'utf8mb4',
            $payload['collation'] ?? 'utf8mb4_unicode_ci'
        ),
        'drop_database' => tap(null, fn () => $databaseService->dropDatabase(requiredString($payload, 'name'))),
        'list_database_users' => $databaseService->listUsers(),
        'create_database_user' => $databaseService->createUser(
            requiredString($payload, 'username'),
            requiredString($payload, 'password'),
            $payload['host'] ?? 'localhost',
            $payload['database'] ?? null
        ),
        'drop_database_user' => tap(null, fn () => $databaseService->dropUser(
            requiredString($payload, 'username'),
            requiredString($payload, 'host')
        )),
        'grant_database_permissions' => tap(null, fn () => $databaseService->grantPermissions(
            requiredString($payload, 'username'),
            requiredString($payload, 'host'),
            requiredString($payload, 'database'),
            $payload['permissions'] ?? []
        )),
        
        // Site-Database linking endpoints
        'link_database_to_site' => $siteDatabaseService->linkDatabase(
            requiredString($payload, 'server_name'),
            requiredString($payload, 'database_name'),
            $payload['database_user'] ?? null,
            $payload['database_host'] ?? 'localhost',
            $payload['description'] ?? null
        ),
        'unlink_database_from_site' => tap(null, fn () => $siteDatabaseService->unlinkDatabase(
            (int) requiredString($payload, 'link_id')
        )),
        'get_site_databases' => $siteDatabaseService->getSiteDatabases(
            requiredString($payload, 'server_name')
        ),
        'get_available_databases' => $siteDatabaseService->getAvailableDatabases(
            requiredString($payload, 'server_name')
        ),
        'update_database_link_description' => $siteDatabaseService->updateLinkDescription(
            (int) requiredString($payload, 'link_id'),
            requiredString($payload, 'description')
        ),
        
        // Backup management endpoints
        'list_backups' => $backupService->listBackups($payload['type'] ?? null, $payload['limit'] ?? 100),
        'create_backup' => (function() use ($db, $payload) {
            // Queue the backup instead of executing it immediately
            $stmt = $db->prepare("
                INSERT INTO backup_queue (type, items, destination_id, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                requiredString($payload, 'type'),
                json_encode($payload['items'] ?? []),
                (int) requiredString($payload, 'destination_id')
            ]);
            $queueId = (int) $db->lastInsertId();
            
            return [
                'queue_id' => $queueId,
                'status' => 'queued',
                'message' => 'Backup queued successfully'
            ];
        })(),
        'get_backup_queue_status' => (function() use ($db, $payload) {
            $stmt = $db->prepare("SELECT * FROM backup_queue WHERE id = ?");
            $stmt->execute([(int) requiredString($payload, 'queue_id')]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: throw new \Exception('Queue item not found');
        })(),
        'get_backup_progress' => $backupService->getBackupProgress(
            (int) requiredString($payload, 'history_id')
        ),
        'restore_backup' => $backupService->restoreBackup(
            (int) requiredString($payload, 'backup_id'),
            $payload['items'] ?? []
        ),
        'delete_backup' => tap(null, fn () => $backupService->deleteBackup((int) requiredString($payload, 'backup_id'))),
        
        // Backup job endpoints
        'list_backup_jobs' => $backupSchedulerService->listJobs(),
        'create_backup_job' => $backupSchedulerService->createJob(
            requiredString($payload, 'name'),
            requiredString($payload, 'backup_type'),
            $payload['items'] ?? [],
            requiredString($payload, 'schedule_cron'),
            (int) requiredString($payload, 'destination_id'),
            $payload['retention_days'] ?? 30,
            $payload['description'] ?? null
        ),
        'update_backup_job' => $backupSchedulerService->updateJob(
            (int) requiredString($payload, 'job_id'),
            $payload
        ),
        'delete_backup_job' => tap(null, fn () => $backupSchedulerService->deleteJob((int) requiredString($payload, 'job_id'))),
        'execute_backup_job' => $backupSchedulerService->executeJob((int) requiredString($payload, 'job_id')),
        'run_due_jobs' => $backupSchedulerService->runDueJobs(),
        
        // Backup destination endpoints
        'list_backup_destinations' => $backupDestinationService->listDestinations(),
        'create_backup_destination' => $backupDestinationService->createDestination(
            requiredString($payload, 'name'),
            requiredString($payload, 'type'),
            $payload['config'] ?? [],
            $payload['is_default'] ?? false
        ),
        'update_backup_destination' => $backupDestinationService->updateDestination(
            (int) requiredString($payload, 'destination_id'),
            $payload
        ),
        'delete_backup_destination' => tap(null, fn () => $backupDestinationService->deleteDestination((int) requiredString($payload, 'destination_id'))),
        'test_backup_destination' => $backupDestinationService->testConnection((int) requiredString($payload, 'destination_id')),
        
        // System monitoring endpoints
        'get_service_status' => getServiceStatus($config),
        'get_system_stats' => getSystemStats(),
        
        // Authentication endpoints
        'login' => handleLogin($config['mysql'], $payload),
        'logout' => handleLogout($config['mysql']),
        'setup_admin' => handleSetupAdmin($config['mysql'], $payload),
        
        // User management endpoints (admin only)
        'list_users' => handleListUsers($config['mysql']),
        'create_user' => handleCreateUser($config['mysql'], $payload),
        'update_user' => handleUpdateUser($config['mysql'], $payload),
        'delete_user' => handleDeleteUser($config['mysql'], $payload),
        
        // Profile management
        'update_profile' => handleUpdateProfile($config['mysql'], $payload),
        'change_password' => handleChangePassword($config['mysql'], $payload),
        
        default => throw new \RuntimeException('Unknown action: ' . $action),
    };

    sendResponse($data);
} catch (\Throwable $e) {
    error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendError($e->getMessage(), 500);
}

function sendResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => true,
        'data' => $data,
    ], JSON_THROW_ON_ERROR);
    exit;
}

function sendError(string $message, int $status = 400): never
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ], JSON_THROW_ON_ERROR);
    exit;
}

function requiredString(array $payload, string $key): string
{
    if (!isset($payload[$key]) || trim((string) $payload[$key]) === '') {
        throw new \InvalidArgumentException(sprintf('Missing field: %s', $key));
    }
    return trim((string) $payload[$key]);
}

function validateCreateSitePayload(array $payload): array
{
    $serverName = requiredString($payload, 'server_name');
    $https = (bool) ($payload['https'] ?? false);
    $wordpress = $payload['wordpress'] ?? [];

    if (!preg_match('/^[a-z0-9.-]+$/', $serverName)) {
        throw new \InvalidArgumentException('Server name may only contain letters, numbers, dots, and hyphens');
    }

    if (str_starts_with($serverName, '.') || str_ends_with($serverName, '.')) {
        throw new \InvalidArgumentException('Server name must not start or end with a dot');
    }

    if (strlen($serverName) < 3) {
        throw new \InvalidArgumentException('Server name is too short');
    }

    return [
        'server_name' => strtolower($serverName),
        'https' => $https,
        'wordpress' => validateWordPressInstallPayload($wordpress),
        'opencart' => validateOpenCartInstallPayload($payload['opencart'] ?? []),
    ];
}

function validateDomainPayload(array $payload): array
{
    $name = requiredString($payload, 'name');
    $type = strtoupper($payload['type'] ?? 'NATIVE');
    
    if (!preg_match('/^[a-z0-9.-]+$/', $name)) {
        throw new \InvalidArgumentException('Domain name may only contain letters, numbers, dots, and hyphens');
    }
    
    if (str_starts_with($name, '.') || str_ends_with($name, '.') || str_contains($name, '..')) {
        throw new \InvalidArgumentException('Invalid domain name format');
    }
    
    if (!in_array($type, ['NATIVE', 'MASTER', 'SLAVE'], true)) {
        throw new \InvalidArgumentException('Invalid domain type');
    }
    
    $result = [
        'name' => strtolower($name),
        'type' => $type,
    ];
    
    if (isset($payload['master'])) {
        $result['master'] = trim($payload['master']);
    }
    
    if (isset($payload['account'])) {
        $result['account'] = trim($payload['account']);
    }
    
    return $result;
}

function validateRecordPayload(array $payload): array
{
    $type = strtoupper(requiredString($payload, 'type'));
    $content = requiredString($payload, 'content');
    
    $validTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'PTR', 'SOA'];
    if (!in_array($type, $validTypes, true)) {
        throw new \InvalidArgumentException('Invalid record type');
    }
    
    $result = [
        'type' => $type,
        'content' => trim($content),
    ];
    
    if (isset($payload['name'])) {
        $result['name'] = strtolower(trim($payload['name']));
    }
    
    if (isset($payload['ttl'])) {
        $ttl = (int) $payload['ttl'];
        if ($ttl < 1) {
            throw new \InvalidArgumentException('TTL must be positive');
        }
        $result['ttl'] = $ttl;
    }
    
    if (isset($payload['prio'])) {
        $result['prio'] = (int) $payload['prio'];
    }
    
    if (isset($payload['disabled'])) {
        $result['disabled'] = (bool) $payload['disabled'];
    }
    
    return $result;
}

function validateWordPressDefaultsPayload(array $payload): array
{
    $result = [];
    foreach ([
        'download_url',
        'default_admin_username',
        'default_admin_password',
        'default_admin_email',
        'default_site_title',
        'default_table_prefix',
    ] as $key) {
        if (array_key_exists($key, $payload)) {
            $result[$key] = is_string($payload[$key]) ? trim($payload[$key]) : $payload[$key];
        }
    }

    return $result;
}

function validateOpenCartDefaultsPayload(array $payload): array
{
    $result = [];
    foreach ([
        'download_url',
        'default_admin_username',
        'default_admin_password',
        'default_admin_email',
        'default_store_name',
    ] as $key) {
        if (array_key_exists($key, $payload)) {
            $result[$key] = is_string($payload[$key]) ? trim($payload[$key]) : $payload[$key];
        }
    }

    return $result;
}

function validateWordPressInstallPayload(mixed $payload): array
{
    if (!is_array($payload)) {
        return ['install' => false];
    }

    $install = (bool) ($payload['install'] ?? false);

    if ($install === false) {
        return ['install' => false];
    }

    $adminUser = trim((string) ($payload['admin_username'] ?? ''));
    $adminPassword = trim((string) ($payload['admin_password'] ?? ''));
    $adminEmail = trim((string) ($payload['admin_email'] ?? ''));

    if ($adminUser === '' || $adminPassword === '' || $adminEmail === '') {
        throw new \InvalidArgumentException('WordPress admin username, password, and email are required when installing WordPress');
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('WordPress admin email must be a valid email address');
    }

    return [
        'install' => true,
        'admin_username' => $adminUser,
        'admin_password' => $adminPassword,
        'admin_email' => $adminEmail,
    ];
}

function validateOpenCartInstallPayload(mixed $payload): array
{
    if (!is_array($payload)) {
        return ['install' => false];
    }

    $install = (bool) ($payload['install'] ?? false);

    if ($install === false) {
        return ['install' => false];
    }

    $adminUser = trim((string) ($payload['admin_username'] ?? ''));
    $adminPassword = trim((string) ($payload['admin_password'] ?? ''));
    $adminEmail = trim((string) ($payload['admin_email'] ?? ''));

    if ($adminUser === '' || $adminPassword === '' || $adminEmail === '') {
        throw new \InvalidArgumentException('OpenCart admin username, password, and email are required when installing OpenCart');
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('OpenCart admin email must be a valid email address');
    }

    return [
        'install' => true,
        'admin_username' => $adminUser,
        'admin_password' => $adminPassword,
        'admin_email' => $adminEmail,
    ];
}

function tap(mixed $value, callable $callback): mixed
{
    $callback($value);
    return $value;
}

function getServiceStatus(array $config): array
{
    $services = [
        'nginx' => 'nginx',
        'php' => $config['paths']['php_fpm_service'] ?? 'php8.3-fpm',
        'mysql' => 'mysql',
        'powerdns' => 'pdns'
    ];
    
    $status = [];
    
    foreach ($services as $key => $serviceName) {
        // Check if service is running using systemctl
        $output = [];
        $returnCode = 0;
        exec("systemctl is-active {$serviceName} 2>/dev/null", $output, $returnCode);
        
        $status[$key] = ($returnCode === 0 && isset($output[0]) && $output[0] === 'active') ? 'running' : 'stopped';
    }
    
    return $status;
}

function getSystemStats(): array
{
    $stats = [
        'cpu' => getCpuUsage(),
        'memory' => getMemoryUsage(),
        'network' => getNetworkStats()
    ];
    
    return $stats;
}

function getCpuUsage(): float
{
    $load = sys_getloadavg();
    if ($load !== false) {
        // Convert load average to percentage (approximate)
        // Load average of 1.0 = 100% on single core system
        $cpuCount = (int) shell_exec('nproc') ?: 1;
        return min(100, ($load[0] / $cpuCount) * 100);
    }
    
    // Fallback: try to read from /proc/stat
    $prevTotal = 0;
    $prevIdle = 0;
    
    if (file_exists('/proc/stat')) {
        $statData = file_get_contents('/proc/stat');
        if ($statData) {
            $lines = explode("\n", $statData);
            $cpuLine = $lines[0];
            $values = preg_split('/\s+/', $cpuLine);
            
            if (count($values) >= 5) {
                $idle = (int) $values[4];
                $total = array_sum(array_slice(array_map('intval', $values), 1, 7));
                
                // Simple calculation - in real implementation you'd want to cache previous values
                $usage = 100 - (($idle / $total) * 100);
                return max(0, min(100, $usage));
            }
        }
    }
    
    return 0.0;
}

function getMemoryUsage(): float
{
    if (file_exists('/proc/meminfo')) {
        $memInfo = file_get_contents('/proc/meminfo');
        if ($memInfo) {
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMatches);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $availableMatches);
            
            if (!empty($totalMatches[1]) && !empty($availableMatches[1])) {
                $total = (int) $totalMatches[1];
                $available = (int) $availableMatches[1];
                $used = $total - $available;
                
                return ($used / $total) * 100;
            }
        }
    }
    
    return 0.0;
}

function getNetworkStats(): array
{
    $stats = ['upload' => 0, 'download' => 0];
    
    if (file_exists('/proc/net/dev')) {
        $netData = file_get_contents('/proc/net/dev');
        if ($netData) {
            $lines = explode("\n", $netData);
            $totalRx = 0;
            $totalTx = 0;
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    $parts = explode(':', $line);
                    $interface = trim($parts[0]);
                    
                    // Skip loopback and virtual interfaces
                    if ($interface === 'lo' || strpos($interface, 'docker') !== false || strpos($interface, 'veth') !== false) {
                        continue;
                    }
                    
                    $stats_part = preg_split('/\s+/', trim($parts[1]));
                    if (count($stats_part) >= 9) {
                        $totalRx += (int) $stats_part[0]; // bytes received
                        $totalTx += (int) $stats_part[8]; // bytes transmitted
                    }
                }
            }
            
            // This gives total bytes since boot - in a real implementation,
            // you'd want to calculate the rate by storing previous values
            // For now, we'll simulate some activity
            $stats['download'] = $totalRx > 0 ? rand(1000, 50000) : 0; // Simulate current rate
            $stats['upload'] = $totalTx > 0 ? rand(100, 10000) : 0;   // Simulate current rate
        }
    }
    
    return $stats;
}

// Authentication Functions
function handleLogin(array $dbConfig, array $payload): array
{
    $authService = new App\Services\AuthService($dbConfig);
    
    $username = trim($payload['username'] ?? '');
    $password = $payload['password'] ?? '';
    
    if (!$username || !$password) {
        throw new InvalidArgumentException('Username and password are required');
    }
    
    return $authService->login($username, $password);
}

function handleLogout(array $dbConfig): array
{
    $sessionId = $_COOKIE['admin_session'] ?? null;
    if ($sessionId) {
        $authService = new App\Services\AuthService($dbConfig);
        $authService->logout($sessionId);
        
        setcookie('admin_session', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']),
            'samesite' => 'Strict'
        ]);
    }
    
    return ['success' => true];
}

function requireAuth(array $dbConfig): array
{
    $authService = new App\Services\AuthService($dbConfig);
    $user = $authService->getCurrentUser();
    
    if (!$user) {
        throw new RuntimeException('Authentication required');
    }
    
    return $user;
}

function requireAdmin(array $dbConfig): array
{
    $user = requireAuth($dbConfig);
    
    if ($user['role'] !== 'admin') {
        throw new RuntimeException('Admin privileges required');
    }
    
    return $user;
}

// User Management Functions
function handleListUsers(array $dbConfig): array
{
    // Temporarily disable auth for debugging
    // requireAdmin($dbConfig);
    
    $userService = new App\Services\UserService($dbConfig);
    return $userService->listUsers();
}

function handleCreateUser(array $dbConfig, array $payload): array
{
    // Temporarily disable auth for debugging
    // requireAdmin($dbConfig);
    
    $userService = new App\Services\UserService($dbConfig);
    return $userService->createUser($payload);
}

function handleUpdateUser(array $dbConfig, array $payload): array
{
    requireAdmin($dbConfig);
    
    $userId = (int) ($payload['user_id'] ?? 0);
    if (!$userId) {
        throw new InvalidArgumentException('User ID is required');
    }
    
    $userService = new App\Services\UserService($dbConfig);
    return $userService->updateUser($userId, $payload);
}

function handleDeleteUser(array $dbConfig, array $payload): void
{
    // Temporarily disable auth for debugging
    // requireAdmin($dbConfig);
    
    $userId = (int) ($payload['user_id'] ?? 0);
    if (!$userId) {
        throw new InvalidArgumentException('User ID is required');
    }
    
    $userService = new App\Services\UserService($dbConfig);
    $userService->deleteUser($userId);
}

// Profile Management Functions
function handleUpdateProfile(array $dbConfig, array $payload): array
{
    $currentUser = requireAuth($dbConfig);
    
    $userService = new App\Services\UserService($dbConfig);
    return $userService->updateUser($currentUser['id'], $payload);
}

function handleChangePassword(array $dbConfig, array $payload): array
{
    $currentUser = requireAuth($dbConfig);
    
    $currentPassword = $payload['current_password'] ?? '';
    $newPassword = $payload['new_password'] ?? '';
    
    if (!$currentPassword || !$newPassword) {
        throw new InvalidArgumentException('Current password and new password are required');
    }
    
    $userService = new App\Services\UserService($dbConfig);
    $userService->changePassword($currentUser['id'], $currentPassword, $newPassword);
    
    return ['success' => true];
}

function handleSetupAdmin(array $dbConfig, array $payload): array
{
    $userService = new App\Services\UserService($dbConfig);
    
    // Check if any admin users exist
    $users = $userService->listUsers();
    $adminExists = false;
    foreach ($users as $user) {
        if ($user['role'] === 'admin') {
            $adminExists = true;
            break;
        }
    }
    
    if ($adminExists) {
        throw new RuntimeException('Admin user already exists');
    }
    
    // Create default admin
    $adminData = [
        'username' => 'admin',
        'email' => 'admin@localhost',
        'password' => 'admin123',
        'full_name' => 'System Administrator',
        'role' => 'admin',
        'is_active' => true
    ];
    
    return $userService->createUser($adminData);
}
