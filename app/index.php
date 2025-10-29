<?php
require __DIR__ . '/bootstrap.php';

use App\Services\AuthService;

// Check authentication if enabled
if ($config['features']['enable_auth'] ?? false) {
    $authService = new AuthService($config['mysql']);
    $currentUser = $authService->getCurrentUser();
    
    if (!$currentUser) {
        header('Location: login.php');
        exit;
    }
} else {
    $currentUser = null;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="assets/css/enterprise-design-system.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <header class="app-header">
        <div class="header-left">
            <img src="assets/img/logo.png" alt="Logo" class="header-logo">
            <div class="header-search">
                <input type="text" id="global-search" placeholder="Search websites, domains..." class="search-input">
                <div id="search-results" class="search-dropdown" style="display: none;"></div>
            </div>
        </div>
        
        <div class="header-status">
            <div class="status-section">
                <h4>Services</h4>
                <div class="service-indicators">
                    <div class="service-status" data-service="nginx">
                        <span class="status-dot status-unknown"></span>
                        <span class="status-label">NGINX</span>
                    </div>
                    <div class="service-status" data-service="php">
                        <span class="status-dot status-unknown"></span>
                        <span class="status-label">PHP-FPM</span>
                    </div>
                    <div class="service-status" data-service="mysql">
                        <span class="status-dot status-unknown"></span>
                        <span class="status-label">MySQL</span>
                    </div>
                    <div class="service-status" data-service="powerdns">
                        <span class="status-dot status-unknown"></span>
                        <span class="status-label">PowerDNS</span>
                    </div>
                </div>
            </div>
            
            <div class="status-section">
                <h4>System Stats</h4>
                <div class="system-stats">
                    <div class="stat-item">
                        <span class="stat-label">CPU</span>
                        <div class="stat-bar">
                            <div class="stat-fill" id="cpu-usage" style="width: 0%"></div>
                        </div>
                        <span class="stat-value" id="cpu-value">0%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">RAM</span>
                        <div class="stat-bar">
                            <div class="stat-fill" id="memory-usage" style="width: 0%"></div>
                        </div>
                        <span class="stat-value" id="memory-value">0%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Network</span>
                        <div class="network-indicator">
                            <span class="network-arrow up">↑</span>
                            <span class="network-value" id="network-up">0 KB/s</span>
                            <span class="network-arrow down">↓</span>
                            <span class="network-value" id="network-down">0 KB/s</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($currentUser): ?>
            <div class="user-menu">
                <button class="user-menu-trigger" id="user-menu-trigger">
                    <svg class="user-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span class="user-name"><?= htmlspecialchars($currentUser['username'] ?? 'User') ?></span>
                    <svg class="dropdown-arrow" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M4 6l4 4 4-4z"></path>
                    </svg>
                </button>
                <div class="user-menu-dropdown" id="user-menu-dropdown">
                    <a href="#" class="user-menu-item" data-target="profile-view">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Profile
                    </a>
                    <a href="#" class="user-menu-item user-menu-item--danger" id="header-logout-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>
    <main class="app-container">
        <nav class="sidebar">
            <div class="nav-menu">
                <button class="nav-button is-active" data-target="sites-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                    <span class="nav-text">Websites</span>
                </button>
                <button class="nav-button" data-target="dns-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="1" width="30" height="22" rx="3" ry="3"></rect>
                        <text x="16" y="16" font-size="10" font-weight="bold" text-anchor="middle" fill="currentColor" stroke="none">.NET</text>
                    </svg>
                    <span class="nav-text">Domains</span>
                </button>
                <button class="nav-button" data-target="database-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                    </svg>
                    <span class="nav-text">Databases</span>
                </button>
                <button class="nav-button" data-target="services-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                        <line x1="6" y1="6" x2="6.01" y2="6"></line>
                        <line x1="6" y1="18" x2="6.01" y2="18"></line>
                    </svg>
                    <span class="nav-text">Services</span>
                </button>
                <button class="nav-button" data-target="logs-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span class="nav-text">Logs</span>
                </button>
                <button class="nav-button" data-target="backup-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    <span class="nav-text">Backups</span>
                </button>
                <button class="nav-button" data-target="settings-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v2.64M12 19.36V22M4.93 4.93l1.86 1.86M17.21 17.21l1.86 1.86M1 12h2.64M19.36 12H22M4.93 19.07l1.86-1.86M17.21 6.79l1.86-1.86"></path>
                    </svg>
                    <span class="nav-text">Settings</span>
                </button>
                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <button class="nav-button" data-target="users-view">
                    <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span class="nav-text">Users</span>
                </button>
                <?php endif; ?>
            </div>
        </nav>
        <section class="content">

            <section id="sites-view" class="view is-active">
                <div class="view-header">
                    <h2>Websites</h2>
                    <button id="open-create-site" class="primary">Add Site</button>
                </div>
                <div id="sites-table-wrapper" class="card">
                    <table id="sites-table">
                        <thead>
                            <tr>
                                <th>Server Name</th>
                                <th>Root</th>
                                <th>Listen</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>

            <section id="dns-view" class="view">
                <div class="view-header">
                    <h2>Domains</h2>
                    <button id="open-create-domain" class="primary">Add Domain</button>
                </div>
                
                <div class="dns-tabs">
                    <button class="dns-tab active" data-tab="domains">Domains</button>
                    <button class="dns-tab" data-tab="records" data-domain="" style="display: none;">Records</button>
                </div>
                
                <!-- Domains Tab -->
                <div id="dns-domains" class="dns-panel active">
                    <div id="domains-table-wrapper" class="card">
                        <table id="domains-table">
                            <thead>
                                <tr>
                                    <th>Domain Name</th>
                                    <th>Type</th>
                                    <th>Records</th>
                                    <th>Status</th>
                                    <th>Last Modified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Records Tab -->
                <div id="dns-records" class="dns-panel">
                    <div class="records-header">
                        <h3 id="records-domain-title">Records for domain</h3>
                        <div class="records-actions">
                            <button id="add-record" class="primary">Add Record</button>
                            <button id="back-to-domains" class="secondary">Back to Domains</button>
                        </div>
                    </div>
                    <div id="records-table-wrapper" class="card">
                        <table id="records-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Content</th>
                                    <th>TTL</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="database-view" class="view">
                <div class="view-header">
                    <h2>Database Management</h2>
                    <button id="open-create-database" class="primary">Create Database</button>
                </div>
                
                <div class="dns-tabs">
                    <button class="dns-tab active" data-db-tab="databases">Databases</button>
                    <button class="dns-tab" data-db-tab="users">Users</button>
                </div>
                
                <!-- Databases Tab -->
                <div id="db-databases" class="dns-panel active">
                    <div id="databases-table-wrapper" class="card">
                        <table id="databases-table">
                            <thead>
                                <tr>
                                    <th>Database Name</th>
                                    <th>Charset</th>
                                    <th>Collation</th>
                                    <th>Size</th>
                                    <th>Tables</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Users Tab -->
                <div id="db-users" class="dns-panel">
                    <div class="view-header">
                        <h3>Database Users</h3>
                        <button id="open-create-user" class="primary">Create User</button>
                    </div>
                    <div id="db-users-table-wrapper" class="card">
                        <table id="db-users-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Host</th>
                                    <th>Databases</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="logs-view" class="view">
                <div class="view-header">
                    <h2>Logs</h2>
                </div>
                <div class="log-controls">
                    <div class="form-group">
                        <label>Log File</label>
                        <select id="log-file">
                            <?php foreach ($config['security']['allowed_log_files'] as $logFile): ?>
                                <option value="<?= htmlspecialchars($logFile, ENT_QUOTES) ?>"><?= htmlspecialchars($logFile) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Lines</label>
                        <input type="number" id="log-lines" min="10" max="1000" value="200">
                    </div>
                    <button id="refresh-log" class="primary">Load Logs</button>
                </div>
                <div class="card">
                    <pre id="log-output" class="log-output" aria-live="polite"></pre>
                </div>
            </section>

            <section id="backup-view" class="view">
                <div class="view-header">
                    <h2>Backup Management</h2>
                    <div class="actions">
                        <button id="open-create-backup" class="primary">Create Backup</button>
                        <button id="open-create-destination" class="secondary">Add Destination</button>
                    </div>
                </div>
                
                <div class="dns-tabs">
                    <button class="dns-tab active" data-backup-tab="history">Backup History</button>
                    <button class="dns-tab" data-backup-tab="jobs">Scheduled Jobs</button>
                    <button class="dns-tab" data-backup-tab="destinations">Destinations</button>
                </div>
                
                <!-- Backup History Tab -->
                <div id="backup-history" class="dns-panel active">
                    <div id="backup-history-table-wrapper" class="card">
                        <table id="backup-history-table">
                            <thead>
                                <tr>
                                    <th>Created</th>
                                    <th>Type</th>
                                    <th>Items</th>
                                    <th>Destination</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Scheduled Jobs Tab -->
                <div id="backup-jobs" class="dns-panel">
                    <div class="view-header">
                        <h3>Scheduled Backup Jobs</h3>
                        <button id="open-create-job" class="primary">Create Job</button>
                    </div>
                    <div id="backup-jobs-table-wrapper" class="card">
                        <table id="backup-jobs-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Schedule</th>
                                    <th>Destination</th>
                                    <th>Last Run</th>
                                    <th>Next Run</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Destinations Tab -->
                <div id="backup-destinations" class="dns-panel">
                    <div id="backup-destinations-table-wrapper" class="card">
                        <table id="backup-destinations-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Default</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="services-view" class="view">
                <div class="view-header">
                    <h2>Service Control</h2>
                </div>
                <div class="service-grid">
                    <div class="service-card">
                        <h3>NGINX</h3>
                        <p class="text-secondary text-sm">Web server control</p>
                        <div class="actions service-actions">
                            <button data-command="nginx_reload" class="secondary">Reload</button>
                            <button data-command="nginx_restart" class="danger">Restart</button>
                        </div>
                    </div>
                    <div class="service-card">
                        <h3>PHP-FPM</h3>
                        <p class="text-secondary text-sm">PHP processor control</p>
                        <div class="actions service-actions">
                            <button data-command="php_fpm_reload" class="secondary">Reload</button>
                            <button data-command="php_fpm_restart" class="danger">Restart</button>
                        </div>
                    </div>
                    <div class="service-card">
                        <h3>MySQL</h3>
                        <p class="text-secondary text-sm">Database server control</p>
                        <div class="actions service-actions">
                            <button data-command="mysql_reload" class="secondary">Reload</button>
                            <button data-command="mysql_restart" class="danger">Restart</button>
                        </div>
                    </div>
                    <div class="service-card">
                        <h3>PowerDNS</h3>
                        <p class="text-secondary text-sm">Authoritative DNS control</p>
                        <div class="actions service-actions">
                            <button data-command="powerdns_reload" class="secondary">Reload</button>
                            <button data-command="powerdns_restart" class="danger">Restart</button>
                        </div>
                    </div>
                </div>
                <div class="service-history">
                    <h3>Recent Actions</h3>
                    <div class="card">
                        <ul id="service-history"></ul>
                    </div>
                </div>
            </section>

            <section id="settings-view" class="view">
                <div class="view-header">
                    <h2>Settings</h2>
                </div>
                <div class="card">
                    <form id="wordpress-settings-form" class="card-body form-grid">
                        <div class="form-header">
                            <div>
                                <h3>WordPress Defaults</h3>
                                <p class="text-secondary text-sm">Values used when provisioning a new WordPress site.</p>
                            </div>
                            <button type="submit" class="primary" id="save-wordpress-settings">Save Defaults</button>
                        </div>

                        <div class="form-group">
                            <label for="wp-default-username">Admin Username <span class="required">*</span></label>
                            <input type="text" id="wp-default-username" name="default_admin_username" required autocomplete="off">
                            <span class="form-help">Used as the administrator username for new WordPress installs.</span>
                        </div>

                        <div class="form-group">
                            <label for="wp-default-password">Admin Password <span class="required">*</span></label>
                            <input type="text" id="wp-default-password" name="default_admin_password" required autocomplete="off">
                            <span class="form-help">Change regularly and share securely with your team.</span>
                        </div>

                        <div class="form-group">
                            <label for="wp-default-email">Admin Email <span class="required">*</span></label>
                            <input type="email" id="wp-default-email" name="default_admin_email" required autocomplete="off">
                            <span class="form-help">Receives WordPress notifications for new sites.</span>
                        </div>

                        <div class="form-group">
                            <label for="wp-default-site-title">Default Site Title</label>
                            <input type="text" id="wp-default-site-title" name="default_site_title" autocomplete="off">
                            <span class="form-help">Supports <code>{server_name}</code> placeholder.</span>
                        </div>

                        <div class="form-group">
                            <label for="wp-default-table-prefix">Table Prefix</label>
                            <input type="text" id="wp-default-table-prefix" name="default_table_prefix" autocomplete="off">
                            <span class="form-help">Only letters, numbers, and underscores. Usually ends with an underscore.</span>
                        </div>

                        <div class="form-group">
                            <label for="wp-download-url">Download URL</label>
                            <input type="url" id="wp-download-url" name="download_url" autocomplete="off">
                            <span class="form-help">Source archive for WordPress core.</span>
                        </div>
                    </form>
                </div>
                <div class="card">
                    <form id="opencart-settings-form" class="card-body form-grid">
                        <div class="form-header">
                            <div>
                                <h3>OpenCart Defaults</h3>
                                <p class="text-secondary text-sm">Values used when provisioning a new OpenCart site.</p>
                            </div>
                            <button type="submit" class="primary" id="save-opencart-settings">Save Defaults</button>
                        </div>

                        <div class="form-group">
                            <label for="oc-default-username">Admin Username <span class="required">*</span></label>
                            <input type="text" id="oc-default-username" name="default_admin_username" required autocomplete="off">
                            <span class="form-help">Used as the administrator username for new OpenCart installs.</span>
                        </div>

                        <div class="form-group">
                            <label for="oc-default-password">Admin Password <span class="required">*</span></label>
                            <input type="text" id="oc-default-password" name="default_admin_password" required autocomplete="off">
                            <span class="form-help">Change regularly and share securely with your team.</span>
                        </div>

                        <div class="form-group">
                            <label for="oc-default-email">Admin Email <span class="required">*</span></label>
                            <input type="email" id="oc-default-email" name="default_admin_email" required autocomplete="off">
                            <span class="form-help">Receives OpenCart notifications for new sites.</span>
                        </div>

                        <div class="form-group">
                            <label for="oc-default-store-name">Default Store Name</label>
                            <input type="text" id="oc-default-store-name" name="default_store_name" autocomplete="off">
                            <span class="form-help">Supports <code>{server_name}</code> placeholder.</span>
                        </div>

                        <div class="form-group">
                            <label for="oc-download-url">Download URL</label>
                            <input type="url" id="oc-download-url" name="download_url" autocomplete="off">
                            <span class="form-help">Source archive for OpenCart core.</span>
                        </div>
                    </form>
                </div>
            </section>

            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
            <section id="users-view" class="view">
                <div class="view-header">
                    <h2>User Management</h2>
                    <button id="open-create-user" class="primary">Add User</button>
                </div>
                <div id="users-table-wrapper" class="card">
                    <table id="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($currentUser): ?>
            <section id="profile-view" class="view">
                <div class="view-header">
                    <h2>My Profile</h2>
                </div>
                <div class="card">
                    <form id="profile-form" class="card-body form-grid">
                        <div class="form-header">
                            <div>
                                <h3>Account Information</h3>
                                <p class="text-secondary text-sm">Update your account details and password.</p>
                            </div>
                            <button type="button" id="logout-button" class="secondary">Logout</button>
                        </div>

                        <div class="form-group">
                            <label for="profile-username">Username</label>
                            <input type="text" id="profile-username" name="username" readonly 
                                   value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>">
                            <span class="form-help">Username cannot be changed.</span>
                        </div>

                        <div class="form-group">
                            <label for="profile-email">Email</label>
                            <input type="email" id="profile-email" name="email" 
                                   value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="profile-full-name">Full Name</label>
                            <input type="text" id="profile-full-name" name="full_name" 
                                   value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="profile-current-password">Current Password</label>
                            <input type="password" id="profile-current-password" name="current_password" autocomplete="current-password">
                            <span class="form-help">Required to save changes.</span>
                        </div>

                        <div class="form-group">
                            <label for="profile-new-password">New Password</label>
                            <input type="password" id="profile-new-password" name="new_password" autocomplete="new-password">
                            <span class="form-help">Leave blank to keep current password.</span>
                        </div>

                        <div class="form-group">
                            <label for="profile-confirm-password">Confirm New Password</label>
                            <input type="password" id="profile-confirm-password" name="confirm_password" autocomplete="new-password">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </section>
            <?php endif; ?>
        </section>
    </main>

    <!-- DNS Modals -->
    <dialog id="create-domain-modal" class="dns-modal">
        <div class="modal-header">
            <h2>Create New Domain</h2>
        </div>
        <form id="create-domain-form" method="dialog">
            <div class="modal-body">
                <div class="form-group">
                    <label>Domain Name <span class="required">*</span></label>
                    <input type="text" name="domain_name" placeholder="example.com" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label>Domain Type <span class="required">*</span></label>
                    <select name="domain_type">
                        <option value="NATIVE">Native (Master)</option>
                        <option value="SLAVE">Slave</option>
                    </select>
                </div>
                <div id="slave-options" style="display: none;">
                    <div class="form-group">
                        <label>Master Server</label>
                        <input type="text" name="master_server" placeholder="192.168.1.1">
                        <span class="form-help">IP address of the master DNS server</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Account (Optional)</label>
                    <input type="text" name="account" placeholder="Account identifier">
                    <span class="form-help">Optional account identifier for organization</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" value="cancel" class="secondary">Cancel</button>
                <button type="submit" value="confirm" class="primary">Create Domain</button>
            </div>
        </form>
    </dialog>

    <dialog id="record-modal" class="dns-modal">
        <div class="modal-header">
            <h2 id="record-modal-title">Add DNS Record</h2>
        </div>
        <form id="record-form" method="dialog">
            <div class="modal-body">
                <div class="form-group">
                    <label>Record Name</label>
                    <input type="text" name="record_name" placeholder="www" required>
                    <span class="form-help">Leave empty for root domain, use @ for apex</span>
                </div>
                <div class="form-group">
                    <label>Record Type <span class="required">*</span></label>
                    <select name="record_type" required>
                        <option value="A">A (IPv4 Address)</option>
                        <option value="AAAA">AAAA (IPv6 Address)</option>
                        <option value="CNAME">CNAME (Canonical Name)</option>
                        <option value="MX">MX (Mail Exchange)</option>
                        <option value="TXT">TXT (Text)</option>
                        <option value="NS">NS (Name Server)</option>
                        <option value="SRV">SRV (Service)</option>
                        <option value="PTR">PTR (Pointer)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Content <span class="required">*</span></label>
                    <input type="text" name="record_content" placeholder="192.168.1.1" required>
                    <span class="form-help" id="content-help">IPv4 address for A records</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>TTL (Time To Live) <span class="required">*</span></label>
                        <input type="number" name="record_ttl" value="3600" min="1" required>
                        <span class="form-help">Seconds until cache expires</span>
                    </div>
                    <div class="form-group" id="priority-field" style="display: none;">
                        <label>Priority</label>
                        <input type="number" name="record_priority" min="0">
                        <span class="form-help">Lower = higher priority</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="record_disabled">
                        Disabled
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" value="cancel" class="secondary">Cancel</button>
                <button type="submit" value="confirm" class="primary">Save Record</button>
            </div>
        </form>
    </dialog>

    <!-- Create Database Modal -->
    <dialog id="create-database-modal" class="dns-modal">
        <div class="modal-header">
            <h2>Create Database</h2>
        </div>
        <form id="database-form" method="dialog">
            <div class="modal-body">
                <div class="form-group">
                    <label>Database Name <span class="required">*</span></label>
                    <input type="text" name="database_name" placeholder="my_database" required pattern="[a-zA-Z0-9_]+">
                    <span class="form-help">Alphanumeric and underscores only</span>
                </div>
                <div class="form-group">
                    <label>Character Set</label>
                    <select name="database_charset">
                        <option value="utf8mb4" selected>utf8mb4 (Recommended)</option>
                        <option value="utf8">utf8</option>
                        <option value="latin1">latin1</option>
                    </select>
                    <span class="form-help">utf8mb4 supports emojis and all Unicode characters</span>
                </div>
                <div class="form-group">
                    <label>Collation</label>
                    <select name="database_collation">
                        <option value="utf8mb4_unicode_ci" selected>utf8mb4_unicode_ci</option>
                        <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                        <option value="utf8_unicode_ci">utf8_unicode_ci</option>
                        <option value="utf8_general_ci">utf8_general_ci</option>
                        <option value="latin1_swedish_ci">latin1_swedish_ci</option>
                    </select>
                    <span class="form-help">Determines how string comparison is performed</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" value="cancel" class="secondary">Cancel</button>
                <button type="submit" value="confirm" class="primary">Create Database</button>
            </div>
        </form>
    </dialog>

    <!-- Create Database User Modal -->
    <dialog id="create-db-user-modal" class="dns-modal">
        <div class="modal-header">
            <h2>Create Database User</h2>
        </div>
        <form id="db-user-form" method="dialog">
            <div class="modal-body">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="user_name" placeholder="dbuser" required pattern="[a-zA-Z0-9_]+">
                    <span class="form-help">Alphanumeric and underscores only</span>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="user_password" required minlength="8">
                    <span class="form-help">Minimum 8 characters</span>
                </div>
                <div class="form-group">
                    <label>Host</label>
                    <select name="user_host">
                        <option value="localhost" selected>localhost (Local only)</option>
                        <option value="%">% (Any host)</option>
                        <option value="custom">Custom</option>
                    </select>
                    <span class="form-help">Where the user can connect from</span>
                </div>
                <div class="form-group" id="custom-host-field" style="display: none;">
                    <label>Custom Host</label>
                    <input type="text" name="user_host_custom" placeholder="192.168.1.%">
                    <span class="form-help">IP address or hostname pattern</span>
                </div>
                <div class="form-group">
                    <label>Grant Access To</label>
                    <select name="user_database" id="user-database-select">
                        <option value="">No database (permissions can be set later)</option>
                    </select>
                    <span class="form-help">Optionally grant ALL PRIVILEGES to a specific database</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" value="cancel" class="secondary">Cancel</button>
                <button type="submit" value="confirm" class="primary">Create User</button>
            </div>
        </form>
    </dialog>

    <!-- Grant Permissions Modal -->
    <dialog id="grant-permissions-modal" class="dns-modal">
        <div class="modal-header">
            <h2>Grant Database Permissions</h2>
        </div>
        <form id="grant-form" method="dialog">
            <input type="hidden" name="grant_username">
            <input type="hidden" name="grant_host">
            <div class="modal-body">
                <div class="form-group">
                    <label>Database <span class="required">*</span></label>
                    <select name="grant_database" required>
                        <option value="">Select a database...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Permissions</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_all" id="grant-all-checkbox">
                            <span>ALL PRIVILEGES</span>
                        </label>
                    </div>
                    <div class="checkbox-group" id="specific-permissions" style="margin-top: 1rem;">
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_select" value="SELECT">
                            <span>SELECT (Read data)</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_insert" value="INSERT">
                            <span>INSERT (Add data)</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_update" value="UPDATE">
                            <span>UPDATE (Modify data)</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_delete" value="DELETE">
                            <span>DELETE (Remove data)</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_create" value="CREATE">
                            <span>CREATE (Create tables)</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_drop" value="DROP">
                            <span>DROP (Delete tables)</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_alter" value="ALTER">
                            <span>ALTER (Modify tables)</span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="grant_index" value="INDEX">
                            <span>INDEX (Create indexes)</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" value="cancel" class="secondary">Cancel</button>
                <button type="submit" value="confirm" class="primary">Grant Permissions</button>
            </div>
        </form>
    </dialog>

    <dialog id="create-site-modal">
        <div class="modal-header">
            <h2>Create New Site</h2>
        </div>
        <form id="create-site-form" method="dialog">
            <div class="modal-body">
                <div class="form-group">
                    <label>Domain / Server Name <span class="required">*</span></label>
                    <input type="text" name="server_name" placeholder="example.com" autocomplete="off" required>
                    <span class="form-help">Enter the primary domain name for this site</span>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="https" value="1">
                        Use HTTPS (443)
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="install_wordpress" value="1" id="install-wordpress-toggle">
                        Install WordPress automatically
                    </label>
                    <span class="form-help">Downloads the latest WordPress package, prepares a database, and bootstraps an admin user.</span>
                </div>
                <div id="wordpress-options" class="form-section" style="display: none;">
                    <h3>WordPress Admin Setup</h3>
                    <div class="form-group">
                        <label>Admin Username <span class="required">*</span></label>
                        <input type="text" name="wordpress_admin_username" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Admin Password <span class="required">*</span></label>
                        <input type="text" name="wordpress_admin_password" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Admin Email <span class="required">*</span></label>
                        <input type="email" name="wordpress_admin_email" autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="install_opencart" value="1" id="install-opencart-toggle">
                        Install OpenCart automatically
                    </label>
                    <span class="form-help">Downloads the latest OpenCart package, prepares a database, and bootstraps an admin user.</span>
                </div>
                <div id="opencart-options" class="form-section" style="display: none;">
                    <h3>OpenCart Admin Setup</h3>
                    <div class="form-group">
                        <label>Admin Username <span class="required">*</span></label>
                        <input type="text" name="opencart_admin_username" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Admin Password <span class="required">*</span></label>
                        <input type="text" name="opencart_admin_password" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>Admin Email <span class="required">*</span></label>
                        <input type="email" name="opencart_admin_email" autocomplete="off">
                    </div>
                </div>
                <div id="site-summary" class="site-summary" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" value="cancel" class="secondary">Cancel</button>
                <button type="submit" value="confirm" class="primary">Create Site</button>
            </div>
        </form>
    </dialog>

    <dialog id="wordpress-credentials-modal" class="wordpress-modal">
        <div class="modal-header">
            <h2>WordPress Installed</h2>
        </div>
        <div class="modal-body">
            <p class="text-secondary text-sm">Share these details securely. Passwords are only shown once.</p>
            <div class="credential-group">
                <h3>Admin Account</h3>
                <dl>
                    <div>
                        <dt>Login URL</dt>
                        <dd><a href="#" target="_blank" rel="noopener" id="wp-login-url"></a></dd>
                    </div>
                    <div>
                        <dt>Username</dt>
                        <dd id="wp-admin-username"></dd>
                    </div>
                    <div>
                        <dt>Password</dt>
                        <dd id="wp-admin-password" class="password-value"></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd id="wp-admin-email"></dd>
                    </div>
                </dl>
            </div>
            <div class="credential-group">
                <h3>Database</h3>
                <dl>
                    <div>
                        <dt>Name</dt>
                        <dd id="wp-db-name"></dd>
                    </div>
                    <div>
                        <dt>User</dt>
                        <dd id="wp-db-user"></dd>
                    </div>
                    <div>
                        <dt>Password</dt>
                        <dd id="wp-db-password" class="password-value"></dd>
                    </div>
                    <div>
                        <dt>Table Prefix</dt>
                        <dd id="wp-table-prefix"></dd>
                    </div>
                </dl>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="primary" id="close-wordpress-credentials">Close</button>
        </div>
    </dialog>

    <dialog id="opencart-credentials-modal" class="wordpress-modal">
        <div class="modal-header">
            <h2>OpenCart Installed</h2>
        </div>
        <div class="modal-body">
            <p class="text-secondary text-sm">Share these details securely. Passwords are only shown once.</p>
            <div class="credential-group">
                <h3>Admin Account</h3>
                <dl>
                    <div>
                        <dt>Login URL</dt>
                        <dd><a href="#" target="_blank" rel="noopener" id="oc-login-url"></a></dd>
                    </div>
                    <div>
                        <dt>Username</dt>
                        <dd id="oc-admin-username"></dd>
                    </div>
                    <div>
                        <dt>Password</dt>
                        <dd id="oc-admin-password" class="password-value"></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd id="oc-admin-email"></dd>
                    </div>
                </dl>
            </div>
            <div class="credential-group">
                <h3>Database</h3>
                <dl>
                    <div>
                        <dt>Name</dt>
                        <dd id="oc-db-name"></dd>
                    </div>
                    <div>
                        <dt>User</dt>
                        <dd id="oc-db-user"></dd>
                    </div>
                    <div>
                        <dt>Password</dt>
                        <dd id="oc-db-password" class="password-value"></dd>
                    </div>
                </dl>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="primary" id="close-opencart-credentials">Close</button>
        </div>
    </dialog>

    <dialog id="config-modal" class="config-modal">
        <div class="modal-header">
            <h2 id="config-modal-title">Configure Site</h2>
        </div>
        
        <div class="config-tabs">
            <button type="button" class="config-tab active" data-tab="basic">Basic</button>
            <button type="button" class="config-tab" data-tab="listen">Listen & SSL</button>
            <button type="button" class="config-tab" data-tab="php">PHP</button>
            <button type="button" class="config-tab" data-tab="logging">Logging</button>
            <button type="button" class="config-tab" data-tab="performance">Performance</button>
            <button type="button" class="config-tab" data-tab="security">Security</button>
            <button type="button" class="config-tab" data-tab="locations">Custom Locations</button>
            <button type="button" class="config-tab" data-tab="advanced">Advanced</button>
        </div>

        <form id="config-form">
            <div class="config-content">
                <!-- Basic Configuration -->
                <div class="config-section active" id="config-basic">
                    <h3>Basic Settings</h3>
                    <div class="form-group">
                        <label>Server Name</label>
                        <input type="text" name="basic.server_name" readonly>
                        <span class="form-help">The primary domain name for this site</span>
                    </div>
                    <div class="form-group">
                        <label>Document Root <span class="required">*</span></label>
                        <input type="text" name="basic.document_root" required>
                        <span class="form-help">Absolute path to the website files</span>
                    </div>
                    <div class="form-group">
                        <label>Index Files</label>
                        <input type="text" name="basic.index_files" placeholder="index.php index.html index.htm">
                        <span class="form-help">Space-separated list of index files to try</span>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="basic.enabled">
                            Site Enabled
                        </label>
                    </div>
                </div>

                <!-- Listen & SSL Configuration -->
                <div class="config-section" id="config-listen">
                    <h3>HTTP/HTTPS Configuration</h3>
                    <div class="form-group">
                        <label>HTTP Listen Ports</label>
                        <input type="text" name="listen.http_listen" placeholder="80">
                        <span class="form-help">HTTP ports to listen on (space-separated)</span>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="listen.https_enabled" id="https-enabled-checkbox">
                            <span class="checkbox-text">Enable HTTPS</span>
                        </label>
                    </div>
                    <div class="https-config" id="https-config-section" style="display: none;">
                        <div class="form-group">
                            <label>HTTPS Listen Ports</label>
                            <input type="text" name="listen.https_listen" placeholder="443 ssl http2">
                            <span class="form-help">HTTPS ports and options (space-separated)</span>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="listen.redirect_http_to_https">
                                <span class="checkbox-text">Redirect HTTP to HTTPS</span>
                            </label>
                        </div>
                        
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">SSL Certificate</h4>
                        
                        <div class="form-group">
                            <label>Certificate Type</label>
                            <select name="ssl.certificate_type" id="ssl-certificate-type">
                                <option value="letsencrypt">Let's Encrypt (Free, Auto-Renew)</option>
                                <option value="manual">Manual Certificate Paths</option>
                            </select>
                            <span class="form-help">Choose automatic Let's Encrypt or provide your own certificate</span>
                        </div>
                        
                        <div id="letsencrypt-config" class="letsencrypt-config">
                            <div class="form-group">
                                <label>Email Address <span class="required">*</span></label>
                                <input type="email" name="ssl.letsencrypt_email" placeholder="admin@example.com">
                                <span class="form-help">Email for Let's Encrypt notifications and recovery</span>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="ssl.letsencrypt_agree_tos" checked>
                                    <span class="checkbox-text">I agree to the Let's Encrypt Terms of Service</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Additional Domains</label>
                                <input type="text" name="ssl.letsencrypt_extra_domains" placeholder="www.example.com blog.example.com">
                                <span class="form-help">Additional domain names for the certificate (space-separated, optional)</span>
                            </div>
                            <div class="alert alert-info" style="margin-top: 1rem; padding: 0.75rem; background: var(--color-primary-50); border-left: 3px solid var(--color-primary-600); border-radius: 4px;">
                                <strong>Note:</strong> Certbot will automatically obtain and renew SSL certificates. Ensure the domain points to this server and port 80 is accessible for validation.
                            </div>
                        </div>
                        
                        <div id="manual-certificate-config" class="manual-certificate-config" style="display: none;">
                            <div class="form-group">
                                <label>SSL Certificate Path <span class="required">*</span></label>
                                <input type="text" name="ssl.ssl_certificate" placeholder="/etc/ssl/certs/domain.crt">
                                <span class="form-help">Path to SSL certificate file</span>
                            </div>
                            <div class="form-group">
                                <label>SSL Certificate Key Path <span class="required">*</span></label>
                                <input type="text" name="ssl.ssl_certificate_key" placeholder="/etc/ssl/private/domain.key">
                                <span class="form-help">Path to SSL private key file</span>
                            </div>
                        </div>
                        
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">SSL Options</h4>
                        
                        <div class="form-group">
                            <label>SSL Protocols</label>
                            <input type="text" name="ssl.ssl_protocols" placeholder="TLSv1.2 TLSv1.3">
                            <span class="form-help">Allowed SSL/TLS protocol versions (space-separated)</span>
                        </div>
                        <div class="form-group">
                            <label>SSL Ciphers</label>
                            <input type="text" name="ssl.ssl_ciphers" placeholder="HIGH:!aNULL:!MD5">
                            <span class="form-help">SSL cipher suite configuration</span>
                        </div>
                    </div>
                </div>

                <!-- PHP Configuration -->
                <div class="config-section" id="config-php">
                    <h3>PHP Configuration</h3>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="php.php_enabled" id="php-enabled-checkbox">
                            <span class="checkbox-text">Enable PHP Processing</span>
                        </label>
                    </div>
                    <div class="php-config" id="php-config-section" style="display: none;">
                        <div class="form-group">
                            <label>FastCGI Pass <span class="required">*</span></label>
                            <select name="php.php_fastcgi_pass">
                                <option value="unix:/run/php/php8.3-fpm.sock">PHP 8.3 (Socket)</option>
                                <option value="unix:/run/php/php8.2-fpm.sock">PHP 8.2 (Socket)</option>
                                <option value="unix:/run/php/php8.1-fpm.sock">PHP 8.1 (Socket)</option>
                                <option value="unix:/run/php/php8.0-fpm.sock">PHP 8.0 (Socket)</option>
                                <option value="127.0.0.1:9000">PHP-FPM (TCP:9000)</option>
                            </select>
                            <span class="form-help">PHP FastCGI process manager connection</span>
                        </div>
                        <div class="form-group">
                            <label>FastCGI Index</label>
                            <input type="text" name="php.php_fastcgi_index" placeholder="index.php">
                            <span class="form-help">Default PHP file to serve</span>
                        </div>
                        <div class="form-group">
                            <label>FastCGI Timeout</label>
                            <input type="number" name="php.fastcgi_read_timeout" placeholder="60" min="1">
                            <span class="form-help">Timeout for reading a response from FastCGI server (seconds)</span>
                        </div>
                    </div>
                </div>

                <!-- Logging Configuration -->
                <div class="config-section" id="config-logging">
                    <h3>Logging Configuration</h3>
                    <div class="form-group">
                        <label>Access Log Path</label>
                        <input type="text" name="logging.access_log" placeholder="/var/log/nginx/site-access.log">
                        <span class="form-help">Path to access log file (leave empty to use document root/logs/access.log)</span>
                    </div>
                    <div class="form-group">
                        <label>Error Log Path</label>
                        <input type="text" name="logging.error_log" placeholder="/var/log/nginx/site-error.log">
                        <span class="form-help">Path to error log file (leave empty to use document root/logs/error.log)</span>
                    </div>
                    <div class="form-group">
                        <label>Error Log Level</label>
                        <select name="logging.error_log_level">
                            <option value="warn">Warn</option>
                            <option value="error" selected>Error</option>
                            <option value="crit">Critical</option>
                            <option value="alert">Alert</option>
                            <option value="emerg">Emergency</option>
                            <option value="debug">Debug</option>
                            <option value="info">Info</option>
                            <option value="notice">Notice</option>
                        </select>
                        <span class="form-help">Minimum severity level to log</span>
                    </div>
                </div>

                <!-- Performance Configuration -->
                <div class="config-section" id="config-performance">
                    <h3>Performance Settings</h3>
                    
                    <h4 style="margin-top: 1rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">Request Limits</h4>
                    
                    <div class="form-group">
                        <label>Client Max Body Size</label>
                        <input type="text" name="performance.client_max_body_size" placeholder="10M">
                        <span class="form-help">Maximum allowed size of client request body (e.g., 1M, 10M, 100M, 1G)</span>
                    </div>
                    <div class="form-group">
                        <label>Client Body Buffer Size</label>
                        <input type="text" name="performance.client_body_buffer_size" placeholder="128k">
                        <span class="form-help">Buffer size for reading client request body</span>
                    </div>
                    
                    <h4 style="margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">FastCGI Cache (PHP)</h4>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="performance.fastcgi_cache_enabled" id="fastcgi-cache-checkbox">
                            <span class="checkbox-text">Enable FastCGI Cache</span>
                        </label>
                        <span class="form-help">Cache PHP responses for improved performance</span>
                    </div>
                    
                    <div class="fastcgi-cache-config" id="fastcgi-cache-section" style="display: none;">
                        <div class="form-group">
                            <label>Cache Path</label>
                            <input type="text" name="performance.fastcgi_cache_path" placeholder="/var/cache/nginx/fastcgi">
                            <span class="form-help">Directory to store cached responses (will be created if needed)</span>
                        </div>
                        <div class="form-group">
                            <label>Cache Valid Time</label>
                            <input type="text" name="performance.fastcgi_cache_valid" placeholder="60m">
                            <span class="form-help">How long to cache responses (e.g., 5m, 1h, 1d)</span>
                        </div>
                        <div class="form-group">
                            <label>Cache Key</label>
                            <input type="text" name="performance.fastcgi_cache_key" placeholder="$scheme$request_method$host$request_uri">
                            <span class="form-help">Variables to use for cache key (default is usually fine)</span>
                        </div>
                        <div class="form-group">
                            <label>Cache Bypass Conditions</label>
                            <input type="text" name="performance.fastcgi_cache_bypass" placeholder="$cookie_session $http_authorization">
                            <span class="form-help">Skip cache when these conditions are true (space-separated)</span>
                        </div>
                        <div class="form-group">
                            <label>No Cache Conditions</label>
                            <input type="text" name="performance.fastcgi_no_cache" placeholder="$cookie_session $http_authorization">
                            <span class="form-help">Don't cache when these conditions are true (space-separated)</span>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="performance.fastcgi_cache_use_stale">
                                <span class="checkbox-text">Use Stale Cache on Error</span>
                            </label>
                            <span class="form-help">Serve stale cached content if backend is down or slow</span>
                        </div>
                    </div>
                    
                    <h4 style="margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">Browser Cache (Static Files)</h4>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="performance.browser_cache_enabled" id="browser-cache-checkbox">
                            <span class="checkbox-text">Enable Browser Caching</span>
                        </label>
                        <span class="form-help">Set cache headers for static files (images, CSS, JS, etc.)</span>
                    </div>
                    
                    <div class="browser-cache-config" id="browser-cache-section" style="display: none;">
                        <div class="form-group">
                            <label>CSS/JS Cache Duration</label>
                            <select name="performance.cache_css_js">
                                <option value="1d">1 Day</option>
                                <option value="7d">7 Days</option>
                                <option value="30d" selected>30 Days</option>
                                <option value="90d">90 Days</option>
                                <option value="1y">1 Year</option>
                            </select>
                            <span class="form-help">How long browsers should cache CSS and JavaScript files</span>
                        </div>
                        <div class="form-group">
                            <label>Image Cache Duration</label>
                            <select name="performance.cache_images">
                                <option value="7d">7 Days</option>
                                <option value="30d">30 Days</option>
                                <option value="90d" selected>90 Days</option>
                                <option value="1y">1 Year</option>
                            </select>
                            <span class="form-help">How long browsers should cache images</span>
                        </div>
                        <div class="form-group">
                            <label>Font Cache Duration</label>
                            <select name="performance.cache_fonts">
                                <option value="30d">30 Days</option>
                                <option value="90d">90 Days</option>
                                <option value="1y" selected>1 Year</option>
                            </select>
                            <span class="form-help">How long browsers should cache font files</span>
                        </div>
                        <div class="form-group">
                            <label>Media Cache Duration</label>
                            <select name="performance.cache_media">
                                <option value="30d">30 Days</option>
                                <option value="90d">90 Days</option>
                                <option value="1y" selected>1 Year</option>
                            </select>
                            <span class="form-help">How long browsers should cache video/audio files</span>
                        </div>
                    </div>
                    
                    <h4 style="margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">Gzip Compression</h4>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="performance.gzip_enabled" id="gzip-enabled-checkbox">
                            <span class="checkbox-text">Enable Gzip Compression</span>
                        </label>
                        <span class="form-help">Compress responses to reduce bandwidth usage</span>
                    </div>
                    <div class="gzip-config" id="gzip-config-section" style="display: none;">
                        <div class="form-group">
                            <label>Gzip Compression Level (1-9)</label>
                            <input type="number" name="performance.gzip_comp_level" placeholder="6" min="1" max="9">
                            <span class="form-help">Higher = better compression but more CPU usage</span>
                        </div>
                        <div class="form-group">
                            <label>Gzip Types</label>
                            <textarea name="performance.gzip_types" rows="3" placeholder="text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript"></textarea>
                            <span class="form-help">MIME types to compress with gzip (one per line or space-separated)</span>
                        </div>
                        <div class="form-group">
                            <label>Gzip Min Length</label>
                            <input type="number" name="performance.gzip_min_length" placeholder="256" min="0">
                            <span class="form-help">Minimum response size to compress (bytes)</span>
                        </div>
                    </div>
                </div>

                <!-- Security Configuration -->
                <div class="config-section" id="config-security">
                    <h3>Security Settings</h3>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="security.hide_server_tokens">
                            <span class="checkbox-text">Hide Server Tokens</span>
                        </label>
                        <span class="form-help">Hide nginx version in error pages and Server header</span>
                    </div>
                    <div class="form-group">
                        <label>X-Frame-Options</label>
                        <select name="security.x_frame_options">
                            <option value="">None</option>
                            <option value="DENY">DENY</option>
                            <option value="SAMEORIGIN">SAMEORIGIN</option>
                        </select>
                        <span class="form-help">Clickjacking protection</span>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="security.x_content_type_options">
                            <span class="checkbox-text">X-Content-Type-Options: nosniff</span>
                        </label>
                        <span class="form-help">Prevent MIME type sniffing</span>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="security.x_xss_protection">
                            <span class="checkbox-text">X-XSS-Protection</span>
                        </label>
                        <span class="form-help">Enable XSS filtering (legacy browsers)</span>
                    </div>
                    <div class="form-group">
                        <label>Referrer-Policy</label>
                        <select name="security.referrer_policy">
                            <option value="">None</option>
                            <option value="no-referrer">no-referrer</option>
                            <option value="no-referrer-when-downgrade">no-referrer-when-downgrade</option>
                            <option value="same-origin">same-origin</option>
                            <option value="strict-origin">strict-origin</option>
                            <option value="strict-origin-when-cross-origin">strict-origin-when-cross-origin</option>
                        </select>
                        <span class="form-help">Control referrer information sent</span>
                    </div>
                </div>

                <!-- Custom Locations -->
                <div class="config-section" id="config-locations">
                    <h3>Custom Location Blocks</h3>
                    <div id="locations-container">
                        <p class="no-locations">No custom locations configured</p>
                    </div>
                    <button type="button" class="secondary" id="add-location">Add Location</button>
                </div>

                <!-- Advanced Configuration -->
                <div class="config-section" id="config-advanced">
                    <h3>Advanced Configuration</h3>
                    <div class="form-group">
                        <label>Custom Nginx Directives</label>
                        <textarea name="advanced.custom_directives" rows="10" placeholder="# Add custom nginx directives here&#10;# Example:&#10;# add_header X-Custom-Header &quot;value&quot;;&#10;# client_body_timeout 30s;"></textarea>
                        <span class="form-help">Custom nginx directives to include in the server block (advanced users only)</span>
                    </div>
                </div>
            </div>

            <div class="config-footer">
                <div class="config-actions">
                    <button type="button" class="secondary" id="config-cancel">Cancel</button>
                    <button type="button" class="secondary" id="config-test">Test Configuration</button>
                    <button type="submit" class="primary">Save Configuration</button>
                </div>
            </div>
        </form>
    </dialog>

    <!-- Link Database to Site Modal -->
    <dialog id="link-database-modal" class="dns-modal">
        <div class="modal-header">
            <h2 id="link-database-modal-title">Link Database to Site</h2>
        </div>
        <form id="link-database-form" method="dialog">
            <input type="hidden" id="link-db-server-name" name="server_name">
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Database <span class="required">*</span></label>
                    <select id="link-db-database-select" name="database_name" required>
                        <option value="">Loading databases...</option>
                    </select>
                    <span class="form-help">Choose a database to link to this site</span>
                </div>
                <div class="form-group">
                    <label>Database User (Optional)</label>
                    <input type="text" id="link-db-user" name="database_user" placeholder="Database username">
                    <span class="form-help">The database user associated with this database</span>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <input type="text" id="link-db-description" name="description" placeholder="e.g., Main application database">
                    <span class="form-help">Optional note about this database's purpose</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" value="cancel" class="secondary" id="link-db-cancel">Cancel</button>
                <button type="submit" value="confirm" class="primary">Link Database</button>
            </div>
        </form>
    </dialog>

    <!-- Site Databases View Modal -->
    <dialog id="site-databases-modal" class="config-modal">
        <div class="modal-header">
            <h2 id="site-databases-modal-title">Linked Databases</h2>
        </div>
        <div class="modal-body">
            <div id="linked-databases-table-wrapper" class="card">
                <table id="linked-databases-table">
                    <thead>
                        <tr>
                            <th>Database Name</th>
                            <th>User</th>
                            <th>Size</th>
                            <th>Tables</th>
                            <th>Description</th>
                            <th>Linked</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" id="open-link-database-btn" class="primary">Link Database</button>
            <button type="button" class="secondary" id="close-site-databases-modal">Close</button>
        </div>
    </dialog>

    <script>
        window.dnsConfig = {
            recordTypes: {
                'A': { help: 'IPv4 address (e.g., 192.168.1.1)', requiresPriority: false },
                'AAAA': { help: 'IPv6 address (e.g., 2001:db8::1)', requiresPriority: false },
                'CNAME': { help: 'Canonical name (e.g., www.example.com)', requiresPriority: false },
                'MX': { help: 'Mail server (e.g., mail.example.com)', requiresPriority: true },
                'TXT': { help: 'Text content (e.g., "v=spf1 include:_spf.google.com ~all")', requiresPriority: false },
                'NS': { help: 'Name server (e.g., ns1.example.com)', requiresPriority: false },
                'SRV': { help: 'Service record (e.g., 0 5 5060 sip.example.com)', requiresPriority: true },
                'PTR': { help: 'Pointer record (e.g., mail.example.com)', requiresPriority: false }
            }
        };
        </script>
        
        <!-- Create Backup Modal -->
        <dialog id="create-backup-modal" class="modal">
            <div class="modal-header">
                <h2>Create Manual Backup</h2>
            </div>
            <form id="create-backup-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="backup-type">Backup Type</label>
                        <select id="backup-type" name="type" required>
                            <option value="">Select type...</option>
                            <option value="site">Websites Only</option>
                            <option value="database">Databases Only</option>
                            <option value="domain">DNS Domains Only</option>
                            <option value="mixed">Mixed (Multiple Types)</option>
                        </select>
                    </div>

                    <div class="form-group" id="backup-items-sites" style="display: none;">
                        <label>Select Websites</label>
                        <div class="checkbox-group" id="backup-sites-list"></div>
                    </div>

                    <div class="form-group" id="backup-items-databases" style="display: none;">
                        <label>Select Databases</label>
                        <div class="checkbox-group" id="backup-databases-list"></div>
                    </div>

                    <div class="form-group" id="backup-items-domains" style="display: none;">
                        <label>Select DNS Domains</label>
                        <div class="checkbox-group" id="backup-domains-list"></div>
                    </div>

                    <div class="form-group">
                        <label for="backup-destination">Destination</label>
                        <select id="backup-destination" name="destination_id" required>
                            <option value="">Select destination...</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="secondary" onclick="this.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="primary">Create Backup</button>
                </div>
            </form>
        </dialog>

        <!-- Restore Backup Modal -->
        <dialog id="restore-backup-modal" class="modal">
            <div class="modal-header">
                <h2>Restore from Backup</h2>
            </div>
            <form id="restore-backup-form">
                <div class="modal-body">
                    <input type="hidden" id="restore-backup-id" name="backup_id">
                    
                    <div class="form-group">
                        <label>Backup Details</label>
                        <div id="restore-backup-info" class="info-box"></div>
                    </div>

                    <div class="form-group">
                        <label>Items to Restore (select one or more)</label>
                        <div class="checkbox-group" id="restore-items-list"></div>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Restoring will overwrite existing data. This action cannot be undone.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="secondary" onclick="this.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="danger">Restore Backup</button>
                </div>
            </form>
        </dialog>

        <!-- Create Backup Job Modal -->
        <dialog id="create-job-modal" class="modal">
            <div class="modal-header">
                <h2>Create Scheduled Backup Job</h2>
            </div>
            <form id="create-job-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="job-name">Job Name</label>
                        <input type="text" id="job-name" name="name" required placeholder="Daily Website Backup">
                    </div>

                    <div class="form-group">
                        <label for="job-description">Description (optional)</label>
                        <textarea id="job-description" name="description" rows="2" placeholder="Backs up all production websites"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="job-backup-type">Backup Type</label>
                        <select id="job-backup-type" name="backup_type" required>
                            <option value="">Select type...</option>
                            <option value="sites">Websites Only</option>
                            <option value="databases">Databases Only</option>
                            <option value="domains">DNS Domains Only</option>
                            <option value="mixed">Mixed (Multiple Types)</option>
                        </select>
                    </div>

                    <div class="form-group" id="job-items-sites" style="display: none;">
                        <label>Select Websites</label>
                        <div class="checkbox-group" id="job-sites-list"></div>
                    </div>

                    <div class="form-group" id="job-items-databases" style="display: none;">
                        <label>Select Databases</label>
                        <div class="checkbox-group" id="job-databases-list"></div>
                    </div>

                    <div class="form-group" id="job-items-domains" style="display: none;">
                        <label>Select DNS Domains</label>
                        <div class="checkbox-group" id="job-domains-list"></div>
                    </div>

                    <div class="form-group">
                        <label for="job-schedule">Schedule (Cron Expression)</label>
                        <div class="cron-builder">
                            <select id="cron-preset">
                                <option value="">Custom...</option>
                                <option value="0 2 * * *">Daily at 2:00 AM</option>
                                <option value="0 3 * * 0">Weekly on Sunday at 3:00 AM</option>
                                <option value="0 4 1 * *">Monthly on 1st at 4:00 AM</option>
                                <option value="0 * * * *">Every Hour</option>
                                <option value="*/30 * * * *">Every 30 Minutes</option>
                            </select>
                            <input type="text" id="job-schedule" name="schedule_cron" required placeholder="0 2 * * *" pattern="^(\*|[0-9,\-\*/]+)\s+(\*|[0-9,\-\*/]+)\s+(\*|[0-9,\-\*/]+)\s+(\*|[0-9,\-\*/]+)\s+(\*|[0-9,\-\*/]+)$">
                        </div>
                        <span class="form-help">Format: minute hour day month weekday</span>
                    </div>

                    <div class="form-group">
                        <label for="job-destination">Destination</label>
                        <select id="job-destination" name="destination_id" required>
                            <option value="">Select destination...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="job-retention">Retention (days)</label>
                        <input type="number" id="job-retention" name="retention_days" value="30" min="0" placeholder="30">
                        <span class="form-help">0 = keep forever</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="secondary" onclick="this.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="primary">Create Job</button>
                </div>
            </form>
        </dialog>

        <!-- Create Backup Destination Modal -->
        <dialog id="create-destination-modal" class="modal">
            <div class="modal-header">
                <h2>Add Backup Destination</h2>
            </div>
            <form id="create-destination-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="dest-name">Destination Name</label>
                        <input type="text" id="dest-name" name="name" required placeholder="Local Backups">
                    </div>

                    <div class="form-group">
                        <label for="dest-type">Type</label>
                        <select id="dest-type" name="type" required>
                            <option value="">Select type...</option>
                            <option value="local">Local Folder</option>
                            <option value="sftp">SFTP Server</option>
                        </select>
                    </div>

                    <!-- Local Configuration -->
                    <div id="dest-local-config" style="display: none;">
                        <div class="form-group">
                            <label for="dest-local-path">Local Path</label>
                            <input type="text" id="dest-local-path" name="local_path" placeholder="/backup/path">
                            <span class="form-help">Absolute path to backup directory</span>
                        </div>
                    </div>

                    <!-- SFTP Configuration -->
                    <div id="dest-sftp-config" style="display: none;">
                        <div class="form-group">
                            <label for="dest-sftp-host">Host</label>
                            <input type="text" id="dest-sftp-host" name="sftp_host" placeholder="backup.example.com">
                        </div>

                        <div class="form-group">
                            <label for="dest-sftp-port">Port</label>
                            <input type="number" id="dest-sftp-port" name="sftp_port" value="22" min="1" max="65535">
                        </div>

                        <div class="form-group">
                            <label for="dest-sftp-username">Username</label>
                            <input type="text" id="dest-sftp-username" name="sftp_username" placeholder="backupuser">
                        </div>

                        <div class="form-group">
                            <label for="dest-sftp-password">Password</label>
                            <input type="password" id="dest-sftp-password" name="sftp_password">
                        </div>

                        <div class="form-group">
                            <label for="dest-sftp-path">Remote Path</label>
                            <input type="text" id="dest-sftp-path" name="sftp_path" placeholder="/backups">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="dest-default" name="is_default">
                            Set as default destination
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="secondary" onclick="this.closest('dialog').close()">Cancel</button>
                    <button type="button" id="test-destination" class="secondary">Test Connection</button>
                    <button type="submit" class="primary">Save Destination</button>
                </div>
            </form>
        </dialog>

        <!-- Backup Progress Modal -->
        <dialog id="backup-progress-modal" class="modal">
            <div class="modal-header">
                <h2>Backup in Progress</h2>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: var(--spacing-6); padding-bottom: var(--spacing-6); border-bottom: 2px solid var(--border-secondary);">
                    <div class="spinner" style="margin: 0 auto var(--spacing-5) auto;"></div>
                    <div style="color: var(--text-primary); font-weight: var(--font-weight-semibold); font-size: var(--font-size-xl); margin-bottom: var(--spacing-2);">Please Wait...</div>
                    <div style="color: var(--text-secondary); font-size: var(--font-size-base);">This may take some time to complete.</div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <p id="backup-progress-status" style="font-size: var(--font-size-base); color: var(--text-primary); margin: var(--spacing-2) 0;">Preparing backup...</p>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Items</label>
                    <div id="backup-progress-items" style="font-size: var(--font-size-sm); color: var(--text-secondary); line-height: 1.6;"></div>
                </div>
            </div>
        </dialog>

        <script>
        window.appConfig = {
            features: <?= json_encode($config['features'], JSON_THROW_ON_ERROR) ?>,
            defaults: <?= json_encode([
                'websites_root' => $config['paths']['websites_root'],
                'document_root_pattern' => $config['site_defaults']['document_root_pattern'] ?? $config['paths']['websites_root'] . '/{server_name}',
                'include_www_alias' => $config['site_defaults']['include_www_alias'] ?? true,
            ], JSON_THROW_ON_ERROR) ?>,
            wordpressDefaults: <?= json_encode($config['wordpress'] ?? [], JSON_THROW_ON_ERROR) ?>,
            opencartDefaults: <?= json_encode($config['opencart'] ?? [], JSON_THROW_ON_ERROR) ?>
        };
    </script>
    <script src="assets/js/app.js" type="module"></script>
    
    <!-- Notifications must be outside all dialogs to appear on top -->
    <div id="notifications" class="notifications" aria-live="polite"></div>
</body>
</html>
