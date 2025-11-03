# WebAdmin Quick Installation Guide

## Prerequisites

- Fresh Ubuntu 22.04+ server
- Root/sudo access
- Internet connection
- At least 2GB RAM and 10GB disk space

## Installation Steps

### 1. Download WebAdmin

```bash
# Clone from GitHub
git clone https://github.com/andrewtimmins/hosting-panel.git
cd hosting-panel

# OR download ZIP
wget https://github.com/andrewtimmins/hosting-panel/archive/main.zip
unzip main.zip
cd hosting-panel-main
```

### 2. Run Installation

```bash
# Make executable and run
chmod +x install.sh
sudo ./install.sh
```

### 3. Follow the Prompts

The installer will ask for:
- Installation directory (default: `/var/www/webadmin`)
- Database name, user, and password
- Admin username, email, and password
- Server domain or IP address

### 4. Access WebAdmin

Open your browser and go to:
```
http://your-server-ip-or-domain
```

Login with the admin credentials you created.

## Post-Installation

### Set up SSL (Recommended)
```bash
sudo certbot --nginx -d your-domain.com
```

### Configure Firewall
```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 53/tcp
sudo ufw allow 53/udp
sudo ufw enable
```

### Check Services
```bash
# Check all services are running
sudo systemctl status nginx php8.3-fpm mariadb pdns supervisor

# Check backup worker
sudo supervisorctl status webadmin-backup-worker
```

## What's Installed

- **Nginx** - Web server
- **PHP 8.3** - Application runtime
- **MariaDB** - Database server
- **PowerDNS** - DNS server
- **Supervisor** - Process manager for backup worker
- **Certbot** - SSL certificate automation

## Next Steps

1. Create your first website
2. Set up backup destinations
3. Configure DNS zones
4. Install WordPress or OpenCart sites

## Need Help?

- Check the full README.md for detailed documentation
- View logs in `/var/www/webadmin/logs/`
- Check system logs with `journalctl -f`

## Quick Commands

```bash
# Restart services
sudo systemctl restart nginx php8.3-fpm mariadb

# Check backup worker
sudo supervisorctl status webadmin-backup-worker

# View WebAdmin logs
sudo tail -f /var/www/webadmin/logs/nginx-error.log

# Test Nginx config
sudo nginx -t
```

That's it! WebAdmin is now ready to manage your web server.