<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

class SslCertificateService
{
    private PDO $db;
    private CertbotService $certbot;

    public function __construct(PDO $db, CertbotService $certbot)
    {
        $this->db = $db;
        $this->certbot = $certbot;
    }

    /**
     * List all certificates with their status
     */
    public function listCertificates(): array
    {
        $stmt = $this->db->query("
            SELECT 
                id,
                domain,
                email,
                method,
                additional_domains,
                status,
                issued_at,
                expires_at,
                auto_renew,
                last_renewed_at,
                DATEDIFF(expires_at, NOW()) as days_until_expiry
            FROM ssl_certificates
            ORDER BY expires_at ASC
        ");

        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse additional domains JSON
        foreach ($certificates as &$cert) {
            $cert['additional_domains'] = $cert['additional_domains'] 
                ? json_decode($cert['additional_domains'], true) 
                : [];
            $cert['days_until_expiry'] = (int) $cert['days_until_expiry'];
            
            // Update status based on expiry
            if ($cert['expires_at'] && strtotime($cert['expires_at']) < time()) {
                $this->updateCertificateStatus($cert['id'], 'expired');
                $cert['status'] = 'expired';
            }
        }

        return $certificates;
    }

    /**
     * Get certificate details
     */
    public function getCertificate(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ssl_certificates WHERE id = ?");
        $stmt->execute([$id]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cert) {
            return null;
        }

        $cert['additional_domains'] = $cert['additional_domains'] 
            ? json_decode($cert['additional_domains'], true) 
            : [];

        return $cert;
    }

    /**
     * Issue a new SSL certificate
     */
    public function issueCertificate(
        string $domain,
        string $email,
        string $method = 'webroot',
        array $additionalDomains = [],
        bool $autoRenew = true
    ): array {
        // Check if certificate already exists
        $stmt = $this->db->prepare("SELECT id FROM ssl_certificates WHERE domain = ?");
        $stmt->execute([$domain]);
        if ($stmt->fetch()) {
            throw new RuntimeException("Certificate already exists for domain: {$domain}");
        }

        // Insert pending certificate record
        $stmt = $this->db->prepare("
            INSERT INTO ssl_certificates (domain, email, method, additional_domains, status, auto_renew)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([
            $domain,
            $email,
            $method,
            json_encode($additionalDomains),
            $autoRenew
        ]);
        $certId = (int) $this->db->lastInsertId();

        try {
            // Issue certificate using certbot
            $result = match ($method) {
                'webroot' => $this->certbot->obtainCertificate($domain, $email, $additionalDomains),
                'standalone' => $this->certbot->obtainCertificateStandalone($domain, $email, $additionalDomains),
                'nginx' => $this->certbot->obtainCertificateNginx($domain, $email, $additionalDomains),
                default => throw new RuntimeException("Invalid method: {$method}")
            };

            // Get certificate expiry date
            $expiryDate = $this->getCertificateExpiryDate($domain);

            // Update certificate record
            $stmt = $this->db->prepare("
                UPDATE ssl_certificates 
                SET status = 'active', issued_at = NOW(), expires_at = ?, last_renewed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$expiryDate, $certId]);

            return $this->getCertificate($certId);
        } catch (\Exception $e) {
            // Mark as failed
            $this->db->prepare("DELETE FROM ssl_certificates WHERE id = ?")->execute([$certId]);
            throw $e;
        }
    }

    /**
     * Renew a certificate
     */
    public function renewCertificate(int $id): array
    {
        $cert = $this->getCertificate($id);
        if (!$cert) {
            throw new RuntimeException("Certificate not found");
        }

        // Renew using certbot
        $result = $this->certbot->renewCertificates();

        if (!$result['success']) {
            throw new RuntimeException("Failed to renew certificate: " . $result['error']);
        }

        // Get new expiry date
        $expiryDate = $this->getCertificateExpiryDate($cert['domain']);

        // Update certificate record
        $stmt = $this->db->prepare("
            UPDATE ssl_certificates 
            SET status = 'active', expires_at = ?, last_renewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$expiryDate, $id]);

        return $this->getCertificate($id);
    }

    /**
     * Delete a certificate
     */
    public function deleteCertificate(int $id): void
    {
        $cert = $this->getCertificate($id);
        if (!$cert) {
            throw new RuntimeException("Certificate not found");
        }

        try {
            // Delete from certbot
            $this->certbot->deleteCertificate($cert['domain']);
        } catch (\Exception $e) {
            // Continue even if certbot deletion fails
        }

        // Delete from database
        $stmt = $this->db->prepare("DELETE FROM ssl_certificates WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Revoke a certificate
     */
    public function revokeCertificate(int $id): void
    {
        $cert = $this->getCertificate($id);
        if (!$cert) {
            throw new RuntimeException("Certificate not found");
        }

        // Revoke using certbot
        $this->certbot->revokeCertificate($cert['domain']);

        // Update status
        $this->updateCertificateStatus($id, 'revoked');
    }

    /**
     * Get certificates expiring soon (within 30 days)
     */
    public function getExpiringCertificates(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ssl_certificates
            WHERE status = 'active' 
            AND expires_at IS NOT NULL
            AND expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
            AND expires_at >= NOW()
            ORDER BY expires_at ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Auto-renew certificates expiring within 30 days
     */
    public function autoRenewCertificates(): array
    {
        $expiring = $this->getExpiringCertificates(30);
        $renewed = [];
        $failed = [];

        foreach ($expiring as $cert) {
            if (!$cert['auto_renew']) {
                continue;
            }

            try {
                $this->renewCertificate($cert['id']);
                $renewed[] = $cert['domain'];
            } catch (\Exception $e) {
                $failed[] = [
                    'domain' => $cert['domain'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'renewed' => $renewed,
            'failed' => $failed
        ];
    }

    /**
     * Update certificate status
     */
    private function updateCertificateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare("UPDATE ssl_certificates SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    /**
     * Get certificate expiry date using openssl
     */
    private function getCertificateExpiryDate(string $domain): ?string
    {
        $certPath = "/etc/letsencrypt/live/{$domain}/cert.pem";
        
        if (!file_exists($certPath)) {
            return null;
        }

        $command = "openssl x509 -in " . escapeshellarg($certPath) . " -noout -enddate";
        $output = shell_exec($command);

        if ($output && preg_match('/notAfter=(.+)/', $output, $matches)) {
            $date = strtotime($matches[1]);
            return date('Y-m-d H:i:s', $date);
        }

        return null;
    }
}
