<?php
/**
 * @var array $data
 */
$serverNames = $data['server_names'] ?? [$data['server_name']];
$primaryServer = $serverNames[0];
$root = $data['root'];

// Varnish settings
$varnishEnabled = $data['varnish_enabled'] ?? false;
$varnishBackendPort = $data['varnish_backend_port'] ?? 8080;
$varnishListenPort = $data['varnish_listen_port'] ?? 80;

// If Varnish is enabled, Nginx should listen on the backend port instead of 80
$httpListen = $data['http_listen'] ?? ($data['listen_directives'] ?? ['80']);
if ($varnishEnabled) {
    // Replace 80 with backend port
    $httpListen = array_map(function($port) use ($varnishBackendPort) {
        return str_replace('80', (string)$varnishBackendPort, $port);
    }, $httpListen);
}

$httpsListen = $data['https_listen'] ?? [];
$httpsEnabled = !empty($data['https']);
$activeListen = $httpsEnabled ? array_merge($httpListen, $httpsListen) : $httpListen;
$redirectHttp = !empty($data['redirect_http']);
$indexFiles = $data['index'] ?? ['index.php', 'index.html', 'index.htm'];

// SSL settings
$sslCertificate = $data['ssl_certificate'] ?? null;
$sslCertificateKey = $data['ssl_certificate_key'] ?? null;
$sslProtocols = $data['ssl_protocols'] ?? 'TLSv1.2 TLSv1.3';
$sslCiphers = $data['ssl_ciphers'] ?? null;
$sslPreferServerCiphers = $data['ssl_prefer_server_ciphers'] ?? true;
$sslIncludes = $data['ssl_extra_includes'] ?? [];

// PHP settings
$phpEnabled = $data['php_enabled'] ?? true;
$phpSocket = $data['php_socket'] ?? 'unix:/run/php/php8.3-fpm.sock';
$phpIndex = $data['php_index'] ?? 'index.php';
$phpReadTimeout = $data['php_read_timeout'] ?? '60';

// Logging settings
$accessLog = $data['access_log'] ?? $root . '/logs/access.log';
$errorLog = $data['error_log'] ?? $root . '/logs/error.log';
$logFormat = $data['log_format'] ?? 'combined';
$errorLogLevel = $data['error_log_level'] ?? 'error';

// Performance settings
$clientMaxBodySize = $data['client_max_body_size'] ?? '1M';
$clientBodyBufferSize = $data['client_body_buffer_size'] ?? '128k';

// FastCGI Cache
$fastcgiCacheEnabled = $data['fastcgi_cache_enabled'] ?? false;
$fastcgiCachePath = $data['fastcgi_cache_path'] ?? '/var/cache/nginx/fastcgi';
$fastcgiCacheValid = $data['fastcgi_cache_valid'] ?? '60m';
$fastcgiCacheKey = $data['fastcgi_cache_key'] ?? '$scheme$request_method$host$request_uri';
$fastcgiCacheBypass = $data['fastcgi_cache_bypass'] ?? '';
$fastcgiNoCache = $data['fastcgi_no_cache'] ?? '';
$fastcgiCacheUseStale = $data['fastcgi_cache_use_stale'] ?? false;

// Browser Cache
$browserCacheEnabled = $data['browser_cache_enabled'] ?? false;
$cacheCssJs = $data['cache_css_js'] ?? '30d';
$cacheImages = $data['cache_images'] ?? '90d';
$cacheFonts = $data['cache_fonts'] ?? '1y';
$cacheMedia = $data['cache_media'] ?? '1y';

// Gzip
$gzipEnabled = $data['gzip_enabled'] ?? false;
$gzipTypes = $data['gzip_types'] ?? 'text/plain text/css application/json application/javascript text/xml application/xml';
$gzipCompLevel = $data['gzip_comp_level'] ?? '6';
$gzipMinLength = $data['gzip_min_length'] ?? '256';

// Security settings
$serverTokens = $data['server_tokens'] ?? false;
$xFrameOptions = $data['x_frame_options'] ?? 'SAMEORIGIN';
$xContentTypeOptions = $data['x_content_type_options'] ?? true;
$xXssProtection = $data['x_xss_protection'] ?? true;
$referrerPolicy = $data['referrer_policy'] ?? 'strict-origin-when-cross-origin';

// Advanced settings
$customDirectives = $data['custom_directives'] ?? '';
$customLocations = $data['custom_locations'] ?? [];

// Generate unique cache zone name based on server name
$cacheZoneName = 'fastcgi_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($primaryServer));
?>
<?php if ($fastcgiCacheEnabled): ?>
# FastCGI cache configuration
fastcgi_cache_path <?= $fastcgiCachePath ?>/<?= $cacheZoneName ?> levels=1:2 keys_zone=<?= $cacheZoneName ?>:10m inactive=<?= $fastcgiCacheValid ?> max_size=100m;
fastcgi_cache_key <?= $fastcgiCacheKey ?>;

<?php endif; ?>
# Managed by webadmin panel
<?php if ($varnishEnabled): ?>
# Varnish cache is enabled - Nginx acts as backend on port <?= $varnishBackendPort ?>

<?php endif; ?>
<?php if ($httpsEnabled && $redirectHttp): ?>
server {
<?php foreach ($httpListen as $directive): ?>
    listen <?= trim((string) $directive) ?>;
<?php endforeach; ?>
    server_name <?= implode(' ', $serverNames) ?>;
    return 301 https://$host$request_uri;
}

<?php endif; ?>
server {
<?php foreach ($activeListen as $directive): ?>
    listen <?= trim((string) $directive) ?>;
<?php endforeach; ?>
    server_name <?= implode(' ', $serverNames) ?>;

    root <?= rtrim($root, '/') ?>;
    index <?= implode(' ', array_map('trim', $indexFiles)) ?>;

    # Logging
    access_log <?= $accessLog ?> <?= $logFormat ?>;
    error_log <?= $errorLog ?> <?= $errorLogLevel ?>;

<?php if ($httpsEnabled): ?>
    # SSL configuration
    ssl_certificate <?= $sslCertificate ?>;
    ssl_certificate_key <?= $sslCertificateKey ?>;
    ssl_protocols <?= $sslProtocols ?>;
<?php if ($sslCiphers): ?>
    ssl_ciphers <?= $sslCiphers ?>;
<?php endif; ?>
    ssl_prefer_server_ciphers <?= $sslPreferServerCiphers ? 'on' : 'off' ?>;
<?php foreach ($sslIncludes as $include): ?>
    include <?= $include ?>;
<?php endforeach; ?>
<?php endif; ?>

    # Performance settings
    client_max_body_size <?= $clientMaxBodySize ?>;
    client_body_buffer_size <?= $clientBodyBufferSize ?>;

<?php if ($gzipEnabled): ?>
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level <?= $gzipCompLevel ?>;
    gzip_min_length <?= $gzipMinLength ?>;
    gzip_types <?= $gzipTypes ?>;
<?php endif; ?>

<?php if (!$serverTokens): ?>
    # Hide nginx version
    server_tokens off;
<?php endif; ?>

    # Security headers
    add_header X-Frame-Options "<?= $xFrameOptions ?>";
<?php if ($xContentTypeOptions): ?>
    add_header X-Content-Type-Options "nosniff";
<?php endif; ?>
<?php if ($xXssProtection): ?>
    add_header X-XSS-Protection "1; mode=block";
<?php endif; ?>
    add_header Referrer-Policy "<?= $referrerPolicy ?>";

<?php if ($customDirectives): ?>
    # Custom directives
<?= $customDirectives ?>

<?php endif; ?>
<?php if ($browserCacheEnabled): ?>
    # Browser caching for static files
    location ~* \.(css|js)$ {
        expires <?= $cacheCssJs ?>;
        add_header Cache-Control "public, immutable";
    }
    
    location ~* \.(jpg|jpeg|png|gif|ico|svg|webp)$ {
        expires <?= $cacheImages ?>;
        add_header Cache-Control "public, immutable";
    }
    
    location ~* \.(woff|woff2|ttf|otf|eot)$ {
        expires <?= $cacheFonts ?>;
        add_header Cache-Control "public, immutable";
    }
    
    location ~* \.(mp4|webm|ogg|mp3|wav|flac|aac)$ {
        expires <?= $cacheMedia ?>;
        add_header Cache-Control "public, immutable";
    }

<?php endif; ?>
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

<?php if (!empty($customLocations) && is_array($customLocations)): ?>
    # Custom location blocks
<?php foreach ($customLocations as $location): ?>
    location <?= htmlspecialchars($location['path']) ?> {
<?= $location['config'] ?>

    }

<?php endforeach; ?>
<?php endif; ?>
<?php if ($phpEnabled): ?>
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass <?= $phpSocket ?>;
        fastcgi_index <?= $phpIndex ?>;
        fastcgi_read_timeout <?= $phpReadTimeout ?>s;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
<?php if ($fastcgiCacheEnabled): ?>
        
        # FastCGI cache
        fastcgi_cache <?= $cacheZoneName ?>;
        fastcgi_cache_valid 200 <?= $fastcgiCacheValid ?>;
<?php if ($fastcgiCacheBypass): ?>
        fastcgi_cache_bypass <?= $fastcgiCacheBypass ?>;
<?php endif; ?>
<?php if ($fastcgiNoCache): ?>
        fastcgi_no_cache <?= $fastcgiNoCache ?>;
<?php endif; ?>
<?php if ($fastcgiCacheUseStale): ?>
        fastcgi_cache_use_stale error timeout updating invalid_header http_500 http_503;
        fastcgi_cache_background_update on;
<?php endif; ?>
        add_header X-FastCGI-Cache $upstream_cache_status;
<?php endif; ?>
    }
<?php endif; ?>

    location ~ /\.ht {
        deny all;
    }
}
