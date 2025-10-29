<?php
namespace App\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;
    private static ?array $config = null;

    public static function get(array $config): PDO
    {
        self::$config = $config;
        
        // Check if connection exists and is alive
        if (self::$instance !== null && !self::isAlive()) {
            self::$instance = null;
        }
        
        if (self::$instance === null) {
            self::$instance = self::createConnection($config);
        }

        return self::$instance;
    }
    
    private static function createConnection(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            
            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
    
    private static function isAlive(): bool
    {
        if (self::$instance === null) {
            return false;
        }
        
        try {
            self::$instance->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public static function reconnect(): void
    {
        self::$instance = null;
        if (self::$config !== null) {
            self::get(self::$config);
        }
    }
    
    public static function close(): void
    {
        self::$instance = null;
    }
}
