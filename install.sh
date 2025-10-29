#!/bin/bash

#############################################
# WebAdmin Installer
# For Ubuntu 22.04 and higher
#############################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored messages
print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }
print_info() { echo -e "${YELLOW}➜ $1${NC}"; }

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root (use sudo)"
   exit 1
fi

# Check Ubuntu version
print_info "Checking Ubuntu version..."
if [ -f /etc/os-release ]; then
    . /etc/os-release
    if [ "$ID" != "ubuntu" ]; then
        print_error "This installer is designed for Ubuntu only"
        exit 1
    fi
    
    VERSION_MAJOR=$(echo $VERSION_ID | cut -d. -f1)
    if [ "$VERSION_MAJOR" -lt 22 ]; then
        print_error "Ubuntu 22.04 or higher is required (you have $VERSION_ID)"
        exit 1
    fi
    print_success "Ubuntu $VERSION_ID detected"
else
    print_error "Cannot detect OS version"
    exit 1
fi

# Get configuration from user
print_info "WebAdmin Installation Setup"
echo ""

read -p "Enter installation directory [/websites/webadmin]: " INSTALL_DIR
INSTALL_DIR=${INSTALL_DIR:-/websites/webadmin}

read -p "Enter MySQL root password: " -s MYSQL_ROOT_PASS
echo ""
read -p "Enter new database name [webadmin]: " DB_NAME
DB_NAME=${DB_NAME:-webadmin}

read -p "Enter new database user [webadmin]: " DB_USER
DB_USER=${DB_USER:-webadmin}

read -p "Enter new database password: " -s DB_PASS
echo ""

read -p "Enter admin username for WebAdmin: " ADMIN_USER
read -p "Enter admin email: " ADMIN_EMAIL
read -p "Enter admin password: " -s ADMIN_PASS
echo ""

# Confirm installation
echo ""
print_info "Installation Summary:"
echo "  Installation Directory: $INSTALL_DIR"
echo "  Database Name: $DB_NAME"
echo "  Database User: $DB_USER"
echo ""
read -p "Continue with installation? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_error "Installation cancelled"
    exit 1
fi

# Update system
print_info "Updating system packages..."
apt update && apt upgrade -y
print_success "System updated"

# Install required packages
print_info "Installing required packages..."
apt install -y \
    nginx \
    php8.3-fpm \
    php8.3-cli \
    php8.3-mysql \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-zip \
    mariadb-server \
    mariadb-client \
    pdns-server \
    pdns-backend-mysql \
    supervisor \
    certbot \
    python3-certbot-nginx \
    tar \
    gzip
print_success "Packages installed"

# Create installation directory
print_info "Creating installation directory..."
mkdir -p $INSTALL_DIR
mkdir -p $INSTALL_DIR/logs
mkdir -p /websites/backups
print_success "Directories created"

# Copy application files
print_info "Copying application files..."
cp -r $(dirname "$0")/app/* $INSTALL_DIR/
print_success "Application files copied"

# Copy and configure config file
print_info "Configuring application..."
cp $(dirname "$0")/config/config.php.template $INSTALL_DIR/config/config.php
sed -i "s/{{DB_HOST}}/127.0.0.1/g" $INSTALL_DIR/config/config.php
sed -i "s/{{DB_PORT}}/3306/g" $INSTALL_DIR/config/config.php
sed -i "s/{{DB_NAME}}/$DB_NAME/g" $INSTALL_DIR/config/config.php
sed -i "s/{{DB_USER}}/$DB_USER/g" $INSTALL_DIR/config/config.php
sed -i "s/{{DB_PASS}}/$DB_PASS/g" $INSTALL_DIR/config/config.php
sed -i "s|{{INSTALL_DIR}}|$INSTALL_DIR|g" $INSTALL_DIR/config/config.php
print_success "Configuration created"

# Set permissions
print_info "Setting permissions..."
chown -R www-data:www-data $INSTALL_DIR
chown -R www-data:www-data /websites/backups
chmod -R 755 $INSTALL_DIR
chmod -R 775 $INSTALL_DIR/logs
chmod -R 775 /websites/backups
print_success "Permissions set"

# Configure MySQL
print_info "Creating database..."
mysql -u root -p"$MYSQL_ROOT_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
print_success "Database created"

# Import schema
print_info "Importing database schema..."
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME < $(dirname "$0")/database/schema.sql
print_success "Schema imported"

# Create admin user
print_info "Creating admin user..."
ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);")
mysql -u root -p"$MYSQL_ROOT_PASS" $DB_NAME <<EOF
INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES ('$ADMIN_USER', '$ADMIN_EMAIL', '$ADMIN_HASH', 'Administrator', 'admin', 1);
EOF
print_success "Admin user created"

# Configure Nginx
print_info "Configuring Nginx..."
cp $(dirname "$0")/config/nginx-site.conf /etc/nginx/sites-available/webadmin
sed -i "s|{{INSTALL_DIR}}|$INSTALL_DIR|g" /etc/nginx/sites-available/webadmin
ln -sf /etc/nginx/sites-available/webadmin /etc/nginx/sites-enabled/webadmin
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
print_success "Nginx configured"

# Configure sudoers
print_info "Configuring sudoers for www-data..."
cp $(dirname "$0")/config/sudoers-webadmin /etc/sudoers.d/webadmin
chmod 440 /etc/sudoers.d/webadmin
print_success "Sudoers configured"

# Configure Supervisor for backup worker
print_info "Configuring Supervisor backup worker..."
cp $(dirname "$0")/config/backup-worker.conf /etc/supervisor/conf.d/backup-worker.conf
sed -i "s|{{INSTALL_DIR}}|$INSTALL_DIR|g" /etc/supervisor/conf.d/backup-worker.conf
supervisorctl reread
supervisorctl update
supervisorctl start backup-worker
print_success "Backup worker configured and started"

# Configure PowerDNS
print_info "Configuring PowerDNS..."
cat > /etc/powerdns/pdns.d/mysql.conf <<EOF
launch=gmysql
gmysql-host=127.0.0.1
gmysql-port=3306
gmysql-dbname=$DB_NAME
gmysql-user=$DB_USER
gmysql-password=$DB_PASS
gmysql-dnssec=yes
EOF
systemctl restart pdns
print_success "PowerDNS configured"

# Enable services
print_info "Enabling services..."
systemctl enable nginx php8.3-fpm mariadb pdns supervisor
systemctl start nginx php8.3-fpm mariadb pdns supervisor
print_success "Services enabled and started"

# Installation complete
echo ""
echo "═══════════════════════════════════════════════════════"
print_success "WebAdmin installation completed successfully!"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "Access your WebAdmin panel at: http://$(hostname -I | awk '{print $1}')"
echo ""
echo "Login credentials:"
echo "  Username: $ADMIN_USER"
echo "  Password: (the password you entered)"
echo ""
echo "Important next steps:"
echo "  1. Configure SSL certificate with certbot"
echo "  2. Review and customize /etc/nginx/sites-available/webadmin"
echo "  3. Check backup worker: sudo supervisorctl status backup-worker"
echo "  4. Secure your MySQL installation: sudo mysql_secure_installation"
echo ""
print_info "Installation log saved to: /var/log/webadmin-install.log"
