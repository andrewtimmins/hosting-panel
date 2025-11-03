# Changelog

All notable changes to WebAdmin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-03

### Added
- Initial release of WebAdmin
- Complete web server management panel
- Nginx virtual host management
- MySQL/MariaDB database management
- PowerDNS zone and record management
- Async backup system with Supervisor
- Let's Encrypt SSL certificate automation
- WordPress and OpenCart installers
- User management with role-based access
- Real-time log viewing
- Service control (Nginx, PHP-FPM, MySQL, PowerDNS)
- Ubuntu 22.04+ support
- Automated installation script
- Comprehensive documentation

### Features
- **Website Management**: Create and manage multiple Nginx virtual hosts
- **Database Management**: Full MySQL database and user management
- **DNS Management**: Complete PowerDNS integration with all record types
- **Backup System**: Queue-based async backups with real-time progress
- **SSL Automation**: Integrated Certbot for Let's Encrypt certificates
- **CMS Installers**: One-click WordPress and OpenCart installation
- **Security**: Role-based access control and secure permissions
- **Monitoring**: System service monitoring and log viewing

### Technical Details
- PHP 8.3 with modern features and type safety
- MariaDB as primary database engine
- Nginx as web server and reverse proxy
- PowerDNS with MySQL backend for DNS
- Supervisor for process management
- Bootstrap CSS for responsive interface
- Async JavaScript for real-time updates

### Installation
- Automated installation script for Ubuntu 22.04+
- Complete dependency installation and configuration
- Database schema creation and initial setup
- System service configuration and startup
- SSL-ready configuration with Certbot integration

### Documentation
- Comprehensive README with full feature documentation
- Quick start guide for immediate deployment
- Troubleshooting guide with common solutions
- Security best practices and configuration
- Update and maintenance procedures

### Security
- Scoped sudo permissions for web server user
- BCrypt password hashing with proper salting
- SQL injection protection with prepared statements
- XSS protection with output escaping
- Secure session management with expiration
- File permission management and validation

## [Unreleased]

### Planned
- RESTful API for external integrations
- Multi-server management capabilities
- Enhanced monitoring with system metrics
- Plugin system for extensibility
- Mobile companion application
- Docker deployment support
- Enhanced backup scheduling and retention
- Advanced SSL certificate management
- Database migration tools
- Performance optimization features