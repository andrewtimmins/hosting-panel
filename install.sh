#!/bin/bash#!/bin/bash



##########################################################################################

# WebAdmin Installer# WebAdmin Installer

# Complete web server management panel# For Ubuntu 22.04 and higher

# For Ubuntu 22.04 and higher#############################################

#############################################

set -e

set -e

# Colors for output

# Colors for outputRED='\033[0;31m'

RED='\033[0;31m'GREEN='\033[0;32m'

GREEN='\033[0;32m'YELLOW='\033[1;33m'

YELLOW='\033[1;33m'NC='\033[0m' # No Color

BLUE='\033[0;34m'

NC='\033[0m' # No Color# Function to print colored messages

print_success() { echo -e "${GREEN}✓ $1${NC}"; }

# Function to print colored messagesprint_error() { echo -e "${RED}✗ $1${NC}"; }

print_success() { echo -e "${GREEN}✓ $1${NC}"; }print_info() { echo -e "${YELLOW}➜ $1${NC}"; }

print_error() { echo -e "${RED}✗ $1${NC}"; }

print_info() { echo -e "${YELLOW}➜ $1${NC}"; }# Check if running as root

print_header() { echo -e "${BLUE}$1${NC}"; }if [[ $EUID -ne 0 ]]; then

   print_error "This script must be run as root (use sudo)"

# Function to check if command exists   exit 1

command_exists() {fi

    command -v "$1" >/dev/null 2>&1

}# Check Ubuntu version

print_info "Checking Ubuntu version..."

# Check if running as rootif [ -f /etc/os-release ]; then

if [[ $EUID -ne 0 ]]; then    . /etc/os-release

   print_error "This script must be run as root (use sudo)"    if [ "$ID" != "ubuntu" ]; then

   exit 1        print_error "This installer is designed for Ubuntu only"

fi        exit 1

    fi

# Welcome message    

clear    VERSION_MAJOR=$(echo $VERSION_ID | cut -d. -f1)

echo "════════════════════════════════════════════════════════"    if [ "$VERSION_MAJOR" -lt 22 ]; then

print_header "          WebAdmin Installation Script"        print_error "Ubuntu 22.04 or higher is required (you have $VERSION_ID)"

print_header "     Complete Web Server Management Panel"        exit 1

echo "════════════════════════════════════════════════════════"    fi

echo ""    print_success "Ubuntu $VERSION_ID detected"

else

# Check Ubuntu version    print_error "Cannot detect OS version"

print_info "Checking Ubuntu version compatibility..."    exit 1

if [ -f /etc/os-release ]; thenfi

    . /etc/os-release

    if [ "$ID" != "ubuntu" ]; then# Get configuration from user

        print_error "This installer is designed for Ubuntu only"print_info "WebAdmin Installation Setup"

        print_error "Detected OS: $PRETTY_NAME"echo ""

        exit 1

    firead -p "Enter installation directory [/websites/webadmin]: " INSTALL_DIR

    INSTALL_DIR=${INSTALL_DIR:-/websites/webadmin}

    VERSION_MAJOR=$(echo $VERSION_ID | cut -d. -f1)

    if [ "$VERSION_MAJOR" -lt 22 ]; thenread -p "Enter MySQL root password: " -s MYSQL_ROOT_PASS

        print_error "Ubuntu 22.04 or higher is required"echo ""

        print_error "You have: $VERSION_ID"read -p "Enter new database name [webadmin]: " DB_NAME

        exit 1DB_NAME=${DB_NAME:-webadmin}

    fi

    print_success "Ubuntu $VERSION_ID detected - compatible!"read -p "Enter new database user [webadmin]: " DB_USER

elseDB_USER=${DB_USER:-webadmin}

    print_error "Cannot detect OS version"

    exit 1read -p "Enter new database password: " -s DB_PASS

fiecho ""



echo ""read -p "Enter admin username for WebAdmin: " ADMIN_USER

print_info "This installer will set up a complete web server management panel including:"read -p "Enter admin email: " ADMIN_EMAIL

echo "  • Nginx web server with PHP 8.3"read -p "Enter admin password: " -s ADMIN_PASS

echo "  • MariaDB database server"echo ""

echo "  • PowerDNS with MySQL backend"

echo "  • Let's Encrypt SSL certificate support"# Confirm installation

echo "  • Async backup system with Supervisor"echo ""

echo "  • Website, database, and DNS management"print_info "Installation Summary:"

echo ""echo "  Installation Directory: $INSTALL_DIR"

echo "  Database Name: $DB_NAME"

# Get configuration from userecho "  Database User: $DB_USER"

print_header "Configuration Setup"echo ""

echo ""read -p "Continue with installation? (y/n) " -n 1 -r

echo ""

# Installation directoryif [[ ! $REPLY =~ ^[Yy]$ ]]; then

read -p "Enter installation directory [/var/www/webadmin]: " INSTALL_DIR    print_error "Installation cancelled"

INSTALL_DIR=${INSTALL_DIR:-/var/www/webadmin}    exit 1

fi

# MySQL/MariaDB Configuration

echo ""# Update system

print_info "Database Configuration"print_info "Updating system packages..."

read -p "Enter new database name [webadmin]: " DB_NAMEapt update && apt upgrade -y

DB_NAME=${DB_NAME:-webadmin}print_success "System updated"



read -p "Enter new database user [webadmin_user]: " DB_USER# Install required packages

DB_USER=${DB_USER:-webadmin_user}print_info "Installing required packages..."

apt install -y \

while true; do    nginx \

    read -s -p "Enter database password (8+ characters): " DB_PASS    php8.3-fpm \

    echo ""    php8.3-cli \

    if [ ${#DB_PASS} -lt 8 ]; then    php8.3-mysql \

        print_error "Password must be at least 8 characters long"    php8.3-mbstring \

        continue    php8.3-xml \

    fi    php8.3-curl \

    read -s -p "Confirm database password: " DB_PASS_CONFIRM    php8.3-zip \

    echo ""    mariadb-server \

    if [ "$DB_PASS" = "$DB_PASS_CONFIRM" ]; then    mariadb-client \

        break    pdns-server \

    else    pdns-backend-mysql \

        print_error "Passwords do not match. Please try again."    supervisor \

    fi    certbot \

done    python3-certbot-nginx \

    varnish \

# Admin user configuration    zip \

echo ""    unzip \

print_info "WebAdmin Administrator Account"    tar \

read -p "Enter admin username: " ADMIN_USER    gzip \

read -p "Enter admin email: " ADMIN_EMAIL    cron

print_success "Packages installed"

while true; do

    read -s -p "Enter admin password (8+ characters): " ADMIN_PASS# Create installation directory

    echo ""print_info "Creating installation directory..."

    if [ ${#ADMIN_PASS} -lt 8 ]; thenmkdir -p $INSTALL_DIR

        print_error "Password must be at least 8 characters long"mkdir -p $INSTALL_DIR/logs

        continuemkdir -p /websites/backups

    fiprint_success "Directories created"

    read -s -p "Confirm admin password: " ADMIN_PASS_CONFIRM

    echo ""# Copy application files

    if [ "$ADMIN_PASS" = "$ADMIN_PASS_CONFIRM" ]; thenprint_info "Copying application files..."

        breakcp -r $(dirname "$0")/app/* $INSTALL_DIR/

    elseprint_success "Application files copied"

        print_error "Passwords do not match. Please try again."

    fi# Copy and configure config file

doneprint_info "Configuring application..."

cp $(dirname "$0")/config/config.php.template $INSTALL_DIR/config/config.php

# Server configurationsed -i "s/{{DB_HOST}}/127.0.0.1/g" $INSTALL_DIR/config/config.php

echo ""sed -i "s/{{DB_PORT}}/3306/g" $INSTALL_DIR/config/config.php

print_info "Server Configuration"sed -i "s/{{DB_NAME}}/$DB_NAME/g" $INSTALL_DIR/config/config.php

SERVER_IP=$(hostname -I | awk '{print $1}')sed -i "s/{{DB_USER}}/$DB_USER/g" $INSTALL_DIR/config/config.php

read -p "Enter server domain name (or leave blank for IP access) [$SERVER_IP]: " SERVER_DOMAINsed -i "s/{{DB_PASS}}/$DB_PASS/g" $INSTALL_DIR/config/config.php

SERVER_DOMAIN=${SERVER_DOMAIN:-$SERVER_IP}sed -i "s|{{INSTALL_DIR}}|$INSTALL_DIR|g" $INSTALL_DIR/config/config.php

print_success "Configuration created"

# Confirm installation

echo ""# Set permissions

print_header "Installation Summary"print_info "Setting permissions..."

echo "═══════════════════════════════════════════════════════"chown -R www-data:www-data $INSTALL_DIR

echo "  Installation Directory: $INSTALL_DIR"chown -R www-data:www-data /websites/backups

echo "  Database Name: $DB_NAME"chmod -R 755 $INSTALL_DIR

echo "  Database User: $DB_USER"chmod -R 775 $INSTALL_DIR/logs

echo "  Admin Username: $ADMIN_USER"chmod -R 775 /websites/backups

echo "  Admin Email: $ADMIN_EMAIL"print_success "Permissions set"

echo "  Server Access: http://$SERVER_DOMAIN"

echo "═══════════════════════════════════════════════════════"# Configure MySQL

echo ""print_info "Creating database..."

read -p "Continue with installation? (y/N) " -n 1 -rmysql -u root -p"$MYSQL_ROOT_PASS" <<EOF

echo ""CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

if [[ ! $REPLY =~ ^[Yy]$ ]]; thenCREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';

    print_error "Installation cancelled by user"GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';

    exit 1FLUSH PRIVILEGES;

fiEOF

print_success "Database created"

echo ""

print_header "Starting Installation..."# Import schema

echo ""print_info "Importing database schema..."

mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < $(dirname "$0")/database/schema.sql

# Update systemprint_success "Schema imported"

print_info "Updating system packages..."

apt update >/dev/null 2>&1# Create admin user

apt upgrade -y >/dev/null 2>&1print_info "Creating admin user..."

print_success "System packages updated"ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);")

mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME <<EOF

# Install required packagesINSERT INTO users (username, email, password_hash, full_name, role, is_active)

print_info "Installing required packages..."VALUES ('$ADMIN_USER', '$ADMIN_EMAIL', '$ADMIN_HASH', 'Administrator', 'admin', 1);

apt install -y \EOF

    nginx \print_success "Admin user created"

    php8.3-fpm \

    php8.3-cli \# Configure Nginx

    php8.3-mysql \print_info "Configuring Nginx..."

    php8.3-mbstring \cp $(dirname "$0")/config/nginx-site.conf /etc/nginx/sites-available/webadmin

    php8.3-xml \sed -i "s|{{INSTALL_DIR}}|$INSTALL_DIR|g" /etc/nginx/sites-available/webadmin

    php8.3-curl \ln -sf /etc/nginx/sites-available/webadmin /etc/nginx/sites-enabled/webadmin

    php8.3-zip \rm -f /etc/nginx/sites-enabled/default

    php8.3-gd \nginx -t && systemctl reload nginx

    php8.3-intl \print_success "Nginx configured"

    mariadb-server \

    mariadb-client \# Configure sudoers

    pdns-server \print_info "Configuring sudoers for www-data..."

    pdns-backend-mysql \cp $(dirname "$0")/config/sudoers-webadmin /etc/sudoers.d/webadmin

    supervisor \chmod 440 /etc/sudoers.d/webadmin

    certbot \print_success "Sudoers configured"

    python3-certbot-nginx \

    unzip \# Configure Supervisor for backup worker

    curl \print_info "Configuring Supervisor backup worker..."

    wget \cp $(dirname "$0")/config/backup-worker.conf /etc/supervisor/conf.d/backup-worker.conf

    tar \sed -i "s|{{INSTALL_DIR}}|$INSTALL_DIR|g" /etc/supervisor/conf.d/backup-worker.conf

    gzip >/dev/null 2>&1supervisorctl reread

print_success "Required packages installed"supervisorctl update

supervisorctl start backup-worker

# Secure MariaDB installationprint_success "Backup worker configured and started"

print_info "Configuring MariaDB security..."

mysql -u root <<EOF# Configure PowerDNS

DELETE FROM mysql.user WHERE User='';print_info "Configuring PowerDNS..."

DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');cat > /etc/powerdns/pdns.d/mysql.conf <<EOF

DROP DATABASE IF EXISTS test;launch=gmysql

DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';gmysql-host=127.0.0.1

FLUSH PRIVILEGES;gmysql-port=3306

EOFgmysql-dbname=$DB_NAME

print_success "MariaDB secured"gmysql-user=$DB_USER

gmysql-password=$DB_PASS

# Create installation directory structuregmysql-dnssec=yes

print_info "Creating installation directories..."EOF

mkdir -p $INSTALL_DIRsystemctl restart pdns

mkdir -p $INSTALL_DIR/logsprint_success "PowerDNS configured"

mkdir -p $INSTALL_DIR/config

mkdir -p $INSTALL_DIR/assets/css# Configure Varnish (optional, disabled by default)

mkdir -p $INSTALL_DIR/assets/jsprint_info "Configuring Varnish Cache..."

mkdir -p $INSTALL_DIR/assets/imgsystemctl stop varnish

mkdir -p $INSTALL_DIR/src/Databasesystemctl disable varnish

mkdir -p $INSTALL_DIR/src/Servicesprint_success "Varnish installed (disabled by default, enable per-site as needed)"

mkdir -p $INSTALL_DIR/src/Support

mkdir -p $INSTALL_DIR/templates# Create PHP-FPM pool directory

mkdir -p /var/www/backupsprint_info "Creating PHP-FPM pool directory..."

print_success "Directory structure created"mkdir -p /etc/php/8.3/fpm/pool.d

print_success "PHP-FPM pool directory created"

# Set up database

print_info "Creating database and user..."# Ensure cron service is running

mysql -u root <<EOFprint_info "Ensuring cron service is running..."

CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;systemctl enable cron

CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';systemctl start cron

GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';print_success "Cron service enabled"

FLUSH PRIVILEGES;

EOF# Enable services

print_success "Database and user created"print_info "Enabling services..."

systemctl enable nginx php8.3-fpm mariadb pdns supervisor cron

# Create database schemasystemctl start nginx php8.3-fpm mariadb pdns supervisor cron

print_info "Creating database schema..."print_success "Services enabled and started"

mysql -u root $DB_NAME <<'EOF'

-- Users table# Installation complete

CREATE TABLE IF NOT EXISTS `users` (echo ""

  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,echo "═══════════════════════════════════════════════════════"

  `username` varchar(50) NOT NULL,print_success "WebAdmin installation completed successfully!"

  `email` varchar(255) NOT NULL,echo "═══════════════════════════════════════════════════════"

  `password_hash` varchar(255) NOT NULL,echo ""

  `full_name` varchar(255) DEFAULT NULL,echo "Access your WebAdmin panel at: http://$(hostname -I | awk '{print $1}')"

  `role` enum('admin','user') NOT NULL DEFAULT 'user',echo ""

  `is_active` tinyint(1) NOT NULL DEFAULT 1,echo "Login credentials:"

  `last_login` datetime DEFAULT NULL,echo "  Username: $ADMIN_USER"

  `created_at` datetime NOT NULL DEFAULT current_timestamp(),echo "  Password: (the password you entered)"

  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),echo ""

  PRIMARY KEY (`id`),echo "Important next steps:"

  UNIQUE KEY `username` (`username`),echo "  1. Configure SSL certificate with certbot"

  UNIQUE KEY `email` (`email`)echo "  2. Review and customize /etc/nginx/sites-available/webadmin"

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;echo "  3. Check backup worker: sudo supervisorctl status backup-worker"

echo "  4. Secure your MySQL installation: sudo mysql_secure_installation"

-- User sessions tableecho "  5. Configure Varnish cache if needed (disabled by default)"

CREATE TABLE IF NOT EXISTS `user_sessions` (echo ""

  `id` varchar(128) NOT NULL,echo "Installed features:"

  `user_id` int(10) unsigned NOT NULL,echo "  ✓ Website Management with Nginx"

  `ip_address` varchar(45) DEFAULT NULL,echo "  ✓ PHP Configuration Management (per-site settings)"

  `user_agent` text DEFAULT NULL,echo "  ✓ SSL Certificate Management (Let's Encrypt)"

  `expires_at` datetime NOT NULL,echo "  ✓ Cron Job Scheduler (per-site tasks)"

  `created_at` datetime NOT NULL DEFAULT current_timestamp(),echo "  ✓ Database Management (MySQL/MariaDB)"

  PRIMARY KEY (`id`),echo "  ✓ DNS Management (PowerDNS)"

  KEY `idx_user_id` (`user_id`),echo "  ✓ File Manager with Code Editor (Ace Editor)"

  KEY `idx_expires` (`expires_at`),echo "  ✓ Backup System (async queue-based)"

  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADEecho "  ✓ CMS Installers (WordPress, OpenCart)"

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;echo "  ✓ Service Control Panel"

echo "  ✓ Varnish Cache (optional, enable per-site)"

-- Sites tableecho ""

CREATE TABLE IF NOT EXISTS `sites` (print_info "Installation log saved to: /var/log/webadmin-install.log"

  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(255) NOT NULL,
  `root` varchar(512) NOT NULL,
  `listen` smallint(5) unsigned NOT NULL DEFAULT 80,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_name` (`server_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site configurations table
CREATE TABLE IF NOT EXISTS `site_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server_name` varchar(255) NOT NULL,
  `document_root` varchar(500) NOT NULL,
  `index_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`index_files`)),
  `http_listen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`http_listen`)),
  `https_listen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`https_listen`)),
  `https_enabled` tinyint(1) DEFAULT 0,
  `redirect_http_to_https` tinyint(1) DEFAULT 0,
  `ssl_certificate` varchar(500) DEFAULT NULL,
  `ssl_certificate_key` varchar(500) DEFAULT NULL,
  `php_enabled` tinyint(1) DEFAULT 1,
  `php_fastcgi_pass` varchar(255) DEFAULT 'unix:/run/php/php8.3-fpm.sock',
  `enabled` tinyint(1) DEFAULT 1,
  `managed` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `server_name` (`server_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site databases table
CREATE TABLE IF NOT EXISTS `site_databases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `server_name` varchar(255) NOT NULL,
  `database_name` varchar(64) NOT NULL,
  `database_user` varchar(32) DEFAULT NULL,
  `database_host` varchar(255) DEFAULT 'localhost',
  `description` varchar(500) DEFAULT NULL,
  `linked_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_site_database` (`server_name`,`database_name`),
  CONSTRAINT `site_databases_ibfk_1` FOREIGN KEY (`server_name`) REFERENCES `sites` (`server_name`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PowerDNS tables
CREATE TABLE IF NOT EXISTS `domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `master` varchar(128) DEFAULT NULL,
  `last_check` int(11) DEFAULT NULL,
  `type` varchar(8) NOT NULL,
  `notified_serial` int(10) unsigned DEFAULT NULL,
  `account` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `records` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `domain_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(10) DEFAULT NULL,
  `content` varchar(64000) DEFAULT NULL,
  `ttl` int(11) DEFAULT NULL,
  `prio` int(11) DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT 0,
  `ordername` varchar(255) DEFAULT NULL,
  `auth` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `nametype_index` (`name`,`type`),
  KEY `domain_id` (`domain_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Backup system tables
CREATE TABLE IF NOT EXISTS `backup_destinations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('local','sftp') NOT NULL DEFAULT 'local',
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config`)),
  `is_default` tinyint(1) DEFAULT 0,
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backup_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_type` enum('site','database','domain','mixed') NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `destination_type` enum('local','sftp') NOT NULL,
  `destination_path` text NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `status` enum('pending','in_progress','completed','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `progress_data` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `backup_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `destination_id` int(11) NOT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `history_id` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `priority` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`,`created_at`),
  CONSTRAINT `backup_queue_ibfk_1` FOREIGN KEY (`destination_id`) REFERENCES `backup_destinations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `backup_queue_ibfk_2` FOREIGN KEY (`history_id`) REFERENCES `backup_history` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actions log table
CREATE TABLE IF NOT EXISTS `actions_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `command_key` varchar(64) NOT NULL,
  `command` varchar(255) NOT NULL,
  `status` enum('success','failure') NOT NULL,
  `exit_code` smallint(6) NOT NULL,
  `stdout` mediumtext DEFAULT NULL,
  `stderr` mediumtext DEFAULT NULL,
  `executed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_command_key` (`command_key`),
  KEY `idx_executed_at` (`executed_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
print_success "Database schema created"

# Insert default backup destination
print_info "Creating default backup destination..."
mysql -u root $DB_NAME <<EOF
INSERT INTO backup_destinations (name, type, config, is_default) 
VALUES ('Local Backups', 'local', '{"path": "/var/www/backups"}', 1);
EOF
print_success "Default backup destination created"

# Create admin user
print_info "Creating admin user account..."
ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);")
mysql -u root $DB_NAME <<EOF
INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES ('$ADMIN_USER', '$ADMIN_EMAIL', '$ADMIN_HASH', 'Administrator', 'admin', 1);
EOF
print_success "Admin user created"

# Install application files - this will be done by copying from the script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

print_info "Installing application files..."

# Check if files directory exists
if [ ! -d "$SCRIPT_DIR/files" ]; then
    print_error "Application files directory not found!"
    print_error "Please ensure the 'files' directory is present with all application files."
    exit 1
fi

# Copy all application files
cp -r "$SCRIPT_DIR/files/"* "$INSTALL_DIR/"
print_success "Application files installed"

# Create configuration file
print_info "Creating configuration..."
cat > "$INSTALL_DIR/config/config.php" <<EOF
<?php
return [
    'mysql' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => '$DB_NAME',
        'username' => '$DB_USER',
        'password' => '$DB_PASS',
        'charset' => 'utf8mb4',
    ],
    'paths' => [
        'websites_root' => '/var/www/websites',
        'nginx_sites_enabled' => '/etc/nginx/sites-enabled',
        'nginx_disabled_dir' => '/etc/nginx/sites-disabled',
        'nginx_template_source' => null,
        'nginx_binary' => '/usr/sbin/nginx',
        'php_fpm_service' => 'php8.3-fpm',
        'nginx_service' => 'nginx',
        'logs' => [
            'access' => '$INSTALL_DIR/logs/nginx-access.log',
            'error' => '$INSTALL_DIR/logs/nginx-error.log',
        ],
    ],
    'site_defaults' => [
        'document_root_pattern' => '/var/www/websites/{server_name}',
        'include_www_alias' => false,
        'php_fastcgi' => 'unix:/run/php/php8.3-fpm.sock',
        'index_files' => ['index.php', 'index.html', 'index.htm'],
        'http_listen' => ['80'],
        'https_listen' => ['443 ssl http2'],
        'redirect_http_to_https' => true,
        'ssl_certificate_root' => '/etc/letsencrypt/live/{server_name}',
        'ssl_certificate_file' => 'fullchain.pem',
        'ssl_certificate_key_file' => 'privkey.pem',
        'ssl_extra_includes' => ['snippets/ssl-params.conf'],
    ],
    'security' => [
        'allowed_commands' => [
            'nginx_reload' => 'sudo systemctl reload nginx',
            'nginx_restart' => 'sudo systemctl restart nginx',
            'php_fpm_restart' => 'sudo systemctl restart php8.3-fpm',
            'php_fpm_reload' => 'sudo systemctl reload php8.3-fpm',
            'nginx_test' => 'sudo nginx -t',
            'mysql_reload' => 'sudo systemctl reload mariadb',
            'mysql_restart' => 'sudo systemctl restart mariadb',
            'powerdns_reload' => 'sudo systemctl reload pdns',
            'powerdns_restart' => 'sudo systemctl restart pdns',
        ],
        'allowed_log_files' => [
            '$INSTALL_DIR/logs/nginx-access.log',
            '$INSTALL_DIR/logs/nginx-error.log'
        ],
    ],
    'wordpress' => [
        'download_url' => 'https://wordpress.org/latest.zip',
        'default_admin_username' => 'admin',
        'default_admin_password' => 'ChangeMe123!',
        'default_admin_email' => 'admin@example.com',
        'default_site_title' => 'WordPress Site for {server_name}',
        'default_table_prefix' => 'wp_',
    ],
    'opencart' => [
        'download_url' => 'https://github.com/opencart/opencart/releases/download/4.0.2.3/opencart-4.0.2.3.zip',
        'default_admin_username' => 'admin',
        'default_admin_password' => 'Admin123!',
        'default_admin_email' => 'admin@example.com',
        'default_store_name' => 'OpenCart Store for {server_name}',
    ],
    'features' => [
        'enable_auth' => true,
    ],
];
EOF
print_success "Configuration file created"

# Set up Nginx
print_info "Configuring Nginx..."
cat > "/etc/nginx/sites-available/webadmin" <<EOF
server {
    listen 80;
    listen [::]:80;
    
    server_name $SERVER_DOMAIN;
    root $INSTALL_DIR;
    index index.php index.html index.htm;

    # Logging
    access_log $INSTALL_DIR/logs/nginx-access.log;
    error_log $INSTALL_DIR/logs/nginx-error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 600;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to backup files
    location ~ ~\$ {
        deny all;
    }
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/webadmin /etc/nginx/sites-enabled/webadmin
rm -f /etc/nginx/sites-enabled/default

# Test nginx configuration
nginx -t >/dev/null 2>&1
systemctl reload nginx
print_success "Nginx configured and reloaded"

# Configure sudoers for www-data
print_info "Configuring system permissions..."
cat > "/etc/sudoers.d/webadmin" <<'EOF'
# WebAdmin sudoers configuration
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.3-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart php8.3-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload mariadb
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart mariadb
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload pdns
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart pdns
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot certonly *
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot renew *
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot delete *
www-data ALL=(ALL) NOPASSWD: /bin/tar -czpf /var/www/backups/* *
www-data ALL=(ALL) NOPASSWD: /bin/tar -xzpf /var/www/backups/* *
www-data ALL=(ALL) NOPASSWD: /usr/bin/mysqldump *
www-data ALL=(ALL) NOPASSWD: /usr/bin/mysql *
www-data ALL=(ALL) NOPASSWD: /bin/cp -a /var/www/* *
www-data ALL=(ALL) NOPASSWD: /bin/rm -rf /var/www/backups/.tmp_* *
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/*
www-data ALL=(ALL) NOPASSWD: /bin/chown -R * /var/www/*
EOF
chmod 440 /etc/sudoers.d/webadmin
print_success "System permissions configured"

# Configure Supervisor for backup worker
print_info "Setting up backup worker daemon..."
cat > "/etc/supervisor/conf.d/webadmin-backup-worker.conf" <<EOF
[program:webadmin-backup-worker]
command=/usr/bin/php $INSTALL_DIR/backup-worker.php
directory=$INSTALL_DIR
autostart=true
autorestart=true
startretries=3
stderr_logfile=/var/log/supervisor/webadmin-backup-worker.err.log
stdout_logfile=/var/log/supervisor/webadmin-backup-worker.out.log
user=www-data
environment=HOME="/var/www",USER="www-data"
priority=999
EOF

supervisorctl reread >/dev/null 2>&1
supervisorctl update >/dev/null 2>&1
supervisorctl start webadmin-backup-worker >/dev/null 2>&1
print_success "Backup worker daemon configured"

# Configure PowerDNS
print_info "Configuring PowerDNS..."
cat > "/etc/powerdns/pdns.d/mysql.conf" <<EOF
launch=gmysql
gmysql-host=127.0.0.1
gmysql-port=3306
gmysql-dbname=$DB_NAME
gmysql-user=$DB_USER
gmysql-password=$DB_PASS
gmysql-dnssec=yes
EOF
systemctl restart pdns >/dev/null 2>&1
print_success "PowerDNS configured"

# Set proper permissions
print_info "Setting file permissions..."
mkdir -p /var/www/websites
chown -R www-data:www-data $INSTALL_DIR
chown -R www-data:www-data /var/www/backups
chown -R www-data:www-data /var/www/websites
chmod -R 755 $INSTALL_DIR
chmod -R 775 $INSTALL_DIR/logs
chmod -R 775 /var/www/backups
chmod -R 775 /var/www/websites
print_success "File permissions set"

# Enable and start services
print_info "Enabling and starting services..."
systemctl enable nginx php8.3-fpm mariadb pdns supervisor >/dev/null 2>&1
systemctl start nginx php8.3-fpm mariadb pdns supervisor >/dev/null 2>&1
print_success "Services enabled and started"

# Create SSL snippet for future use
print_info "Creating SSL configuration snippet..."
mkdir -p /etc/nginx/snippets
cat > "/etc/nginx/snippets/ssl-params.conf" <<'EOF'
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers on;
ssl_dhparam /etc/nginx/dhparam.pem;
ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-SHA384;
ssl_ecdh_curve secp384r1;
ssl_session_timeout 10m;
ssl_session_cache shared:SSL:10m;
ssl_session_tickets off;
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
add_header X-Frame-Options DENY;
add_header X-Content-Type-Options nosniff;
add_header X-XSS-Protection "1; mode=block";
EOF

# Generate DH parameters (this might take a while)
if [ ! -f /etc/nginx/dhparam.pem ]; then
    print_info "Generating DH parameters (this may take a few minutes)..."
    openssl dhparam -out /etc/nginx/dhparam.pem 2048 >/dev/null 2>&1
    print_success "DH parameters generated"
fi

# Final system check
print_info "Performing final system checks..."
sleep 2

# Check if services are running
SERVICES_OK=true
for service in nginx php8.3-fpm mariadb pdns supervisor; do
    if ! systemctl is-active --quiet $service; then
        print_error "Service $service is not running"
        SERVICES_OK=false
    fi
done

# Check if backup worker is running
if ! supervisorctl status webadmin-backup-worker | grep -q RUNNING; then
    print_error "Backup worker is not running"
    SERVICES_OK=false
fi

if [ "$SERVICES_OK" = true ]; then
    print_success "All services are running correctly"
else
    print_error "Some services are not running properly"
    echo "Check the logs for more information:"
    echo "  - Nginx: /var/log/nginx/"
    echo "  - PHP-FPM: /var/log/php8.3-fpm.log"
    echo "  - MariaDB: /var/log/mysql/"
    echo "  - PowerDNS: /var/log/syslog"
    echo "  - Backup Worker: /var/log/supervisor/webadmin-backup-worker.*.log"
fi

# Installation complete
echo ""
echo "════════════════════════════════════════════════════════"
print_success "WebAdmin installation completed successfully!"
echo "════════════════════════════════════════════════════════"
echo ""
print_info "Access Information"
echo "  URL: http://$SERVER_DOMAIN"
echo "  Username: $ADMIN_USER"
echo "  Password: (the password you entered)"
echo ""
print_info "Installation Details"
echo "  Installation Directory: $INSTALL_DIR"
echo "  Database: $DB_NAME"
echo "  Backup Directory: /var/www/backups"
echo "  Websites Directory: /var/www/websites"
echo ""
print_info "Important Next Steps"
echo "  1. Set up SSL certificate:"
echo "     sudo certbot --nginx -d $SERVER_DOMAIN"
echo ""
echo "  2. Configure firewall:"
echo "     sudo ufw allow 80/tcp"
echo "     sudo ufw allow 443/tcp"
echo "     sudo ufw allow 53/tcp"
echo "     sudo ufw allow 53/udp"
echo "     sudo ufw enable"
echo ""
echo "  3. Check backup worker status:"
echo "     sudo supervisorctl status webadmin-backup-worker"
echo ""
echo "  4. View logs:"
echo "     - Access: $INSTALL_DIR/logs/nginx-access.log"
echo "     - Error: $INSTALL_DIR/logs/nginx-error.log"
echo "     - Backup Worker: /var/log/supervisor/webadmin-backup-worker.out.log"
echo ""
print_info "For support and documentation, visit the project repository."
echo ""
print_success "Installation completed! You can now access WebAdmin."