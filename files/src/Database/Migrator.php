<?php
namespace App\Database;

use PDO;

class Migrator
{
    private PDO $db;

    public function __construct(array $config)
    {
        $this->db = Connection::get($config);
    }

    public function ensure(): void
    {
        $this->createSitesTable();
        $this->createActionsLogTable();
        $this->createSiteConfigurationsTable();
        $this->addExtendedConfigFields();
        $this->addVarnishFieldsToSiteConfigurations();
        $this->addPhpConfigToSiteConfigurations();
        $this->createSslCertificatesTable();
        $this->createCronJobsTable();
        $this->createSettingsTable();
        $this->createUsersTable();
        $this->createUserSessionsTable();
        $this->createSiteDatabasesTable();
        $this->createBackupDestinationsTable();
        $this->createBackupJobsTable();
        $this->createBackupHistoryTable();
        $this->addProgressDataToBackupHistory();
        $this->createBackupQueueTable();
        $this->seedDefaultAdmin();
    }

    private function createSitesTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(255) NOT NULL UNIQUE,
    root VARCHAR(512) NOT NULL,
    listen SMALLINT UNSIGNED NOT NULL DEFAULT 80,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $this->db->exec($sql);
    }

    private function createActionsLogTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS actions_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    command_key VARCHAR(64) NOT NULL,
    command VARCHAR(255) NOT NULL,
    status ENUM('success', 'failure') NOT NULL,
    exit_code SMALLINT NOT NULL,
    stdout MEDIUMTEXT NULL,
    stderr MEDIUMTEXT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actions_log_command_key (command_key),
    INDEX idx_actions_log_executed_at (executed_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $this->db->exec($sql);
    }

    private function createSiteConfigurationsTable(): void
    {
        $migration = new \App\Database\Migrations\CreateSiteConfigurationsTable();
        $migration->up($this->db);
    }

    private function addExtendedConfigFields(): void
    {
        $migration = new \App\Database\Migrations\AddExtendedConfigFieldsToSiteConfigurations();
        $migration->up($this->db);
    }

    private function addVarnishFieldsToSiteConfigurations(): void
    {
        $migration = new \App\Database\Migrations\AddVarnishFieldsToSiteConfigurations();
        $migration->up($this->db);
    }

    private function addPhpConfigToSiteConfigurations(): void
    {
        $migration = new \App\Database\Migrations\AddPhpConfigToSiteConfigurations();
        $migration->up($this->db);
    }

    private function createSslCertificatesTable(): void
    {
        $migration = new \App\Database\Migrations\CreateSslCertificatesTable();
        $migration->up($this->db);
    }

    private function createCronJobsTable(): void
    {
        $migration = new \App\Database\Migrations\CreateCronJobsTable();
        $migration->up($this->db);
    }

    private function createSettingsTable(): void
    {
        $migration = new \App\Database\Migrations\CreateSettingsTable();
        $migration->up($this->db);
    }

    private function createUsersTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $this->db->exec($sql);
    }

    private function createUserSessionsTable(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $this->db->exec($sql);
    }

    private function createSiteDatabasesTable(): void
    {
        $migration = new \App\Database\Migrations\CreateSiteDatabasesTable();
        $migration->up($this->db);
    }

    private function createBackupDestinationsTable(): void
    {
        $migration = new \App\Database\Migrations\CreateBackupDestinationsTable($this->db);
        $migration->up();
    }

    private function createBackupJobsTable(): void
    {
        $migration = new \App\Database\Migrations\CreateBackupJobsTable($this->db);
        $migration->up();
    }

    private function createBackupHistoryTable(): void
    {
        $migration = new \App\Database\Migrations\CreateBackupHistoryTable($this->db);
        $migration->up();
    }

    private function addProgressDataToBackupHistory(): void
    {
        $migration = new \App\Database\Migrations\AddProgressDataToBackupHistory();
        $migration->up($this->db);
    }

    private function createBackupQueueTable(): void
    {
        $migration = new \App\Database\Migrations\CreateBackupQueueTable();
        $migration->up($this->db);
    }

    private function seedDefaultAdmin(): void
    {
        // Check if any admin users exist
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $stmt->execute(['admin']);
        $adminCount = $stmt->fetchColumn();

        if ($adminCount === 0) {
            // Create default admin user
            $stmt = $this->db->prepare('
                INSERT INTO users (username, email, password_hash, full_name, role, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                'admin',
                'admin@localhost',
                password_hash('admin123', PASSWORD_DEFAULT),
                'System Administrator',
                'admin',
                true
            ]);
        }
    }
}
