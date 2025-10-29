<?php
namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;

class DatabaseService
{
    private PDO $db;

    public function __construct(array $dbConfig)
    {
        $this->db = Connection::get($dbConfig);
    }

    public function listDatabases(): array
    {
        // Get list of databases excluding system databases
        $stmt = $this->db->query("
            SELECT 
                SCHEMA_NAME as name,
                DEFAULT_CHARACTER_SET_NAME as charset,
                DEFAULT_COLLATION_NAME as collation
            FROM information_schema.SCHEMATA
            WHERE SCHEMA_NAME NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
            ORDER BY SCHEMA_NAME
        ");
        
        $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get size and table count for each database
        foreach ($databases as &$database) {
            $dbName = $database['name'];
            
            // Get size
            $sizeStmt = $this->db->prepare("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = :db_name
            ");
            $sizeStmt->execute([':db_name' => $dbName]);
            $sizeResult = $sizeStmt->fetch(PDO::FETCH_ASSOC);
            $database['size'] = $sizeResult['size_mb'] ? $sizeResult['size_mb'] . ' MB' : '0 MB';
            
            // Get table count
            $tableStmt = $this->db->prepare("
                SELECT COUNT(*) as table_count
                FROM information_schema.TABLES
                WHERE table_schema = :db_name
            ");
            $tableStmt->execute([':db_name' => $dbName]);
            $tableResult = $tableStmt->fetch(PDO::FETCH_ASSOC);
            $database['tables'] = (int) $tableResult['table_count'];
        }
        
        return $databases;
    }

    public function createDatabase(string $name, string $charset = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): array
    {
        // Validate database name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('Database name may only contain letters, numbers, and underscores');
        }
        
        if (strlen($name) > 64) {
            throw new RuntimeException('Database name must not exceed 64 characters');
        }
        
        // Validate charset and collation
        $validCharsets = ['utf8mb4', 'utf8', 'latin1'];
        if (!in_array($charset, $validCharsets, true)) {
            throw new RuntimeException('Invalid character set');
        }
        
        // Create database
        $sql = sprintf(
            "CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s",
            $name,
            $charset,
            $collation
        );
        
        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'database exists') !== false) {
                throw new RuntimeException("Database '{$name}' already exists");
            }
            throw new RuntimeException('Failed to create database: ' . $e->getMessage());
        }
        
        return [
            'name' => $name,
            'charset' => $charset,
            'collation' => $collation
        ];
    }

    public function dropDatabase(string $name): void
    {
        // Validate database name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('Invalid database name');
        }
        
        // Prevent dropping system databases
        $systemDatabases = ['information_schema', 'performance_schema', 'mysql', 'sys'];
        if (in_array($name, $systemDatabases, true)) {
            throw new RuntimeException('Cannot drop system database');
        }
        
        $sql = sprintf("DROP DATABASE `%s`", $name);
        
        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "database doesn't exist") !== false) {
                throw new RuntimeException("Database '{$name}' does not exist");
            }
            throw new RuntimeException('Failed to drop database: ' . $e->getMessage());
        }
    }

    public function listUsers(): array
    {
        $stmt = $this->db->query("
            SELECT 
                User as username,
                Host as host
            FROM mysql.user
            WHERE User != ''
            ORDER BY User, Host
        ");
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get granted databases for each user
        foreach ($users as &$user) {
            $username = $user['username'];
            $host = $user['host'];
            
            // Get databases this user has access to
            $dbStmt = $this->db->prepare("
                SELECT DISTINCT Db
                FROM mysql.db
                WHERE User = :username AND Host = :host
                ORDER BY Db
            ");
            $dbStmt->execute([':username' => $username, ':host' => $host]);
            $databases = $dbStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $user['databases'] = implode(', ', $databases ?: ['-']);
        }
        
        return $users;
    }

    public function createUser(string $username, string $password, string $host = 'localhost', ?string $database = null): array
    {
        // Validate username
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new RuntimeException('Username may only contain letters, numbers, and underscores');
        }
        
        if (strlen($username) > 32) {
            throw new RuntimeException('Username must not exceed 32 characters');
        }
        
        // Validate host
        if ($host !== 'localhost' && $host !== '%' && !filter_var($host, FILTER_VALIDATE_IP)) {
            // Allow hostname patterns like %.example.com
            if (!preg_match('/^[a-zA-Z0-9%._-]+$/', $host)) {
                throw new RuntimeException('Invalid host format');
            }
        }
        
        // Create user
        $sql = sprintf(
            "CREATE USER '%s'@'%s' IDENTIFIED BY '%s'",
            $username,
            $host,
            $this->db->quote($password)
        );
        
        try {
            // Remove quotes added by quote()
            $sql = sprintf(
                "CREATE USER '%s'@'%s' IDENTIFIED BY '%s'",
                $username,
                $host,
                str_replace("'", "\\'", $password)
            );
            $this->db->exec($sql);
            
            // Grant access to specific database if provided
            if ($database && $database !== 'none') {
                $this->grantPermissions($username, $host, $database, ['ALL PRIVILEGES']);
            }
            
            // Flush privileges
            $this->db->exec('FLUSH PRIVILEGES');
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                throw new RuntimeException("User '{$username}'@'{$host}' already exists");
            }
            throw new RuntimeException('Failed to create user: ' . $e->getMessage());
        }
        
        return [
            'username' => $username,
            'host' => $host
        ];
    }

    public function dropUser(string $username, string $host): void
    {
        // Validate inputs
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new RuntimeException('Invalid username');
        }
        
        // Prevent dropping root user
        if ($username === 'root') {
            throw new RuntimeException('Cannot drop root user');
        }
        
        $sql = sprintf("DROP USER '%s'@'%s'", $username, $host);
        
        try {
            $this->db->exec($sql);
            $this->db->exec('FLUSH PRIVILEGES');
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                throw new RuntimeException("User '{$username}'@'{$host}' does not exist");
            }
            throw new RuntimeException('Failed to drop user: ' . $e->getMessage());
        }
    }

    public function grantPermissions(string $username, string $host, string $database, array $permissions): void
    {
        // Validate database name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            throw new RuntimeException('Invalid database name');
        }
        
        // Handle ALL PRIVILEGES
        if (in_array('ALL PRIVILEGES', $permissions, true)) {
            $permString = 'ALL PRIVILEGES';
        } else {
            // Validate individual permissions
            $validPermissions = [
                'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
                'INDEX', 'ALTER', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
                'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
                'ALTER ROUTINE', 'EVENT', 'TRIGGER'
            ];
            
            foreach ($permissions as $perm) {
                if (!in_array($perm, $validPermissions, true)) {
                    throw new RuntimeException("Invalid permission: {$perm}");
                }
            }
            
            $permString = implode(', ', $permissions);
        }
        
        $sql = sprintf(
            "GRANT %s ON `%s`.* TO '%s'@'%s'",
            $permString,
            $database,
            $username,
            $host
        );
        
        try {
            $this->db->exec($sql);
            $this->db->exec('FLUSH PRIVILEGES');
        } catch (\PDOException $e) {
            throw new RuntimeException('Failed to grant permissions: ' . $e->getMessage());
        }
    }
}
