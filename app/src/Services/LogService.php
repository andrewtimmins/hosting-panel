<?php
namespace App\Services;

use RuntimeException;

class LogService
{
    private array $allowedLogFiles;

    public function __construct(array $allowedLogFiles)
    {
    $this->allowedLogFiles = array_map([$this, 'normalizePath'], $allowedLogFiles);
    }

    public function tail(string $logFile, int $lines = 200): array
    {
        $resolved = $this->normalizePath($logFile);

        if (!$this->isLogFileAllowed($resolved)) {
            throw new RuntimeException('Log file not allowed');
        }

        if (!is_file($resolved)) {
            throw new RuntimeException('Log file not found: ' . $logFile);
        }

        $lines = max(10, min($lines, 1000));

    $file = new \SplFileObject($resolved, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $target = max(0, $lastLine - $lines);
        $output = [];
        $file->seek($target);
        while (!$file->eof()) {
            $output[] = rtrim((string) $file->current(), "\r\n");
            $file->next();
        }
        return $output;
    }

    public function getAvailableLogFiles(array $sites = []): array
    {
        $result = [
            'system' => [],
            'sites' => []
        ];

        // Add system log files
        foreach ($this->allowedLogFiles as $logFile) {
            if (is_file($logFile)) {
                $name = basename($logFile);
                $result['system'][] = [
                    'name' => $name,
                    'path' => $logFile
                ];
            }
        }

        // Add site-specific log files
        foreach ($sites as $site) {
            $siteName = $site['server_name'] ?? '';
            $siteRoot = $site['root'] ?? '';
            if (empty($siteName)) continue;

            // Common nginx log locations for sites
            $possibleLogs = [
                "/var/log/nginx/{$siteName}-access.log",
                "/var/log/nginx/{$siteName}-error.log",
                "/var/log/nginx/access.log",
                "/var/log/nginx/error.log"
            ];
            
            // Add site-specific logs directory if root is available
            if (!empty($siteRoot)) {
                $possibleLogs[] = "{$siteRoot}/logs/access.log";
                $possibleLogs[] = "{$siteRoot}/logs/error.log";
            }

            foreach ($possibleLogs as $logPath) {
                if (is_file($logPath) && is_readable($logPath)) {
                    $logName = basename($logPath);
                    $displayName = str_contains($logName, $siteName) 
                        ? $logName 
                        : "{$siteName} - {$logName}";
                    
                    // Avoid duplicates
                    $exists = false;
                    foreach ($result['sites'] as $existing) {
                        if ($existing['path'] === $logPath) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $result['sites'][] = [
                            'name' => $displayName,
                            'path' => $logPath
                        ];
                    }
                }
            }
        }

        return $result;
    }

    private function isLogFileAllowed(string $resolvedPath): bool
    {
        // Check if it's in the explicitly allowed list
        if (\in_array($resolvedPath, $this->allowedLogFiles, true)) {
            return true;
        }

        // Allow any readable file that looks like a log file
        if (!is_file($resolvedPath) || !is_readable($resolvedPath)) {
            return false;
        }

        // Allow common log file extensions and patterns
        $logPatterns = [
            '/\.log$/',
            '/\.log\.\d+$/',
            '/\.log\.gz$/',
            '/access$/',
            '/error$/',
            '/nginx/',
            '/apache/',
            '/httpd/',
            '/php/',
            '/fpm/',
        ];

        foreach ($logPatterns as $pattern) {
            if (preg_match($pattern, $resolvedPath)) {
                return true;
            }
        }

        // Allow files in common log directories
        $allowedDirs = [
            '/var/log/',
            '/usr/local/var/log/',
            '/opt/nginx/logs/',
            '/websites/',
            '/logs/',
        ];

        foreach ($allowedDirs as $dir) {
            if (str_starts_with($resolvedPath, $dir)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        $fullPath = realpath(__DIR__ . '/../../' . $path);
        if ($fullPath === false) {
            return $path;
        }

        return $fullPath;
    }
}
