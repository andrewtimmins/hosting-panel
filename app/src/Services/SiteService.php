<?php
namespace App\Services;

use App\Database\Connection;
use RuntimeException;
use PDO;

class SiteService
{
    private PDO $db;
    private NginxConfigService $nginxConfig;
    private SettingsService $settingsService;
    private ?WordPressInstaller $wordpressInstaller;
    private ?OpenCartInstaller $opencartInstaller;

    public function __construct(array $config, NginxConfigService $nginxConfig, SettingsService $settingsService, ?WordPressInstaller $wordpressInstaller = null, ?OpenCartInstaller $opencartInstaller = null)
    {
        $this->db = Connection::get($config);
        $this->nginxConfig = $nginxConfig;
        $this->settingsService = $settingsService;
        $this->wordpressInstaller = $wordpressInstaller;
        $this->opencartInstaller = $opencartInstaller;
    }

    public function listSites(): array
    {
        $sites = $this->nginxConfig->listSites();
        foreach ($sites as &$site) {
            $extra = $this->fetchSiteMeta($site['server_name']);
            $site['created_at'] = $extra['created_at'] ?? null;
            $site['updated_at'] = $extra['updated_at'] ?? null;
        }
        return $sites;
    }

    public function createSite(array $data): array
    {
        $site = $this->nginxConfig->createSite($data);
        $this->upsertSiteMeta($site);

        // Reload nginx to apply the new configuration
        try {
            $this->nginxConfig->reload();
        } catch (\Exception $e) {
            // Log but don't fail - site was created successfully
            error_log('Warning: Failed to reload nginx after creating site: ' . $e->getMessage());
        }

        $site['wordpress'] = null;
        $site['opencart'] = null;
        
        if (($data['wordpress']['install'] ?? false) === true) {
            if (!$this->wordpressInstaller) {
                throw new RuntimeException('WordPress installer not configured');
            }

            $defaults = $this->settingsService->getWordPressDefaults();
            $installOptions = [
                'install' => true,
                'admin_username' => $data['wordpress']['admin_username'] ?? $defaults['default_admin_username'] ?? null,
                'admin_password' => $data['wordpress']['admin_password'] ?? $defaults['default_admin_password'] ?? null,
                'admin_email' => $data['wordpress']['admin_email'] ?? $defaults['default_admin_email'] ?? null,
            ];

            $site['wordpress'] = $this->wordpressInstaller->install($site, $installOptions, $defaults, (bool) ($data['https'] ?? false));
        }

        if (($data['opencart']['install'] ?? false) === true) {
            if (!$this->opencartInstaller) {
                throw new RuntimeException('OpenCart installer not configured');
            }

            $defaults = $this->settingsService->getOpenCartDefaults();
            $installOptions = [
                'install' => true,
                'admin_username' => $data['opencart']['admin_username'] ?? $defaults['default_admin_username'] ?? null,
                'admin_password' => $data['opencart']['admin_password'] ?? $defaults['default_admin_password'] ?? null,
                'admin_email' => $data['opencart']['admin_email'] ?? $defaults['default_admin_email'] ?? null,
            ];

            $site['opencart'] = $this->opencartInstaller->install($site, $installOptions, $defaults, (bool) ($data['https'] ?? false));
        }

        return $site;
    }

    public function toggleSite(string $serverName): array
    {
        $site = $this->nginxConfig->toggleSite($serverName);
        $this->upsertSiteMeta($site);
        return $site;
    }

    public function deleteSite(string $serverName): void
    {
        $this->nginxConfig->deleteSite($serverName);
        $stmt = $this->db->prepare('DELETE FROM sites WHERE server_name = :server_name');
        $stmt->execute([':server_name' => $serverName]);
    }

    private function fetchSiteMeta(string $serverName): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sites WHERE server_name = :server_name LIMIT 1');
        $stmt->execute([':server_name' => $serverName]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function upsertSiteMeta(array $site): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sites (server_name, root, listen, enabled, created_at, updated_at)
             VALUES (:server_name, :root, :listen, :enabled, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                root = VALUES(root),
                listen = VALUES(listen),
                enabled = VALUES(enabled),
                updated_at = NOW()'
        );

        $stmt->execute([
            ':server_name' => $site['server_name'],
            ':root' => $site['root'],
            ':listen' => is_array($site['listen_directives'] ?? null)
                ? implode(', ', $site['listen_directives'])
                : ($site['listen'] ?? ''),
            ':enabled' => $site['enabled'] ? 1 : 0,
        ]);
    }
}
