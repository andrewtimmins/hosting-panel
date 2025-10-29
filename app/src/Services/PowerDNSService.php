<?php
namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;
use InvalidArgumentException;

class PowerDNSService
{
    private PDO $db;

    public function __construct(array $dbConfig)
    {
        $this->db = Connection::get($dbConfig);
    }

    public function listDomains(): array
    {
        $stmt = $this->db->prepare('
            SELECT d.id, d.name, d.type, d.master, d.last_check, d.account, d.notified_serial,
                   COUNT(r.id) as record_count
            FROM domains d
            LEFT JOIN records r ON d.id = r.domain_id
            GROUP BY d.id, d.name, d.type, d.master, d.last_check, d.account, d.notified_serial
            ORDER BY d.name ASC
        ');
        
        $stmt->execute();
        $domains = $stmt->fetchAll();
        
        // Convert timestamps and add status information
        foreach ($domains as &$domain) {
            $domain['record_count'] = (int) $domain['record_count'];
            $domain['last_check'] = $domain['last_check'] ? date('Y-m-d H:i:s', $domain['last_check']) : null;
            $domain['last_modified'] = null; // PowerDNS doesn't track record modification times by default
            $domain['status'] = $this->getDomainStatus($domain);
        }
        
        return $domains;
    }

    public function getDomainRecords(string $domainName): array
    {
        $stmt = $this->db->prepare('
            SELECT r.id, r.name, r.type, r.content, r.ttl, r.prio, r.disabled, r.ordername, r.auth,
                   d.name as domain_name
            FROM records r
            JOIN domains d ON r.domain_id = d.id
            WHERE d.name = :domain_name
            ORDER BY r.type, r.name, r.prio
        ');
        
        $stmt->execute([':domain_name' => $domainName]);
        $records = $stmt->fetchAll();
        
        // Convert timestamps and format data
        foreach ($records as &$record) {
            $record['ttl'] = (int) $record['ttl'];
            $record['prio'] = $record['prio'] ? (int) $record['prio'] : null;
            $record['disabled'] = (bool) $record['disabled'];
            $record['auth'] = (bool) $record['auth'];
        }
        
        return $records;
    }

    public function createDomain(array $data): array
    {
        $this->validateDomainData($data);
        
        $name = strtolower(trim($data['name']));
        $type = strtoupper($data['type'] ?? 'NATIVE');
        $master = $data['master'] ?? null;
        $account = $data['account'] ?? null;
        
        // Check if domain already exists
        $stmt = $this->db->prepare('SELECT id FROM domains WHERE name = :name');
        $stmt->execute([':name' => $name]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('Domain already exists: ' . $name);
        }
        
        $this->db->beginTransaction();
        
        try {
            // Insert domain
            $stmt = $this->db->prepare('
                INSERT INTO domains (name, type, master, account) 
                VALUES (:name, :type, :master, :account)
            ');
            
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':master' => $master,
                ':account' => $account
            ]);
            
            $domainId = $this->db->lastInsertId();
            
            // Create default SOA record if it's a NATIVE domain
            if ($type === 'NATIVE') {
                $this->createDefaultSOARecord($domainId, $name);
            }
            
            $this->db->commit();
            
            // Return the created domain
            return $this->getDomainById($domainId);
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteDomain(string $domainName): void
    {
        $domain = $this->getDomainByName($domainName);
        if (!$domain) {
            throw new RuntimeException('Domain not found: ' . $domainName);
        }
        
        $this->db->beginTransaction();
        
        try {
            // Delete all records first
            $stmt = $this->db->prepare('DELETE FROM records WHERE domain_id = :domain_id');
            $stmt->execute([':domain_id' => $domain['id']]);
            
            // Delete domain
            $stmt = $this->db->prepare('DELETE FROM domains WHERE id = :domain_id');
            $stmt->execute([':domain_id' => $domain['id']]);
            
            $this->db->commit();
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function createRecord(string $domainName, array $data): array
    {
        $domain = $this->getDomainByName($domainName);
        if (!$domain) {
            throw new RuntimeException('Domain not found: ' . $domainName);
        }
        
        $this->validateRecordData($data);
        
        $name = strtolower(trim($data['name']));
        $type = strtoupper($data['type']);
        $content = trim($data['content']);
        $ttl = (int) ($data['ttl'] ?? 3600);
        $prio = isset($data['prio']) ? (int) $data['prio'] : null;
        $disabled = isset($data['disabled']) ? (bool) $data['disabled'] : false;
        
        // Validate record type specific requirements
        $this->validateRecordTypeRequirements($type, $content, $prio);
        
        $stmt = $this->db->prepare('
            INSERT INTO records (domain_id, name, type, content, ttl, prio, disabled) 
            VALUES (:domain_id, :name, :type, :content, :ttl, :prio, :disabled)
        ');
        
        $stmt->execute([
            ':domain_id' => $domain['id'],
            ':name' => $name,
            ':type' => $type,
            ':content' => $content,
            ':ttl' => $ttl,
            ':prio' => $prio,
            ':disabled' => $disabled ? 1 : 0
        ]);
        
        $recordId = $this->db->lastInsertId();
        
        // Update domain serial if SOA record
        if ($type === 'SOA') {
            $this->updateDomainSerial($domain['id']);
        }
        
        return $this->getRecordById($recordId);
    }

    public function updateRecord(int $recordId, array $data): array
    {
        $record = $this->getRecordById($recordId);
        if (!$record) {
            throw new RuntimeException('Record not found: ' . $recordId);
        }
        
        $this->validateRecordData($data);
        
        $content = trim($data['content']);
        $ttl = (int) ($data['ttl'] ?? $record['ttl']);
        $prio = isset($data['prio']) ? (int) $data['prio'] : $record['prio'];
        $disabled = isset($data['disabled']) ? (bool) $data['disabled'] : $record['disabled'];
        
        // Validate record type specific requirements
        $this->validateRecordTypeRequirements($record['type'], $content, $prio);
        
        $stmt = $this->db->prepare('
            UPDATE records 
            SET content = :content, ttl = :ttl, prio = :prio, disabled = :disabled
            WHERE id = :record_id
        ');
        
        $stmt->execute([
            ':content' => $content,
            ':ttl' => $ttl,
            ':prio' => $prio,
            ':disabled' => $disabled ? 1 : 0,
            ':record_id' => $recordId
        ]);
        
        // Update domain serial if SOA record
        if ($record['type'] === 'SOA') {
            $this->updateDomainSerial($record['domain_id'] ?? null);
        }
        
        return $this->getRecordById($recordId);
    }

    public function deleteRecord(int $recordId): void
    {
        $record = $this->getRecordById($recordId);
        if (!$record) {
            throw new RuntimeException('Record not found: ' . $recordId);
        }
        
        $stmt = $this->db->prepare('DELETE FROM records WHERE id = :record_id');
        $stmt->execute([':record_id' => $recordId]);
        
        // Update domain serial if SOA record was deleted
        if ($record['type'] === 'SOA') {
            $this->updateDomainSerial($record['domain_id'] ?? null);
        }
    }

    private function getDomainStatus(array $domain): string
    {
        if ($domain['type'] === 'SLAVE' && $domain['last_check'] && time() - $domain['last_check'] > 7200) {
            return 'stale';
        }
        
        if ($domain['record_count'] == 0) {
            return 'empty';
        }
        
        return 'active';
    }

    private function getDomainById(int $domainId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE id = :id');
        $stmt->execute([':id' => $domainId]);
        return $stmt->fetch() ?: null;
    }

    private function getDomainByName(string $domainName): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM domains WHERE name = :name');
        $stmt->execute([':name' => $domainName]);
        return $stmt->fetch() ?: null;
    }

    private function getRecordById(int $recordId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT r.*, d.name as domain_name 
            FROM records r 
            JOIN domains d ON r.domain_id = d.id 
            WHERE r.id = :id
        ');
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch();
        
        if ($record) {
            $record['ttl'] = (int) $record['ttl'];
            $record['prio'] = $record['prio'] ? (int) $record['prio'] : null;
            $record['disabled'] = (bool) $record['disabled'];
            $record['auth'] = (bool) $record['auth'];
        }
        
        return $record ?: null;
    }

    private function validateDomainData(array $data): void
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Domain name is required');
        }
        
        $name = strtolower(trim($data['name']));
        
        if (!filter_var('test@' . $name, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid domain name format');
        }
        
        if (strlen($name) > 253) {
            throw new InvalidArgumentException('Domain name too long');
        }
        
        $type = strtoupper($data['type'] ?? 'NATIVE');
        if (!in_array($type, ['NATIVE', 'MASTER', 'SLAVE'], true)) {
            throw new InvalidArgumentException('Invalid domain type');
        }
    }

    private function validateRecordData(array $data): void
    {
        if (empty($data['content'])) {
            throw new InvalidArgumentException('Record content is required');
        }
        
        if (isset($data['ttl']) && ((int) $data['ttl']) < 1) {
            throw new InvalidArgumentException('TTL must be positive');
        }
    }

    private function validateRecordTypeRequirements(string $type, string $content, ?int $prio): void
    {
        switch ($type) {
            case 'MX':
            case 'SRV':
                if ($prio === null) {
                    throw new InvalidArgumentException($type . ' records require a priority value');
                }
                break;
                
            case 'A':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    throw new InvalidArgumentException('A record must contain a valid IPv4 address');
                }
                break;
                
            case 'AAAA':
                if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    throw new InvalidArgumentException('AAAA record must contain a valid IPv6 address');
                }
                break;
        }
    }

    private function createDefaultSOARecord(int $domainId, string $domainName): void
    {
        $serial = date('Ymd') . '01'; // YYYYMMDDNN format
        $soaContent = "ns1.{$domainName} admin.{$domainName} {$serial} 10800 3600 604800 3600";
        
        $stmt = $this->db->prepare('
            INSERT INTO records (domain_id, name, type, content, ttl) 
            VALUES (:domain_id, :name, :type, :content, :ttl)
        ');
        
        $stmt->execute([
            ':domain_id' => $domainId,
            ':name' => $domainName,
            ':type' => 'SOA',
            ':content' => $soaContent,
            ':ttl' => 3600
        ]);
    }

    private function updateDomainSerial(?int $domainId): void
    {
        if (!$domainId) return;
        
        // Find SOA record and update serial
        $stmt = $this->db->prepare('
            SELECT id, content FROM records 
            WHERE domain_id = :domain_id AND type = :type
        ');
        $stmt->execute([':domain_id' => $domainId, ':type' => 'SOA']);
        $soaRecord = $stmt->fetch();
        
        if ($soaRecord) {
            $parts = explode(' ', $soaRecord['content']);
            if (count($parts) >= 5) {
                // Update serial (YYYYMMDDNN format)
                $today = date('Ymd');
                $currentSerial = $parts[2];
                
                if (substr($currentSerial, 0, 8) === $today) {
                    // Increment daily counter
                    $counter = intval(substr($currentSerial, 8)) + 1;
                    $parts[2] = $today . str_pad($counter, 2, '0', STR_PAD_LEFT);
                } else {
                    // New day, reset counter
                    $parts[2] = $today . '01';
                }
                
                $newContent = implode(' ', $parts);
                
                $updateStmt = $this->db->prepare('
                    UPDATE records 
                    SET content = :content 
                    WHERE id = :id
                ');
                $updateStmt->execute([
                    ':content' => $newContent,
                    ':id' => $soaRecord['id']
                ]);
            }
        }
    }
}