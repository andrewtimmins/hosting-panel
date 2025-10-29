<?php
namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;
use InvalidArgumentException;

class SiteDatabaseService
{
    private PDO $db;

    public function __construct(array $dbConfig)
    {
        $this->db = Connection::get($dbConfig);
    }

    /**
     * Link a database to a site
     */
    public function linkDatabase(string $serverName, string $databaseName, ?string $databaseUser = null, ?string $databaseHost = 'localhost', ?string $description = null): array
    {
        // Validate site exists
        $stmt = $this->db->prepare('SELECT server_name FROM sites WHERE server_name = :server_name');
        $stmt->execute([':server_name' => $serverName]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException("Site not found: {$serverName}");
        }

        // Validate database exists
        $stmt = $this->db->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :db_name');
        $stmt->execute([':db_name' => $databaseName]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException("Database not found: {$databaseName}");
        }

        // Check if link already exists
        $stmt = $this->db->prepare('SELECT id FROM site_databases WHERE server_name = :server_name AND database_name = :database_name');
        $stmt->execute([
            ':server_name' => $serverName,
            ':database_name' => $databaseName
        ]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException("Database {$databaseName} is already linked to site {$serverName}");
        }

        // Create the link
        $stmt = $this->db->prepare('
            INSERT INTO site_databases (server_name, database_name, database_user, database_host, description)
            VALUES (:server_name, :database_name, :database_user, :database_host, :description)
        ');
        
        $stmt->execute([
            ':server_name' => $serverName,
            ':database_name' => $databaseName,
            ':database_user' => $databaseUser,
            ':database_host' => $databaseHost,
            ':description' => $description
        ]);

        $linkId = $this->db->lastInsertId();

        return $this->getLinkById($linkId);
    }

    /**
     * Unlink a database from a site
     */
    public function unlinkDatabase(int $linkId): void
    {
        $stmt = $this->db->prepare('DELETE FROM site_databases WHERE id = :id');
        $stmt->execute([':id' => $linkId]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Database link not found: {$linkId}");
        }
    }

    /**
     * Unlink database by server name and database name
     */
    public function unlinkDatabaseByNames(string $serverName, string $databaseName): void
    {
        $stmt = $this->db->prepare('DELETE FROM site_databases WHERE server_name = :server_name AND database_name = :database_name');
        $stmt->execute([
            ':server_name' => $serverName,
            ':database_name' => $databaseName
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Database link not found for {$serverName} -> {$databaseName}");
        }
    }

    /**
     * Get all databases linked to a site
     */
    public function getSiteDatabases(string $serverName): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                sd.id,
                sd.server_name,
                sd.database_name,
                sd.database_user,
                sd.database_host,
                sd.description,
                sd.linked_at,
                ROUND(SUM(t.data_length + t.index_length) / 1024 / 1024, 2) as size_mb,
                COUNT(t.table_name) as table_count
            FROM site_databases sd
            LEFT JOIN information_schema.TABLES t ON t.table_schema = sd.database_name
            WHERE sd.server_name = :server_name
            GROUP BY sd.id, sd.server_name, sd.database_name, sd.database_user, sd.database_host, sd.description, sd.linked_at
            ORDER BY sd.linked_at DESC
        ');
        
        $stmt->execute([':server_name' => $serverName]);
        $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($databases as &$db) {
            $db['size'] = $db['size_mb'] ? $db['size_mb'] . ' MB' : '0 MB';
            $db['table_count'] = (int) $db['table_count'];
            unset($db['size_mb']);
        }

        return $databases;
    }

    /**
     * Get all sites that have a specific database linked
     */
    public function getDatabaseSites(string $databaseName): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                sd.id,
                sd.server_name,
                sd.database_name,
                sd.database_user,
                sd.database_host,
                sd.description,
                sd.linked_at,
                s.root,
                s.enabled
            FROM site_databases sd
            JOIN sites s ON sd.server_name = s.server_name
            WHERE sd.database_name = :database_name
            ORDER BY sd.linked_at DESC
        ');
        
        $stmt->execute([':database_name' => $databaseName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available databases that are not linked to a specific site
     */
    public function getAvailableDatabases(string $serverName): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                s.SCHEMA_NAME as name,
                s.DEFAULT_CHARACTER_SET_NAME as charset,
                s.DEFAULT_COLLATION_NAME as collation,
                ROUND(SUM(t.data_length + t.index_length) / 1024 / 1024, 2) as size_mb,
                COUNT(t.table_name) as table_count
            FROM information_schema.SCHEMATA s
            LEFT JOIN information_schema.TABLES t ON t.table_schema = s.SCHEMA_NAME
            WHERE s.SCHEMA_NAME NOT IN (
                SELECT database_name 
                FROM site_databases 
                WHERE server_name = :server_name
            )
            AND s.SCHEMA_NAME NOT IN ("information_schema", "performance_schema", "mysql", "sys", "webadmin")
            GROUP BY s.SCHEMA_NAME, s.DEFAULT_CHARACTER_SET_NAME, s.DEFAULT_COLLATION_NAME
            ORDER BY s.SCHEMA_NAME
        ');
        
        $stmt->execute([':server_name' => $serverName]);
        $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($databases as &$db) {
            $db['size'] = $db['size_mb'] ? $db['size_mb'] . ' MB' : '0 MB';
            $db['table_count'] = (int) $db['table_count'];
            unset($db['size_mb']);
        }

        return $databases;
    }

    /**
     * Get all database links
     */
    public function getAllLinks(): array
    {
        $stmt = $this->db->query('
            SELECT 
                sd.*,
                s.root,
                s.enabled
            FROM site_databases sd
            JOIN sites s ON sd.server_name = s.server_name
            ORDER BY sd.server_name, sd.linked_at DESC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific link by ID
     */
    private function getLinkById(int $linkId): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                sd.*,
                s.root,
                s.enabled
            FROM site_databases sd
            JOIN sites s ON sd.server_name = s.server_name
            WHERE sd.id = :id
        ');
        
        $stmt->execute([':id' => $linkId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            throw new RuntimeException("Database link not found: {$linkId}");
        }

        return $link;
    }

    /**
     * Update link description
     */
    public function updateLinkDescription(int $linkId, string $description): array
    {
        $stmt = $this->db->prepare('
            UPDATE site_databases 
            SET description = :description
            WHERE id = :id
        ');
        
        $stmt->execute([
            ':description' => $description,
            ':id' => $linkId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException("Database link not found: {$linkId}");
        }

        return $this->getLinkById($linkId);
    }
}
