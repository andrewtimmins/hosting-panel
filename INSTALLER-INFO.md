# WebAdmin Installer Package

## Package Contents

```
installer/
├── install.sh                      # Automated installation script
├── README.md                       # Complete documentation
├── app/                           # Application files (ready to deploy)
│   ├── *.php                      # Core PHP files
│   ├── assets/                    # CSS, JS, images
│   ├── src/                       # PHP classes (no migrations)
│   └── templates/                 # Nginx templates
├── config/                        # Configuration templates
│   ├── config.php.template        # App config with placeholders
│   ├── nginx-site.conf            # Nginx virtual host
│   ├── sudoers-webadmin           # Sudo permissions
│   └── backup-worker.conf         # Supervisor daemon config
└── database/
    └── schema.sql                 # Complete database schema

```

## Quick Start

1. **Make install script executable**:
   ```bash
   chmod +x install.sh
   ```

2. **Run the installer**:
   ```bash
   sudo ./install.sh
   ```

3. **Follow the prompts** to configure:
   - Installation directory
   - Database credentials
   - Admin user account

## What the Installer Does

✅ Checks Ubuntu version (22.04+)
✅ Installs all required packages (Nginx, PHP 8.3, MariaDB, PowerDNS, Supervisor)
✅ Creates and configures database
✅ Copies application files
✅ Configures Nginx virtual host
✅ Sets up sudo permissions for www-data
✅ Configures backup worker daemon
✅ Configures PowerDNS with MySQL backend
✅ Creates admin user
✅ Starts all services

## Installation Requirements

- Fresh Ubuntu 22.04+ server
- Root/sudo access
- Internet connection for package installation
- MySQL/MariaDB root password (will be prompted)

## Post-Installation

1. Access panel at: `http://your-server-ip`
2. Log in with your admin credentials
3. Configure SSL with: `sudo certbot --nginx`
4. Set up backup destinations
5. Start managing your server!

## Key Features

- **Async Backup System**: Queue-based backups with Supervisor worker
- **SSL Management**: Automated Let's Encrypt integration
- **DNS Management**: Full PowerDNS zone control
- **CMS Installers**: One-click WordPress/OpenCart setup
- **User Management**: Role-based access control
- **Real-time Monitoring**: Service status and logs

## Files to Distribute

Package the entire `installer/` directory:

```bash
cd /path/to/installer
tar -czf webadmin-installer.tar.gz installer/
```

Users extract and run:
```bash
tar -xzf webadmin-installer.tar.gz
cd installer
sudo ./install.sh
```

## Notes

- No database migrations needed (complete schema included)
- All configurations use templates with placeholders
- Backup worker auto-starts and auto-restarts
- Fully automated installation process
- Minimal user input required

## Support

See README.md for:
- Manual installation steps
- Troubleshooting guide
- Backup/restore procedures
- Update instructions
- Uninstallation steps
