# WebAdmin - Web Server Management Panel

A comprehensive web-based control panel for managing Nginx websites, MySQL databases, PowerDNS zones, and system services on Ubuntu servers.

## Features

- **Website Management**: Create, configure, and manage Nginx virtual hosts
- **SSL Certificates**: Automated Let's Encrypt SSL certificate management with Certbot
- **Database Management**: Create, manage, and link MySQL databases to websites
- **DNS Management**: Full PowerDNS zone and record management
- **Backup System**: 
  - Async queue-based backup processing
  - Backup websites, databases, and DNS zones
  - Local and SFTP storage destinations
  - Scheduled backups with cron expressions
  - Real-time progress tracking
- **CMS Installers**: One-click WordPress and OpenCart installation
- **Service Control**: Manage Nginx, PHP-FPM, MySQL, and PowerDNS services
- **User Management**: Role-based access control (admin/user)
- **Logs Viewer**: Real-time access and error log viewing

## System Requirements

- **Operating System**: Ubuntu 22.04 LTS or higher
- **RAM**: Minimum 2GB (4GB recommended)
- **Disk Space**: Minimum 10GB free space
- **Network**: Internet connection for package installation

## Quick Installation

### 1. Download the Installer

```bash
# Download and extract (adjust URL to your distribution method)
cd /tmp
# wget https://example.com/webadmin-installer.tar.gz
# tar -xzf webadmin-installer.tar.gz
# cd webadmin-installer
```

### 2. Run the Installation Script

```bash
sudo chmod +x install.sh
sudo ./install.sh
```

The installer will:
1. Check Ubuntu version compatibility
2. Prompt for installation directory and database credentials
3. Install all required packages
4. Configure Nginx, PHP-FPM, MariaDB, PowerDNS, and Supervisor
5. Import the database schema
6. Create the admin user
7. Set up the backup worker daemon
8. Configure system permissions

### 3. Access WebAdmin

After installation, access your panel at:
```
http://your-server-ip
```

Login with the credentials you created during installation.

## Manual Installation

If you prefer manual installation or need to customize the setup:

### Install Required Packages

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y \
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
    python3-certbot-nginx
```

### Create Database

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE webadmin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'webadmin'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON webadmin.* TO 'webadmin'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Import Schema

```bash
sudo mysql -u root -p webadmin < database/schema.sql
```

### Copy Application Files

```bash
sudo mkdir -p /websites/webadmin
sudo cp -r app/* /websites/webadmin/
sudo mkdir -p /websites/webadmin/logs
sudo mkdir -p /websites/backups
```

### Configure Application

```bash
sudo cp config/config.php.template /websites/webadmin/config/config.php
```

Edit `/websites/webadmin/config/config.php` and update database credentials.

### Configure Nginx

```bash
sudo cp config/nginx-site.conf /etc/nginx/sites-available/webadmin
sudo sed -i 's|{{INSTALL_DIR}}|/websites/webadmin|g' /etc/nginx/sites-available/webadmin
sudo ln -s /etc/nginx/sites-available/webadmin /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### Configure Sudoers

```bash
sudo cp config/sudoers-webadmin /etc/sudoers.d/webadmin
sudo chmod 440 /etc/sudoers.d/webadmin
```

### Configure Supervisor

```bash
sudo cp config/backup-worker.conf /etc/supervisor/conf.d/
sudo sed -i 's|{{INSTALL_DIR}}|/websites/webadmin|g' /etc/supervisor/conf.d/backup-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start backup-worker
```

### Configure PowerDNS

```bash
sudo tee /etc/powerdns/pdns.d/mysql.conf > /dev/null <<EOF
launch=gmysql
gmysql-host=127.0.0.1
gmysql-port=3306
gmysql-dbname=webadmin
gmysql-user=webadmin
gmysql-password=your-password
gmysql-dnssec=yes
EOF

sudo systemctl restart pdns
```

### Create Admin User

```bash
php -r "echo password_hash('your-password', PASSWORD_BCRYPT);" > /tmp/hash.txt
```

```bash
sudo mysql -u root -p webadmin
```

```sql
INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES ('admin', 'admin@example.com', 'paste-hash-here', 'Administrator', 'admin', 1);
```

### Set Permissions

```bash
sudo chown -R www-data:www-data /websites/webadmin
sudo chown -R www-data:www-data /websites/backups
sudo chmod -R 755 /websites/webadmin
sudo chmod -R 775 /websites/webadmin/logs
sudo chmod -R 775 /websites/backups
```

## Post-Installation

### Secure MySQL

```bash
sudo mysql_secure_installation
```

### Configure Firewall

```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 53/tcp
sudo ufw allow 53/udp
sudo ufw enable
```

### Set Up SSL (Recommended)

After installation, you can configure SSL for the WebAdmin panel:

1. Update `/etc/nginx/sites-available/webadmin` with your domain name
2. Run: `sudo certbot --nginx -d your-domain.com`

### Configure Backup Destinations

1. Log in to WebAdmin
2. Navigate to Backups → Backup Destinations
3. Create a backup destination (local or SFTP)
4. Set as default if desired

### Monitor Services

```bash
# Check backup worker status
sudo supervisorctl status backup-worker

# View backup worker logs
sudo tail -f /var/log/supervisor/backup-worker.out.log

# Check Nginx status
sudo systemctl status nginx

# Check PHP-FPM status
sudo systemctl status php8.3-fpm

# Check PowerDNS status
sudo systemctl status pdns
```

## Architecture

### Async Backup System

WebAdmin uses a sophisticated async backup system:

- **Queue-based**: Backups are queued and processed by a background worker
- **Supervisor-managed**: The backup worker runs as a daemon, auto-restarts on failure
- **No timeouts**: Backups can run for hours without HTTP timeout issues
- **Progress tracking**: Real-time progress updates stored in database
- **Multiple backup types**: Sites, databases, DNS zones, or mixed
- **Flexible storage**: Local filesystem or SFTP remote storage

### Security

- **Sudo permissions**: Minimal, scoped sudo access for www-data user
- **Password hashing**: BCrypt password hashing for user accounts
- **Session management**: Secure session handling with expiration
- **Role-based access**: Admin and user roles with different permissions
- **Input validation**: Server-side validation for all user inputs

## Troubleshooting

### Backup Worker Not Running

```bash
sudo supervisorctl status backup-worker
sudo supervisorctl start backup-worker
sudo tail -f /var/log/supervisor/backup-worker.err.log
```

### Nginx Configuration Errors

```bash
sudo nginx -t
sudo tail -f /var/log/nginx/error.log
```

### Database Connection Issues

Check credentials in `/websites/webadmin/config/config.php`

```bash
mysql -u webadmin -p webadmin
```

### Permission Errors

```bash
sudo chown -R www-data:www-data /websites/webadmin
sudo chmod -R 755 /websites/webadmin
```

## Backup & Restore

### Backup WebAdmin

```bash
# Backup database
mysqldump -u root -p webadmin > webadmin-backup.sql

# Backup application files
tar -czf webadmin-files.tar.gz /websites/webadmin

# Backup Nginx config
cp /etc/nginx/sites-available/webadmin /path/to/backup/
```

### Restore WebAdmin

```bash
# Restore database
mysql -u root -p webadmin < webadmin-backup.sql

# Restore files
tar -xzf webadmin-files.tar.gz -C /

# Restore Nginx config
cp /path/to/backup/webadmin /etc/nginx/sites-available/
sudo nginx -t && sudo systemctl reload nginx
```

## Updating

To update WebAdmin:

1. Backup your current installation
2. Download the new version
3. Stop services:
   ```bash
   sudo supervisorctl stop backup-worker
   ```
4. Replace application files (preserve config.php)
5. Run any new database migrations
6. Restart services:
   ```bash
   sudo supervisorctl start backup-worker
   sudo systemctl reload nginx php8.3-fpm
   ```

## Uninstallation

To completely remove WebAdmin:

```bash
# Stop and disable services
sudo supervisorctl stop backup-worker
sudo rm /etc/supervisor/conf.d/backup-worker.conf
sudo supervisorctl reread && sudo supervisorctl update

# Remove Nginx config
sudo rm /etc/nginx/sites-enabled/webadmin
sudo rm /etc/nginx/sites-available/webadmin
sudo systemctl reload nginx

# Remove sudoers file
sudo rm /etc/sudoers.d/webadmin

# Remove application files
sudo rm -rf /websites/webadmin

# Drop database
sudo mysql -u root -p -e "DROP DATABASE webadmin; DROP USER 'webadmin'@'localhost';"
```

## Support

For issues, questions, or contributions:
- Check the troubleshooting section above
- Review log files in `/websites/webadmin/logs/`
- Check Supervisor logs: `/var/log/supervisor/backup-worker.*.log`
- Check Nginx logs: `/var/log/nginx/`

## License

[Your License Here]

## Credits

Built with modern web technologies:
- PHP 8.3
- MariaDB
- Nginx
- PowerDNS
- Supervisor
