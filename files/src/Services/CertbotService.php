<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\CommandRunner;
use RuntimeException;

class CertbotService
{
    private CommandRunner $commandRunner;
    private string $webroot;
    
    public function __construct(array $allowedCommands, string $webroot = '/var/www/html')
    {
        $this->commandRunner = new CommandRunner($allowedCommands);
        $this->webroot = $webroot;
    }
    
    /**
     * Check if certbot is installed
     */
    public function isInstalled(): bool
    {
        try {
            $result = $this->commandRunner->run('certbot --version');
            return $result['exit_code'] === 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtain a new certificate using webroot authentication
     */
    public function obtainCertificate(
        string $domain,
        string $email,
        array $extraDomains = [],
        bool $agreeTos = true,
        ?string $webroot = null
    ): array {
        if (!$this->isInstalled()) {
            throw new RuntimeException('Certbot is not installed. Please install certbot first.');
        }
        
        $webroot = $webroot ?? $this->webroot;
        
        // Build domain list
        $domains = array_merge([$domain], $extraDomains);
        $domainArgs = array_map(fn($d) => "-d " . escapeshellarg($d), $domains);
        $domainArgsStr = implode(' ', $domainArgs);
        
        // Build certbot command
        $command = sprintf(
            'certbot certonly --webroot -w %s %s --email %s %s --non-interactive',
            escapeshellarg($webroot),
            $domainArgsStr,
            escapeshellarg($email),
            $agreeTos ? '--agree-tos' : ''
        );
        
        $result = $this->commandRunner->run($command);
        
        if ($result['exit_code'] !== 0) {
            throw new RuntimeException(
                'Failed to obtain certificate: ' . ($result['stderr'] ?: $result['stdout'])
            );
        }
        
        return $this->getCertificatePaths($domain);
    }
    
    /**
     * Obtain a new certificate using standalone mode (requires port 80)
     */
    public function obtainCertificateStandalone(
        string $domain,
        string $email,
        array $extraDomains = [],
        bool $agreeTos = true
    ): array {
        if (!$this->isInstalled()) {
            throw new RuntimeException('Certbot is not installed. Please install certbot first.');
        }
        
        // Build domain list
        $domains = array_merge([$domain], $extraDomains);
        $domainArgs = array_map(fn($d) => "-d " . escapeshellarg($d), $domains);
        $domainArgsStr = implode(' ', $domainArgs);
        
        // Build certbot command
        $command = sprintf(
            'certbot certonly --standalone %s --email %s %s --non-interactive',
            $domainArgsStr,
            escapeshellarg($email),
            $agreeTos ? '--agree-tos' : ''
        );
        
        $result = $this->commandRunner->run($command);
        
        if ($result['exit_code'] !== 0) {
            throw new RuntimeException(
                'Failed to obtain certificate: ' . ($result['stderr'] ?: $result['stdout'])
            );
        }
        
        return $this->getCertificatePaths($domain);
    }
    
    /**
     * Obtain certificate using nginx plugin
     */
    public function obtainCertificateNginx(
        string $domain,
        string $email,
        array $extraDomains = [],
        bool $agreeTos = true
    ): array {
        if (!$this->isInstalled()) {
            throw new RuntimeException('Certbot is not installed. Please install certbot first.');
        }
        
        // Build domain list
        $domains = array_merge([$domain], $extraDomains);
        $domainArgs = array_map(fn($d) => "-d " . escapeshellarg($d), $domains);
        $domainArgsStr = implode(' ', $domainArgs);
        
        // Build certbot command
        $command = sprintf(
            'certbot --nginx %s --email %s %s --non-interactive',
            $domainArgsStr,
            escapeshellarg($email),
            $agreeTos ? '--agree-tos' : ''
        );
        
        $result = $this->commandRunner->run($command);
        
        if ($result['exit_code'] !== 0) {
            throw new RuntimeException(
                'Failed to obtain certificate: ' . ($result['stderr'] ?: $result['stdout'])
            );
        }
        
        return $this->getCertificatePaths($domain);
    }
    
    /**
     * Renew all certificates
     */
    public function renewCertificates(): array
    {
        if (!$this->isInstalled()) {
            throw new RuntimeException('Certbot is not installed.');
        }
        
        $result = $this->commandRunner->run('certbot renew --non-interactive');
        
        return [
            'success' => $result['exit_code'] === 0,
            'output' => $result['stdout'],
            'error' => $result['stderr'],
        ];
    }
    
    /**
     * Revoke a certificate
     */
    public function revokeCertificate(string $domain): void
    {
        $certPath = "/etc/letsencrypt/live/{$domain}/cert.pem";
        
        if (!file_exists($certPath)) {
            throw new RuntimeException("Certificate not found for domain: {$domain}");
        }
        
        $command = sprintf(
            'certbot revoke --cert-path %s --non-interactive',
            escapeshellarg($certPath)
        );
        
        $result = $this->commandRunner->run($command);
        
        if ($result['exit_code'] !== 0) {
            throw new RuntimeException(
                'Failed to revoke certificate: ' . ($result['stderr'] ?: $result['stdout'])
            );
        }
    }
    
    /**
     * Delete a certificate
     */
    public function deleteCertificate(string $domain): void
    {
        $command = sprintf(
            'certbot delete --cert-name %s --non-interactive',
            escapeshellarg($domain)
        );
        
        $result = $this->commandRunner->run($command);
        
        if ($result['exit_code'] !== 0) {
            throw new RuntimeException(
                'Failed to delete certificate: ' . ($result['stderr'] ?: $result['stdout'])
            );
        }
    }
    
    /**
     * Get certificate information
     */
    public function getCertificateInfo(string $domain): ?array
    {
        $certPath = "/etc/letsencrypt/live/{$domain}/cert.pem";
        
        if (!file_exists($certPath)) {
            return null;
        }
        
        $command = sprintf(
            'openssl x509 -in %s -noout -subject -issuer -dates',
            escapeshellarg($certPath)
        );
        
        $result = $this->commandRunner->run($command);
        
        if ($result['exit_code'] !== 0) {
            return null;
        }
        
        return [
            'path' => $certPath,
            'info' => $result['stdout'],
        ];
    }
    
    /**
     * List all certificates
     */
    public function listCertificates(): array
    {
        $result = $this->commandRunner->run('certbot certificates');
        
        if ($result['exit_code'] !== 0) {
            return [];
        }
        
        // Parse certbot output to get certificate list
        // This is a simplified version - actual parsing would be more complex
        return [
            'output' => $result['stdout'],
        ];
    }
    
    /**
     * Get standard certificate file paths for a domain
     */
    public function getCertificatePaths(string $domain): array
    {
        $liveDir = "/etc/letsencrypt/live/{$domain}";
        
        return [
            'certificate' => "{$liveDir}/fullchain.pem",
            'certificate_key' => "{$liveDir}/privkey.pem",
            'chain' => "{$liveDir}/chain.pem",
            'fullchain' => "{$liveDir}/fullchain.pem",
        ];
    }
    
    /**
     * Check if certificate exists for domain
     */
    public function certificateExists(string $domain): bool
    {
        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        return file_exists($certPath);
    }
}
