# WebAdmin - Complete Web Server Management Panel# WebAdmin - Web Server Management Panel



![WebAdmin](https://img.shields.io/badge/Version-1.0-blue) ![Ubuntu](https://img.shields.io/badge/Ubuntu-22.04+-orange) ![PHP](https://img.shields.io/badge/PHP-8.3-purple) ![License](https://img.shields.io/badge/License-MIT-green)A comprehensive web-based control panel for managing Nginx websites, MySQL databases, PowerDNS zones, and system services on Ubuntu servers.



A comprehensive web-based control panel for managing Nginx websites, MySQL databases, PowerDNS zones, and system services on Ubuntu servers. Built with modern PHP 8.3, featuring an async backup system and intuitive web interface.## Features



## 🚀 Features- **Website Management**: Create, configure, and manage Nginx virtual hosts

- **PHP Configuration**: Per-site PHP settings (version, memory limit, upload size, execution time, custom directives)

### Website Management- **Varnish Cache**: Optional Varnish cache integration with customizable cache rules

- **Nginx Virtual Hosts**: Create and manage multiple websites- **SSL Certificates**: Automated Let's Encrypt SSL certificate management with Certbot

- **SSL Certificates**: Automated Let's Encrypt integration with Certbot- **Cron Jobs**: Create and manage scheduled tasks per website with custom schedules

- **PHP Configuration**: Per-site PHP-FPM configuration- **Database Management**: Create, manage, and link MySQL databases to websites

- **Static & Dynamic Sites**: Support for HTML, PHP, WordPress, OpenCart- **DNS Management**: Full PowerDNS zone and record management

- **File Manager**: 

### Database Management  - Browse and navigate server directories

- **MySQL/MariaDB**: Create and manage databases  - Create, edit, and delete files and folders

- **User Management**: Database user creation and permissions  - Upload and download files

- **Site Linking**: Link databases to specific websites  - Download folders as ZIP archives

- **Backup Integration**: Automatic database backup support  - Built-in code editor with syntax highlighting (Ace Editor)

  - Support for 25+ file types (PHP, JS, CSS, HTML, JSON, YAML, etc.)

### DNS Management    - File permissions and ownership management

- **PowerDNS Integration**: Full DNS zone management  - File search functionality

- **Record Types**: A, AAAA, CNAME, MX, TXT, NS, PTR records- **Backup System**: 

- **Zone Management**: Create, edit, and delete DNS zones  - Async queue-based backup processing

- **DNSSEC Support**: Built-in DNSSEC capabilities  - Backup websites, databases, and DNS zones

  - Local and SFTP storage destinations

### Advanced Backup System  - Scheduled backups with cron expressions

- **Async Processing**: Queue-based backup system with Supervisor  - Real-time progress tracking

- **Multiple Types**: Website files, databases, DNS zones, or mixed- **CMS Installers**: One-click WordPress and OpenCart installation

- **Storage Options**: Local filesystem or SFTP remote storage- **Service Control**: Manage Nginx, PHP-FPM, MySQL, and PowerDNS services

- **Real-time Progress**: Live backup progress tracking with visual indicators- **User Management**: Role-based access control (admin/user)

- **Scheduled Backups**: Cron-based automatic backup scheduling- **Logs Viewer**: Real-time access and error log viewing

- **No Timeouts**: Long-running backups without HTTP timeout issues

## System Requirements

### Content Management Systems

- **WordPress Installer**: One-click WordPress installation and setup- **Operating System**: Ubuntu 22.04 LTS or higher

- **OpenCart Installer**: Automated e-commerce platform deployment- **RAM**: Minimum 2GB (4GB recommended)

- **Database Creation**: Automatic database and user creation- **Disk Space**: Minimum 10GB free space

- **Configuration**: Pre-configured settings and admin accounts- **Network**: Internet connection for package installation



### System Management  ## Quick Installation

- **Service Control**: Manage Nginx, PHP-FPM, MySQL, PowerDNS

- **Log Viewing**: Real-time access and error log monitoring### 1. Download the Installer

- **User Management**: Role-based access control (admin/user)

- **Security**: Scoped sudo permissions and secure session handling```bash

# Download and extract (adjust URL to your distribution method)

## 📋 System Requirements

- **Operating System**: Ubuntu 22.04 LTS or higher# tar -xzf webadmin-installer.tar.gz

- **Memory**: Minimum 2GB RAM (4GB recommended for multiple sites)# cd webadmin-installer

- **Storage**: Minimum 10GB free disk space```

- **Network**: Internet connection for package installation and updates

- **Access**: Root/sudo privileges for installation### 2. Run the Installation Script



## 🛠 Quick Installation

### 1. Download the Installer

```bash
# Clone from GitHub
git clone https://github.com/andrewtimmins/hosting-panel.git
cd hosting-panel

# Or download and extract zip
wget https://github.com/andrewtimmins/hosting-panel/archive/main.zip
unzip main.zip
cd hosting-panel-main
```

### 2. Run the Installation Script

```bash
# Make the installer executable
chmod +x install.sh

# Run the installer as root
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

# Run the installer as root

sudo ./install.shLogin with the credentials you created during installation.

```

## Manual Installation

### 3. Follow the Setup Wizard

If you prefer manual installation or need to customize the setup:

The installer will prompt you for:

- **Installation directory** (default: `/var/www/webadmin`)### Install Required Packages

- **Database configuration** (name, user, password)

- **Admin account details** (username, email, password)```bash

- **Server domain/IP** for accesssudo apt update && sudo apt upgrade -y



### 4. Access WebAdminsudo apt install -y \

    nginx \

After installation completes:    php8.3-fpm \

```    php8.3-cli \

http://your-server-ip    php8.3-mysql \

```    php8.3-mbstring \

    php8.3-xml \

Log in with the admin credentials you created during installation.    php8.3-curl \

    php8.3-zip \

## 📖 What Gets Installed    mariadb-server \

    mariadb-client \

### Software Packages    pdns-server \

- **Nginx**: Web server and reverse proxy    pdns-backend-mysql \

- **PHP 8.3**: With FPM, MySQL, curl, zip, gd, intl extensions    supervisor \

- **MariaDB**: MySQL-compatible database server    certbot \

- **PowerDNS**: Authoritative DNS server with MySQL backend    python3-certbot-nginx \

- **Supervisor**: Process manager for backup worker daemon    varnish \

- **Certbot**: Let's Encrypt SSL certificate automation    zip \

- **System Tools**: tar, gzip, curl, wget, unzip    unzip \

    cron

### Directory Structure```

```

/var/www/webadmin/          # Main application### Create Database

├── config/                 # Configuration files

├── assets/                 # CSS, JS, images```bash

├── src/                    # PHP classes and servicessudo mysql -u root -p

├── templates/              # Nginx configuration templates```

├── logs/                   # Application logs

├── *.php                   # Main application files```sql

└── backup-worker.php       # Async backup daemonCREATE DATABASE webadmin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'webadmin'@'localhost' IDENTIFIED BY 'your-password';

/var/www/websites/          # Website filesGRANT ALL PRIVILEGES ON webadmin.* TO 'webadmin'@'localhost';

/var/www/backups/           # Backup storageFLUSH PRIVILEGES;

```EXIT;

```

### Database Schema

- **User Management**: users, user_sessions tables### Import Schema

- **Website Management**: sites, site_configurations, site_databases

- **DNS Management**: domains, records (PowerDNS schema)```bash

- **Backup System**: backup_destinations, backup_history, backup_queuesudo mysql -u root -p webadmin < database/schema.sql

- **System**: settings, actions_log tables```



### System Configuration### Copy Application Files

- **Nginx**: Virtual host for WebAdmin panel

- **PHP-FPM**: Optimized for web applications```bash

- **Sudoers**: Scoped permissions for www-data usersudo mkdir -p /websites/webadmin

- **Supervisor**: Backup worker daemon managementsudo cp -r app/* /websites/webadmin/

- **PowerDNS**: MySQL backend configurationsudo mkdir -p /websites/webadmin/logs

- **SSL**: Ready for Let's Encrypt certificatessudo mkdir -p /websites/backups

```

## 🔧 Post-Installation Setup

### Configure Application

### 1. Configure SSL (Recommended)

```bash

```bashsudo cp config/config.php.template /websites/webadmin/config/config.php

# For domain-based access```

sudo certbot --nginx -d your-domain.com

Edit `/websites/webadmin/config/config.php` and update database credentials.

# The installer configures SSL-ready Nginx settings

```### Configure Nginx



### 2. Configure Firewall```bash

sudo cp config/nginx-site.conf /etc/nginx/sites-available/webadmin

```bashsudo sed -i 's|{{INSTALL_DIR}}|/websites/webadmin|g' /etc/nginx/sites-available/webadmin

# Allow web trafficsudo ln -s /etc/nginx/sites-available/webadmin /etc/nginx/sites-enabled/

sudo ufw allow 80/tcpsudo rm -f /etc/nginx/sites-enabled/default

sudo ufw allow 443/tcpsudo nginx -t && sudo systemctl reload nginx

```

# Allow DNS traffic

sudo ufw allow 53/tcp### Configure Sudoers

sudo ufw allow 53/udp

```bash

# Enable firewallsudo cp config/sudoers-webadmin /etc/sudoers.d/webadmin

sudo ufw enablesudo chmod 440 /etc/sudoers.d/webadmin

``````



### 3. Set Up Backup Destinations### Configure Supervisor



1. Log into WebAdmin```bash

2. Navigate to **Backups → Destinations**sudo cp config/backup-worker.conf /etc/supervisor/conf.d/

3. Configure additional storage locations:sudo sed -i 's|{{INSTALL_DIR}}|/websites/webadmin|g' /etc/supervisor/conf.d/backup-worker.conf

   - **Local**: Different paths for organizationsudo supervisorctl reread

   - **SFTP**: Remote server backup storagesudo supervisorctl update

sudo supervisorctl start backup-worker

### 4. Create Your First Website```



1. Go to **Websites → Add Website**### Configure PowerDNS

2. Enter domain name and configuration

3. Upload files or install WordPress/OpenCart```bash

4. Configure SSL certificatesudo tee /etc/powerdns/pdns.d/mysql.conf > /dev/null <<EOF

5. Link database if neededlaunch=gmysql

gmysql-host=127.0.0.1

## 🔍 Monitoring and Maintenancegmysql-port=3306

gmysql-dbname=webadmin

### Check System Statusgmysql-user=webadmin

gmysql-password=your-password

```bashgmysql-dnssec=yes

# All services statusEOF

sudo systemctl status nginx php8.3-fpm mariadb pdns supervisor

sudo systemctl restart pdns

# Backup worker status```

sudo supervisorctl status webadmin-backup-worker

### Create Admin User

# View backup worker logs

sudo tail -f /var/log/supervisor/webadmin-backup-worker.out.log```bash

```php -r "echo password_hash('your-password', PASSWORD_BCRYPT);" > /tmp/hash.txt

```

### Log Locations

```bash

- **WebAdmin Access**: `/var/www/webadmin/logs/nginx-access.log`sudo mysql -u root -p webadmin

- **WebAdmin Errors**: `/var/www/webadmin/logs/nginx-error.log````

- **Backup Worker**: `/var/log/supervisor/webadmin-backup-worker.*.log`

- **Nginx**: `/var/log/nginx/````sql

- **PHP-FPM**: `/var/log/php8.3-fpm.log`INSERT INTO users (username, email, password_hash, full_name, role, is_active)

- **MariaDB**: `/var/log/mysql/`VALUES ('admin', 'admin@example.com', 'paste-hash-here', 'Administrator', 'admin', 1);

```

### Database Access

### Set Permissions

```bash

# Connect to WebAdmin database```bash

mysql -u your_db_user -p your_db_namesudo chown -R www-data:www-data /websites/webadmin

sudo chown -R www-data:www-data /websites/backups

# Or as rootsudo chmod -R 755 /websites/webadmin

sudo mysql your_db_namesudo chmod -R 775 /websites/webadmin/logs

```sudo chmod -R 775 /websites/backups

```

## 🛡 Security Features

## Post-Installation

### Access Control

- **Role-based permissions**: Admin and user roles### Secure MySQL

- **Session management**: Secure session handling with expiration

- **Password hashing**: BCrypt with proper salt```bash

sudo mysql_secure_installation

### System Security```

- **Scoped sudo**: Minimal required permissions for www-data

- **Input validation**: Server-side validation for all inputs### Configure Firewall

- **SQL injection protection**: PDO prepared statements

- **XSS protection**: Output escaping and security headers```bash

sudo ufw allow 80/tcp

### File Securitysudo ufw allow 443/tcp

- **Permission management**: Proper file and directory permissionssudo ufw allow 53/tcp

- **Backup security**: Secure temporary file handlingsudo ufw allow 53/udp

- **Configuration protection**: Protected config filessudo ufw enable

```

## 🔄 Backup System Architecture

### Set Up SSL (Recommended)

### Async Processing

- **Queue-based**: Jobs queued in database, processed by background workerAfter installation, you can configure SSL for the WebAdmin panel:

- **Supervisor-managed**: Auto-restart on failure, persistent daemon

- **No HTTP timeouts**: Backups run independently of web requests1. Update `/etc/nginx/sites-available/webadmin` with your domain name

- **Progress tracking**: Real-time status updates with visual progress2. Run: `sudo certbot --nginx -d your-domain.com`



### Backup Types### Configure Backup Destinations

- **Website Files**: Complete file system backup with tar/gzip

- **Databases**: MySQL dumps with compression1. Log in to WebAdmin

- **DNS Zones**: PowerDNS zone exports2. Navigate to Backups → Backup Destinations

- **Mixed Backups**: Combined websites, databases, and DNS3. Create a backup destination (local or SFTP)

4. Set as default if desired

### Storage Options

- **Local Storage**: Filesystem paths with configurable locations### Monitor Services

- **SFTP Storage**: Remote server backup with authentication

- **Retention Policies**: Automatic cleanup of old backups```bash

# Check backup worker status

## 🚨 Troubleshootingsudo supervisorctl status backup-worker



### Installation Issues# View backup worker logs

sudo tail -f /var/log/supervisor/backup-worker.out.log

**Package installation fails**:

```bash# Check Nginx status

sudo apt updatesudo systemctl status nginx

sudo apt upgrade

# Re-run installer# Check PHP-FPM status

```sudo systemctl status php8.3-fpm



**Database connection issues**:# Check PowerDNS status

```bashsudo systemctl status pdns

# Check MariaDB status```

sudo systemctl status mariadb

## Architecture

# Reset root password

sudo mysql_secure_installation### PHP Configuration Management

```

WebAdmin allows granular PHP configuration per website:

### Runtime Issues

- **Version Selection**: Choose PHP version per site (supports multiple PHP-FPM versions)

**Nginx configuration errors**:- **Memory Limits**: Configure memory_limit (e.g., 256M, 512M, 1G)

```bash- **Upload Settings**: Set upload_max_filesize and post_max_size independently

# Test configuration- **Execution Times**: Configure max_execution_time and max_input_time

sudo nginx -t- **Custom Directives**: Add any PHP INI settings as JSON (e.g., opcache settings, error reporting)

- **Pool Configuration**: Generates dedicated PHP-FPM pool config per site

# Check error logs

sudo tail -f /var/log/nginx/error.log### Varnish Cache Integration

```

Optional Varnish cache support for high-performance websites:

**Backup worker not running**:

```bash- **Cache Rules**: Define what to cache (static files, pages, etc.)

# Check supervisor status- **Bypass Rules**: Exclude admin areas, login pages, cookies

sudo supervisorctl status webadmin-backup-worker- **TTL Configuration**: Set cache lifetime per content type

- **Cache Purging**: Manual cache clearing per site

# Restart worker- **Backend Integration**: Seamless Nginx → Varnish → PHP-FPM setup

sudo supervisorctl restart webadmin-backup-worker

### Cron Job Management

# Check error logs

sudo tail -f /var/log/supervisor/webadmin-backup-worker.err.logBuilt-in cron job scheduler for automated tasks:

```

- **Per-Site Jobs**: Create cron jobs specific to each website

**Permission errors**:- **Flexible Scheduling**: Standard cron expressions (minute, hour, day, month, weekday)

```bash- **User Context**: Run jobs as specific system users

# Reset permissions- **Execution Tracking**: Last run time, next run time, exit codes

sudo chown -R www-data:www-data /var/www/webadmin- **Output Logging**: Capture and store command output

sudo chown -R www-data:www-data /var/www/websites- **Enable/Disable**: Toggle jobs without deleting them

sudo chown -R www-data:www-data /var/www/backups

sudo chmod -R 755 /var/www/webadmin### File Manager

sudo chmod -R 775 /var/www/websites

sudo chmod -R 775 /var/www/backupsThe File Manager provides a comprehensive interface for managing server files:

```

- **Modern UI**: Clean, responsive interface with breadcrumb navigation

### Performance Issues- **Code Editor**: Integrated Ace Editor with syntax highlighting for 20+ programming languages

- **Security**: Path traversal prevention and file extension validation

**High memory usage**:- **File Operations**: Full CRUD support with ZIP compression for folders

- Increase server RAM or optimize PHP-FPM settings- **Owner Display**: Shows file ownership (user:group) and permissions

- Check for large backup operations- **Type Detection**: Automatic syntax mode detection based on file extensions



**Slow backup operations**:Supported file types for editing:

- Check disk I/O and available space- Web: HTML, CSS, JavaScript, TypeScript, JSX, TSX, Vue, SCSS, LESS, SVG

- Consider backup scheduling during off-peak hours- Server: PHP, Python, Ruby, Shell scripts

- Config: JSON, YAML, XML, INI, ENV, .htaccess

## 🔄 Updates and Maintenance- Data: SQL, CSV, Markdown, Log files



### Updating WebAdmin### Async Backup System



1. **Backup current installation**:WebAdmin uses a sophisticated async backup system:

   ```bash

   # Backup database- **Queue-based**: Backups are queued and processed by a background worker

   mysqldump -u root -p your_db_name > webadmin-backup.sql- **Supervisor-managed**: The backup worker runs as a daemon, auto-restarts on failure

   - **No timeouts**: Backups can run for hours without HTTP timeout issues

   # Backup files- **Progress tracking**: Real-time progress updates stored in database

   tar -czf webadmin-files-backup.tar.gz /var/www/webadmin- **Multiple backup types**: Sites, databases, DNS zones, or mixed

   ```- **Flexible storage**: Local filesystem or SFTP remote storage



2. **Download and apply updates**:### Security

   ```bash

   # Stop backup worker- **Sudo permissions**: Minimal, scoped sudo access for www-data user

   sudo supervisorctl stop webadmin-backup-worker- **Password hashing**: BCrypt password hashing for user accounts

   - **Session management**: Secure session handling with expiration

   # Apply updates (preserve config.php)- **Role-based access**: Admin and user roles with different permissions

   # Follow specific update instructions for each version- **Input validation**: Server-side validation for all user inputs

   

   # Restart services## Troubleshooting

   sudo supervisorctl start webadmin-backup-worker

   sudo systemctl reload nginx php8.3-fpm### Backup Worker Not Running

   ```

```bash

### System Maintenancesudo supervisorctl status backup-worker

sudo supervisorctl start backup-worker

**Regular tasks**:sudo tail -f /var/log/supervisor/backup-worker.err.log

- Monitor disk space in `/var/www/backups````

- Check log file sizes and rotate if needed

- Update system packages: `sudo apt update && sudo apt upgrade`### Nginx Configuration Errors

- Review backup success rates and storage usage

```bash

## 🗑 Uninstallationsudo nginx -t

sudo tail -f /var/log/nginx/error.log

To completely remove WebAdmin:```



```bash### Database Connection Issues

# Stop services

sudo supervisorctl stop webadmin-backup-workerCheck credentials in `/websites/webadmin/config/config.php`

sudo rm /etc/supervisor/conf.d/webadmin-backup-worker.conf

sudo supervisorctl reread && sudo supervisorctl update```bash

mysql -u webadmin -p webadmin

# Remove Nginx configuration```

sudo rm /etc/nginx/sites-enabled/webadmin

sudo rm /etc/nginx/sites-available/webadmin### Permission Errors

sudo systemctl reload nginx

```bash

# Remove sudo permissionssudo chown -R www-data:www-data /websites/webadmin

sudo rm /etc/sudoers.d/webadminsudo chmod -R 755 /websites/webadmin

```

# Remove application files

sudo rm -rf /var/www/webadmin## Backup & Restore



# Remove database (optional)### Backup WebAdmin

sudo mysql -u root -p -e "DROP DATABASE your_db_name; DROP USER 'your_db_user'@'localhost';"

```bash

# Remove PowerDNS configuration# Backup database

sudo rm /etc/powerdns/pdns.d/mysql.confmysqldump -u root -p webadmin > webadmin-backup.sql

sudo systemctl restart pdns

```# Backup application files

tar -czf webadmin-files.tar.gz /websites/webadmin

## 🤝 Contributing

# Backup Nginx config

We welcome contributions! Please see our contributing guidelines for:cp /etc/nginx/sites-available/webadmin /path/to/backup/

- Code style and standards```

- Pull request process

- Issue reporting### Restore WebAdmin

- Feature requests

```bash

## 📄 License# Restore database

mysql -u root -p webadmin < webadmin-backup.sql

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

# Restore files

## 🆘 Supporttar -xzf webadmin-files.tar.gz -C /



### Getting Help# Restore Nginx config

cp /path/to/backup/webadmin /etc/nginx/sites-available/

- **Documentation**: Check this README and inline helpsudo nginx -t && sudo systemctl reload nginx

- **Logs**: Review system and application logs```

- **Community**: GitHub issues and discussions

- **Professional Support**: Available for enterprise deployments## Updating



### Reporting IssuesTo update WebAdmin:



When reporting issues, please include:1. Backup your current installation

- Ubuntu version and system specifications2. Download the new version

- WebAdmin version3. Stop services:

- Error messages and logs   ```bash

- Steps to reproduce the issue   sudo supervisorctl stop backup-worker

- Screenshots if relevant   ```

4. Replace application files (preserve config.php)

## 🏗 Built With5. Run any new database migrations

6. Restart services:

- **PHP 8.3**: Modern PHP with type safety and performance   ```bash

- **Nginx**: High-performance web server   sudo supervisorctl start backup-worker

- **MariaDB**: Reliable MySQL-compatible database   sudo systemctl reload nginx php8.3-fpm

- **PowerDNS**: Authoritative DNS server   ```

- **Supervisor**: Process control system

- **Bootstrap CSS**: Responsive web interface## Uninstallation

- **JavaScript**: Modern ES6+ for dynamic interfaces

To completely remove WebAdmin:

## 🎯 Roadmap

```bash

### Planned Features# Stop and disable services

- **API Integration**: RESTful API for external integrationssudo supervisorctl stop backup-worker

- **Multi-server Management**: Manage multiple servers from one panelsudo rm /etc/supervisor/conf.d/backup-worker.conf

- **Enhanced Monitoring**: System metrics and alertingsudo supervisorctl reread && sudo supervisorctl update

- **Plugin System**: Extensible architecture for custom features

- **Mobile App**: Companion mobile application# Remove Nginx config

- **Docker Support**: Containerized deployment optionssudo rm /etc/nginx/sites-enabled/webadmin

sudo rm /etc/nginx/sites-available/webadmin

### Version Historysudo systemctl reload nginx

- **v1.0**: Initial release with core features

- **v1.1**: Enhanced backup system with async processing# Remove sudoers file

- **v1.2**: Improved SSL management and security featuressudo rm /etc/sudoers.d/webadmin



---# Remove application files

sudo rm -rf /websites/webadmin

**WebAdmin** - Making web server management simple and powerful.

# Drop database

For more information, visit our [GitHub repository](https://github.com/andrewtimmins/hosting-panel).

```bash
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

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Built with modern web technologies:
- PHP 8.3
- MariaDB
- Nginx
- PowerDNS
- Supervisor
