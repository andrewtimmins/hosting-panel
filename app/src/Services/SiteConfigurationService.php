<?php
namespace App\Services;

use PDO;
use RuntimeException;
use App\Database\Connection;

class SiteConfigurationService
{
    private PDO $db;
    private NginxConfigService $nginxService;
    private ?CertbotService $certbotService = null;

    public function __construct(array $dbConfig, NginxConfigService $nginxService, ?CertbotService $certbotService = null)
    {
        $this->db = Connection::get($dbConfig);
        $this->nginxService = $nginxService;
        $this->certbotService = $certbotService;
    }

    public function getConfiguration(string $serverName): array
    {
        // First try to get from database
        $stmt = $this->db->prepare("SELECT * FROM site_configurations WHERE server_name = ?");
        $stmt->execute([$serverName]);
        $dbConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dbConfig) {
            return $this->parseDbConfiguration($dbConfig);
        }
        
        // Fallback: parse existing nginx config
        $sites = $this->nginxService->listSites();
        $site = null;
        foreach ($sites as $s) {
            if ($s['server_name'] === $serverName) {
                $site = $s;
                break;
            }
        }
        
        if (!$site) {
            throw new RuntimeException("Site not found: $serverName");
        }
        
        return $this->convertSiteToConfiguration($site);
    }

    public function updateConfiguration(string $serverName, array $config): array
    {
        $this->validateConfiguration($config);
        
        // Handle Let's Encrypt certificate if requested
        if ($config['listen']['https_enabled'] && 
            $config['ssl']['certificate_type'] === 'letsencrypt' &&
            $this->certbotService) {
            
            $this->obtainLetsEncryptCertificate($serverName, $config['ssl']);
            
            // Update SSL paths to Let's Encrypt certificate
            $certPaths = $this->certbotService->getCertificatePaths($serverName);
            $config['ssl']['ssl_certificate'] = $certPaths['certificate'];
            $config['ssl']['ssl_certificate_key'] = $certPaths['certificate_key'];
        }
        
        // Store in database
        $this->saveConfiguration($serverName, $config);
        
        // Generate and write nginx config
        $nginxConfig = $this->generateNginxConfig($serverName, $config);
        $this->nginxService->updateSiteConfig($serverName, $nginxConfig);
        
        return $this->getConfiguration($serverName);
    }
    
    private function obtainLetsEncryptCertificate(string $domain, array $sslConfig): void
    {
        if (!$this->certbotService) {
            throw new RuntimeException('Certbot service is not configured');
        }
        
        // Check if certificate already exists
        if ($this->certbotService->certificateExists($domain)) {
            // Certificate exists, no need to obtain a new one
            return;
        }
        
        $email = $sslConfig['letsencrypt_email'] ?? null;
        $agreeTos = $sslConfig['letsencrypt_agree_tos'] ?? false;
        $extraDomains = [];
        
        if (!empty($sslConfig['letsencrypt_extra_domains'])) {
            $extraDomains = array_filter(
                array_map('trim', explode(' ', $sslConfig['letsencrypt_extra_domains']))
            );
        }
        
        if (!$email) {
            throw new RuntimeException('Email is required for Let\'s Encrypt');
        }
        
        if (!$agreeTos) {
            throw new RuntimeException('You must agree to the Let\'s Encrypt Terms of Service');
        }
        
        // Try webroot method first, fallback to nginx method
        try {
            $this->certbotService->obtainCertificateNginx($domain, $email, $extraDomains, $agreeTos);
        } catch (\Exception $e) {
            // If nginx method fails, the error will be thrown
            throw new RuntimeException(
                'Failed to obtain Let\'s Encrypt certificate: ' . $e->getMessage()
            );
        }
    }

    private function parseDbConfiguration(array $dbConfig): array
    {
        return [
            'basic' => [
                'server_name' => $dbConfig['server_name'],
                'document_root' => $dbConfig['document_root'],
                'index_files' => json_decode($dbConfig['index_files'] ?? '[]', true) ?: ['index.php', 'index.html', 'index.htm'],
                'enabled' => (bool) $dbConfig['enabled'],
            ],
            'listen' => [
                'http_listen' => json_decode($dbConfig['http_listen'] ?? '[]', true) ?: ['80'],
                'https_listen' => json_decode($dbConfig['https_listen'] ?? '[]', true) ?: ['443 ssl http2'],
                'https_enabled' => (bool) $dbConfig['https_enabled'],
                'redirect_http_to_https' => (bool) $dbConfig['redirect_http_to_https'],
            ],
            'ssl' => [
                'certificate_type' => $dbConfig['certificate_type'] ?? 'letsencrypt',
                'letsencrypt_email' => $dbConfig['letsencrypt_email'] ?? '',
                'letsencrypt_agree_tos' => (bool) ($dbConfig['letsencrypt_agree_tos'] ?? false),
                'letsencrypt_extra_domains' => $dbConfig['letsencrypt_extra_domains'] ?? '',
                'ssl_certificate' => $dbConfig['ssl_certificate'],
                'ssl_certificate_key' => $dbConfig['ssl_certificate_key'],
                'ssl_protocols' => $dbConfig['ssl_protocols'] ?: 'TLSv1.2 TLSv1.3',
                'ssl_ciphers' => $dbConfig['ssl_ciphers'],
                'ssl_prefer_server_ciphers' => (bool) ($dbConfig['ssl_prefer_server_ciphers'] ?? true),
                'ssl_extra_includes' => json_decode($dbConfig['ssl_extra_includes'] ?? '[]', true) ?: [],
            ],
            'php' => [
                'php_enabled' => (bool) $dbConfig['php_enabled'],
                'php_fastcgi_pass' => $dbConfig['php_fastcgi_pass'] ?: 'unix:/run/php/php8.3-fpm.sock',
                'php_fastcgi_index' => $dbConfig['php_fastcgi_index'] ?: 'index.php',
                'php_fastcgi_read_timeout' => $dbConfig['php_fastcgi_read_timeout'] ?: '60',
            ],
            'logging' => [
                'access_log' => $dbConfig['access_log'] ?: $dbConfig['document_root'] . '/logs/access.log',
                'error_log' => $dbConfig['error_log'] ?: $dbConfig['document_root'] . '/logs/error.log',
                'log_format' => $dbConfig['log_format'] ?: 'combined',
                'error_log_level' => $dbConfig['error_log_level'] ?: 'error',
            ],
            'performance' => [
                'client_max_body_size' => $dbConfig['client_max_body_size'] ?: '1M',
                'client_body_buffer_size' => $dbConfig['client_body_buffer_size'] ?: '128k',
                'fastcgi_cache_enabled' => (bool) ($dbConfig['fastcgi_cache_enabled'] ?? false),
                'fastcgi_cache_path' => $dbConfig['fastcgi_cache_path'] ?: '/var/cache/nginx/fastcgi',
                'fastcgi_cache_valid' => $dbConfig['fastcgi_cache_valid'] ?: '60m',
                'fastcgi_cache_key' => $dbConfig['fastcgi_cache_key'] ?: '$scheme$request_method$host$request_uri',
                'fastcgi_cache_bypass' => $dbConfig['fastcgi_cache_bypass'] ?: '$cookie_PHPSESSID $http_authorization',
                'fastcgi_no_cache' => $dbConfig['fastcgi_no_cache'] ?: '$cookie_PHPSESSID $http_authorization',
                'fastcgi_cache_use_stale' => (bool) ($dbConfig['fastcgi_cache_use_stale'] ?? false),
                'browser_cache_enabled' => (bool) ($dbConfig['browser_cache_enabled'] ?? false),
                'cache_css_js' => $dbConfig['cache_css_js'] ?: '30d',
                'cache_images' => $dbConfig['cache_images'] ?: '90d',
                'cache_fonts' => $dbConfig['cache_fonts'] ?: '1y',
                'cache_media' => $dbConfig['cache_media'] ?: '1y',
                'gzip_enabled' => (bool) $dbConfig['gzip_enabled'],
                'gzip_types' => $dbConfig['gzip_types'] ?: 'text/plain text/css application/json application/javascript text/xml application/xml',
                'gzip_comp_level' => $dbConfig['gzip_comp_level'] ?: '6',
                'gzip_min_length' => $dbConfig['gzip_min_length'] ?: '256',
            ],
            'security' => [
                'server_tokens' => (bool) $dbConfig['server_tokens'],
                'x_frame_options' => $dbConfig['x_frame_options'] ?: 'SAMEORIGIN',
                'x_content_type_options' => (bool) ($dbConfig['x_content_type_options'] ?? true),
                'x_xss_protection' => (bool) ($dbConfig['x_xss_protection'] ?? true),
                'referrer_policy' => $dbConfig['referrer_policy'] ?: 'strict-origin-when-cross-origin',
            ],
            'locations' => json_decode($dbConfig['custom_locations'] ?? '[]', true) ?: [],
            'advanced' => [
                'custom_directives' => $dbConfig['custom_directives'] ?: '',
            ],
        ];
    }

    private function convertSiteToConfiguration(array $site): array
    {
        return [
            'basic' => [
                'server_name' => $site['server_name'],
                'document_root' => $site['root'],
                'index_files' => $site['index'] ?? ['index.php', 'index.html', 'index.htm'],
                'enabled' => $site['enabled'],
            ],
            'listen' => [
                'http_listen' => ['80'],
                'https_listen' => ['443 ssl http2'],
                'https_enabled' => !empty($site['ssl_certificate']),
                'redirect_http_to_https' => false,
            ],
            'ssl' => [
                'ssl_certificate' => $site['ssl_certificate'] ?? '',
                'ssl_certificate_key' => $site['ssl_certificate_key'] ?? '',
                'ssl_protocols' => 'TLSv1.2 TLSv1.3',
                'ssl_ciphers' => '',
                'ssl_prefer_server_ciphers' => true,
                'ssl_extra_includes' => [],
            ],
            'php' => [
                'php_enabled' => !empty($site['php_fastcgi']),
                'php_fastcgi_pass' => $site['php_fastcgi'] ?? 'unix:/run/php/php8.3-fpm.sock',
                'php_fastcgi_index' => 'index.php',
                'php_fastcgi_read_timeout' => '60',
            ],
            'logging' => [
                'access_log' => $site['root'] . '/logs/access.log',
                'error_log' => $site['root'] . '/logs/error.log',
                'log_format' => 'combined',
                'error_log_level' => 'error',
            ],
            'performance' => [
                'client_max_body_size' => '1M',
                'client_body_buffer_size' => '128k',
                'fastcgi_cache_enabled' => false,
                'fastcgi_cache_path' => '/var/cache/nginx/fastcgi',
                'fastcgi_cache_valid' => '60m',
                'fastcgi_cache_key' => '$scheme$request_method$host$request_uri',
                'fastcgi_cache_bypass' => '$cookie_PHPSESSID $http_authorization',
                'fastcgi_no_cache' => '$cookie_PHPSESSID $http_authorization',
                'fastcgi_cache_use_stale' => false,
                'browser_cache_enabled' => false,
                'cache_css_js' => '30d',
                'cache_images' => '90d',
                'cache_fonts' => '1y',
                'cache_media' => '1y',
                'gzip_enabled' => true,
                'gzip_types' => 'text/plain text/css application/json application/javascript text/xml application/xml',
                'gzip_comp_level' => '6',
                'gzip_min_length' => '256',
            ],
            'security' => [
                'server_tokens' => false,
                'x_frame_options' => 'SAMEORIGIN',
                'x_content_type_options' => true,
                'x_xss_protection' => true,
                'referrer_policy' => 'strict-origin-when-cross-origin',
            ],
            'locations' => [],
            'advanced' => [
                'custom_directives' => '',
            ],
        ];
    }

    private function saveConfiguration(string $serverName, array $config): void
    {
        $sql = "
            INSERT INTO site_configurations (
                server_name, document_root, index_files, http_listen, https_listen,
                https_enabled, redirect_http_to_https, 
                certificate_type, letsencrypt_email, letsencrypt_agree_tos, letsencrypt_extra_domains,
                ssl_certificate, ssl_certificate_key,
                ssl_protocols, ssl_ciphers, ssl_prefer_server_ciphers, ssl_extra_includes, 
                php_enabled, php_fastcgi_pass, php_fastcgi_index, php_fastcgi_read_timeout,
                access_log, error_log, log_format, error_log_level,
                client_max_body_size, client_body_buffer_size,
                fastcgi_cache_enabled, fastcgi_cache_path, fastcgi_cache_valid, fastcgi_cache_key,
                fastcgi_cache_bypass, fastcgi_no_cache, fastcgi_cache_use_stale,
                browser_cache_enabled, cache_css_js, cache_images, cache_fonts, cache_media,
                gzip_enabled, gzip_types, gzip_comp_level, gzip_min_length,
                server_tokens, x_frame_options, x_content_type_options, x_xss_protection, referrer_policy,
                custom_locations, custom_directives, enabled, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            ) ON DUPLICATE KEY UPDATE
                document_root = VALUES(document_root),
                index_files = VALUES(index_files),
                http_listen = VALUES(http_listen),
                https_listen = VALUES(https_listen),
                https_enabled = VALUES(https_enabled),
                redirect_http_to_https = VALUES(redirect_http_to_https),
                certificate_type = VALUES(certificate_type),
                letsencrypt_email = VALUES(letsencrypt_email),
                letsencrypt_agree_tos = VALUES(letsencrypt_agree_tos),
                letsencrypt_extra_domains = VALUES(letsencrypt_extra_domains),
                ssl_certificate = VALUES(ssl_certificate),
                ssl_certificate_key = VALUES(ssl_certificate_key),
                ssl_protocols = VALUES(ssl_protocols),
                ssl_ciphers = VALUES(ssl_ciphers),
                ssl_prefer_server_ciphers = VALUES(ssl_prefer_server_ciphers),
                ssl_extra_includes = VALUES(ssl_extra_includes),
                php_enabled = VALUES(php_enabled),
                php_fastcgi_pass = VALUES(php_fastcgi_pass),
                php_fastcgi_index = VALUES(php_fastcgi_index),
                php_fastcgi_read_timeout = VALUES(php_fastcgi_read_timeout),
                access_log = VALUES(access_log),
                error_log = VALUES(error_log),
                log_format = VALUES(log_format),
                error_log_level = VALUES(error_log_level),
                client_max_body_size = VALUES(client_max_body_size),
                client_body_buffer_size = VALUES(client_body_buffer_size),
                fastcgi_cache_enabled = VALUES(fastcgi_cache_enabled),
                fastcgi_cache_path = VALUES(fastcgi_cache_path),
                fastcgi_cache_valid = VALUES(fastcgi_cache_valid),
                fastcgi_cache_key = VALUES(fastcgi_cache_key),
                fastcgi_cache_bypass = VALUES(fastcgi_cache_bypass),
                fastcgi_no_cache = VALUES(fastcgi_no_cache),
                fastcgi_cache_use_stale = VALUES(fastcgi_cache_use_stale),
                browser_cache_enabled = VALUES(browser_cache_enabled),
                cache_css_js = VALUES(cache_css_js),
                cache_images = VALUES(cache_images),
                cache_fonts = VALUES(cache_fonts),
                cache_media = VALUES(cache_media),
                gzip_enabled = VALUES(gzip_enabled),
                gzip_types = VALUES(gzip_types),
                gzip_comp_level = VALUES(gzip_comp_level),
                gzip_min_length = VALUES(gzip_min_length),
                server_tokens = VALUES(server_tokens),
                x_frame_options = VALUES(x_frame_options),
                x_content_type_options = VALUES(x_content_type_options),
                x_xss_protection = VALUES(x_xss_protection),
                referrer_policy = VALUES(referrer_policy),
                custom_locations = VALUES(custom_locations),
                custom_directives = VALUES(custom_directives),
                enabled = VALUES(enabled),
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $serverName,
            $config['basic']['document_root'],
            json_encode($config['basic']['index_files']),
            json_encode($config['listen']['http_listen']),
            json_encode($config['listen']['https_listen']),
            $config['listen']['https_enabled'] ? 1 : 0,
            $config['listen']['redirect_http_to_https'] ? 1 : 0,
            $config['ssl']['certificate_type'] ?? 'letsencrypt',
            $config['ssl']['letsencrypt_email'] ?? null,
            $config['ssl']['letsencrypt_agree_tos'] ? 1 : 0,
            $config['ssl']['letsencrypt_extra_domains'] ?? null,
            $config['ssl']['ssl_certificate'] ?: null,
            $config['ssl']['ssl_certificate_key'] ?: null,
            $config['ssl']['ssl_protocols'] ?: null,
            $config['ssl']['ssl_ciphers'] ?: null,
            $config['ssl']['ssl_prefer_server_ciphers'] ? 1 : 0,
            json_encode($config['ssl']['ssl_extra_includes'] ?? []),
            $config['php']['php_enabled'] ? 1 : 0,
            $config['php']['php_fastcgi_pass'],
            $config['php']['php_fastcgi_index'],
            $config['php']['php_fastcgi_read_timeout'],
            $config['logging']['access_log'],
            $config['logging']['error_log'],
            $config['logging']['log_format'],
            $config['logging']['error_log_level'],
            $config['performance']['client_max_body_size'],
            $config['performance']['client_body_buffer_size'],
            $config['performance']['fastcgi_cache_enabled'] ? 1 : 0,
            $config['performance']['fastcgi_cache_path'],
            $config['performance']['fastcgi_cache_valid'],
            $config['performance']['fastcgi_cache_key'],
            $config['performance']['fastcgi_cache_bypass'],
            $config['performance']['fastcgi_no_cache'],
            $config['performance']['fastcgi_cache_use_stale'] ? 1 : 0,
            $config['performance']['browser_cache_enabled'] ? 1 : 0,
            $config['performance']['cache_css_js'],
            $config['performance']['cache_images'],
            $config['performance']['cache_fonts'],
            $config['performance']['cache_media'],
            $config['performance']['gzip_enabled'] ? 1 : 0,
            $config['performance']['gzip_types'],
            $config['performance']['gzip_comp_level'],
            $config['performance']['gzip_min_length'],
            $config['security']['server_tokens'] ? 1 : 0,
            $config['security']['x_frame_options'],
            $config['security']['x_content_type_options'] ? 1 : 0,
            $config['security']['x_xss_protection'] ? 1 : 0,
            $config['security']['referrer_policy'],
            json_encode($config['locations']),
            $config['advanced']['custom_directives'],
            $config['basic']['enabled'] ? 1 : 0,
        ]);
    }

    private function validateConfiguration(array $config): void
    {
        $required = ['basic', 'listen', 'ssl', 'php', 'logging', 'performance', 'security', 'locations', 'advanced'];
        foreach ($required as $section) {
            if (!isset($config[$section])) {
                throw new RuntimeException("Missing configuration section: $section");
            }
        }

        // Validate document root
        if (empty($config['basic']['document_root'])) {
            throw new RuntimeException("Document root is required");
        }

        // Validate SSL configuration
        if ($config['listen']['https_enabled']) {
            if (empty($config['ssl']['ssl_certificate']) || empty($config['ssl']['ssl_certificate_key'])) {
                throw new RuntimeException("SSL certificate and key are required when HTTPS is enabled");
            }
        }

        // Validate client_max_body_size format
        if (!preg_match('/^\d+[kmgKMG]?$/', $config['performance']['client_max_body_size'])) {
            throw new RuntimeException("Invalid client_max_body_size format");
        }
    }

    private function generateNginxConfig(string $serverName, array $config): array
    {
        return [
            'server_name' => $serverName,
            'server_names' => [$serverName],
            'root' => $config['basic']['document_root'],
            'index' => $config['basic']['index_files'],
            'listen_directives' => array_merge(
                $config['listen']['http_listen'],
                $config['listen']['https_enabled'] ? $config['listen']['https_listen'] : []
            ),
            'http_listen' => $config['listen']['http_listen'],
            'https_listen' => $config['listen']['https_listen'],
            'https' => $config['listen']['https_enabled'],
            'redirect_http' => $config['listen']['redirect_http_to_https'],
            
            // SSL settings
            'ssl_certificate' => $config['ssl']['ssl_certificate'],
            'ssl_certificate_key' => $config['ssl']['ssl_certificate_key'],
            'ssl_protocols' => $config['ssl']['ssl_protocols'],
            'ssl_ciphers' => $config['ssl']['ssl_ciphers'],
            'ssl_prefer_server_ciphers' => $config['ssl']['ssl_prefer_server_ciphers'],
            'ssl_extra_includes' => $config['ssl']['ssl_extra_includes'],
            
            // PHP settings
            'php_enabled' => $config['php']['php_enabled'],
            'php_socket' => $config['php']['php_enabled'] ? $config['php']['php_fastcgi_pass'] : null,
            'php_index' => $config['php']['php_fastcgi_index'],
            'php_read_timeout' => $config['php']['php_fastcgi_read_timeout'],
            
            // Logging settings
            'access_log' => $config['logging']['access_log'],
            'error_log' => $config['logging']['error_log'],
            'log_format' => $config['logging']['log_format'],
            'error_log_level' => $config['logging']['error_log_level'],
            
            // Performance settings
            'client_max_body_size' => $config['performance']['client_max_body_size'],
            'client_body_buffer_size' => $config['performance']['client_body_buffer_size'],
            'fastcgi_cache_enabled' => $config['performance']['fastcgi_cache_enabled'],
            'fastcgi_cache_path' => $config['performance']['fastcgi_cache_path'],
            'fastcgi_cache_valid' => $config['performance']['fastcgi_cache_valid'],
            'fastcgi_cache_key' => $config['performance']['fastcgi_cache_key'],
            'fastcgi_cache_bypass' => $config['performance']['fastcgi_cache_bypass'],
            'fastcgi_no_cache' => $config['performance']['fastcgi_no_cache'],
            'fastcgi_cache_use_stale' => $config['performance']['fastcgi_cache_use_stale'],
            'browser_cache_enabled' => $config['performance']['browser_cache_enabled'],
            'cache_css_js' => $config['performance']['cache_css_js'],
            'cache_images' => $config['performance']['cache_images'],
            'cache_fonts' => $config['performance']['cache_fonts'],
            'cache_media' => $config['performance']['cache_media'],
            'gzip_enabled' => $config['performance']['gzip_enabled'],
            'gzip_types' => $config['performance']['gzip_types'],
            'gzip_comp_level' => $config['performance']['gzip_comp_level'],
            'gzip_min_length' => $config['performance']['gzip_min_length'],
            
            // Security settings
            'server_tokens' => $config['security']['server_tokens'],
            'x_frame_options' => $config['security']['x_frame_options'],
            'x_content_type_options' => $config['security']['x_content_type_options'],
            'x_xss_protection' => $config['security']['x_xss_protection'],
            'referrer_policy' => $config['security']['referrer_policy'],
            
            // Advanced settings
            'custom_locations' => $config['locations'],
            'custom_directives' => $config['advanced']['custom_directives'],
        ];
    }
}