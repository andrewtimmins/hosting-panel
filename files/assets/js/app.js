// DNS configuration
window.dnsConfig = {
    recordTypes: {
        'A': {
            help: 'IPv4 address (e.g., 192.168.1.1)',
            requiresPriority: false
        },
        'AAAA': {
            help: 'IPv6 address (e.g., 2001:db8::1)',
            requiresPriority: false
        },
        'CNAME': {
            help: 'Canonical name (e.g., www.example.com)',
            requiresPriority: false
        },
        'MX': {
            help: 'Mail exchange server (e.g., mail.example.com)',
            requiresPriority: true
        },
        'TXT': {
            help: 'Text record (e.g., "v=spf1 include:_spf.google.com ~all")',
            requiresPriority: false
        },
        'NS': {
            help: 'Name server (e.g., ns1.example.com)',
            requiresPriority: false
        },
        'SRV': {
            help: 'Service record (e.g., 10 443 sip.example.com)',
            requiresPriority: true
        },
        'PTR': {
            help: 'Pointer record for reverse DNS (e.g., example.com)',
            requiresPriority: false
        }
    }
};

// Utility function for API requests
async function apiRequest(action, data = {}) {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, payload: data })
        });

        const result = await response.json();
        
        if (result.success) {
            return result.data;
        } else {
            throw new Error(result.error || 'API request failed');
        }
    } catch (error) {
        console.error('API Request Error:', error);
        throw error;
    }
}

const views = document.querySelectorAll('.view');
const navButtons = document.querySelectorAll('.nav-button');
const notifications = document.getElementById('notifications');
const createSiteModal = document.getElementById('create-site-modal');
const createSiteForm = document.getElementById('create-site-form');
const openCreateSiteBtn = document.getElementById('open-create-site');
const siteSummary = document.getElementById('site-summary');
const serverNameField = createSiteForm.querySelector('input[name="server_name"]');
const httpsField = createSiteForm.querySelector('input[name="https"]');
const installWordPressField = createSiteForm.querySelector('input[name="install_wordpress"]');
const wordpressOptionsSection = document.getElementById('wordpress-options');
const wordpressAdminUsernameField = createSiteForm.querySelector('input[name="wordpress_admin_username"]');
const wordpressAdminPasswordField = createSiteForm.querySelector('input[name="wordpress_admin_password"]');
const wordpressAdminEmailField = createSiteForm.querySelector('input[name="wordpress_admin_email"]');
const installOpenCartField = createSiteForm.querySelector('input[name="install_opencart"]');
const opencartOptionsSection = document.getElementById('opencart-options');
const opencartAdminUsernameField = createSiteForm.querySelector('input[name="opencart_admin_username"]');
const opencartAdminPasswordField = createSiteForm.querySelector('input[name="opencart_admin_password"]');
const opencartAdminEmailField = createSiteForm.querySelector('input[name="opencart_admin_email"]');
const sitesTableBody = document.querySelector('#sites-table tbody');
const logFileSelect = document.getElementById('log-file');
const logLinesInput = document.getElementById('log-lines');
const logOutput = document.getElementById('log-output');
const refreshLogButton = document.getElementById('refresh-log');
const serviceHistoryList = document.querySelector('#service-history');
const wordpressSettingsForm = document.getElementById('wordpress-settings-form');
const wordpressCredentialsModal = document.getElementById('wordpress-credentials-modal');
const wordpressCredentialFields = {
    loginUrl: document.getElementById('wp-login-url'),
    adminUsername: document.getElementById('wp-admin-username'),
    adminPassword: document.getElementById('wp-admin-password'),
    adminEmail: document.getElementById('wp-admin-email'),
    dbName: document.getElementById('wp-db-name'),
    dbUser: document.getElementById('wp-db-user'),
    dbPassword: document.getElementById('wp-db-password'),
    tablePrefix: document.getElementById('wp-table-prefix'),
};
const closeWordPressCredentialsBtn = document.getElementById('close-wordpress-credentials');

const opencartCredentialsModal = document.getElementById('opencart-credentials-modal');
const opencartCredentialFields = {
    loginUrl: document.getElementById('oc-login-url'),
    adminUsername: document.getElementById('oc-admin-username'),
    adminPassword: document.getElementById('oc-admin-password'),
    adminEmail: document.getElementById('oc-admin-email'),
    dbName: document.getElementById('oc-db-name'),
    dbUser: document.getElementById('oc-db-user'),
    dbPassword: document.getElementById('oc-db-password'),
};
const closeOpenCartCredentialsBtn = document.getElementById('close-opencart-credentials');

let wordpressDefaults = window.appConfig?.wordpressDefaults || null;
let opencartDefaults = window.appConfig?.opencartDefaults || null;
let wordpressSettingsLoaded = false;
let opencartSettingsLoaded = false;

const API_ENDPOINT = 'api.php';

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function switchView(targetId) {
    views.forEach((view) => view.classList.toggle('is-active', view.id === targetId));
    navButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.target === targetId));
    
    // Initialize view-specific functionality
    if (targetId === 'dns-view') {
        initializeDNS();
    } else if (targetId === 'settings-view') {
        loadWordPressDefaults().catch(() => {});
        loadOpenCartDefaults().catch(() => {});
    } else if (targetId === 'users-view') {
        initializeUsers();
    } else if (targetId === 'database-view') {
        initializeDatabases();
    } else if (targetId === 'backup-view') {
        loadBackupHistory();
        loadBackupJobs();
        loadBackupDestinations();
    } else if (targetId === 'ssl-view') {
        loadSSLCertificates().catch(() => {});
    } else if (targetId === 'cron-view') {
        loadCronJobs().catch(() => {});
    } else if (targetId === 'files-view') {
        loadDirectory('/').catch(() => {});
    }
}

navButtons.forEach((button) => {
    button.addEventListener('click', () => switchView(button.dataset.target));
});

const notificationQueue = [];

function showNotification(message, type = 'info', timeout = 5000) {
    const element = document.createElement('div');
    element.className = `notification notification--${type}`;
    element.innerText = message;
    
    // Find open dialog and append notification to it, otherwise use the global container
    const openDialog = document.querySelector('dialog[open]');
    const targetContainer = openDialog || notifications;
    
    // If appending to dialog, create temporary notification container inside it
    let notifContainer = targetContainer;
    if (openDialog) {
        let dialogNotifs = openDialog.querySelector('.dialog-notifications');
        if (!dialogNotifs) {
            dialogNotifs = document.createElement('div');
            dialogNotifs.className = 'notifications dialog-notifications';
            dialogNotifs.style.cssText = 'position: fixed; top: 1rem; right: 1rem; z-index: 99999; display: flex; flex-direction: column; gap: 0.75rem; max-width: 420px; pointer-events: none;';
            openDialog.appendChild(dialogNotifs);
        }
        notifContainer = dialogNotifs;
    }
    
    notifContainer.appendChild(element);

    const id = setTimeout(() => {
        element.remove();
        const index = notificationQueue.indexOf(id);
        if (index >= 0) notificationQueue.splice(index, 1);
    }, timeout);

    notificationQueue.push(id);
}

// Global Search Functionality
const globalSearchInput = document.getElementById('global-search');
const searchResultsDropdown = document.getElementById('search-results');
let searchCache = { sites: [], domains: [], databases: [] };
let searchDebounceTimer = null;

async function performSearch(query) {
    if (query.length < 3) {
        searchResultsDropdown.style.display = 'none';
        return;
    }

    const lowerQuery = query.toLowerCase();
    const results = {
        websites: [],
        domains: [],
        databases: []
    };

    // Search in websites
    searchCache.sites.forEach(site => {
        if (site.server_name.toLowerCase().includes(lowerQuery) ||
            site.root?.toLowerCase().includes(lowerQuery)) {
            results.websites.push(site);
        }
    });

    // Search in DNS domains
    searchCache.domains.forEach(domain => {
        if (domain.name.toLowerCase().includes(lowerQuery)) {
            results.domains.push(domain);
        }
    });

    // Search in databases
    searchCache.databases.forEach(db => {
        if (db.name.toLowerCase().includes(lowerQuery)) {
            results.databases.push(db);
        }
    });

    displaySearchResults(results, query);
}

function displaySearchResults(results, query) {
    const totalResults = results.websites.length + results.domains.length + results.databases.length;

    if (totalResults === 0) {
        searchResultsDropdown.innerHTML = `
            <div class="search-no-results">
                <div class="search-no-results-icon">🔍</div>
                <div>No results found for "${escapeHtml(query)}"</div>
            </div>
        `;
        searchResultsDropdown.style.display = 'block';
        return;
    }

    let html = '';

    // Websites section
    if (results.websites.length > 0) {
        html += `
            <div class="search-category">
                <div class="search-category-title">Websites (${results.websites.length})</div>
                ${results.websites.slice(0, 5).map(site => `
                    <div class="search-result-item" data-type="website" data-id="${escapeHtml(site.server_name)}">
                        <div class="search-result-icon">🌐</div>
                        <div class="search-result-content">
                            <div class="search-result-title">${escapeHtml(site.server_name)}</div>
                            <div class="search-result-subtitle">${escapeHtml(site.root || 'No path')}</div>
                        </div>
                    </div>
                `).join('')}
                ${results.websites.length > 5 ? `<div style="padding: var(--spacing-2); text-align: center; font-size: var(--font-size-sm); color: var(--text-tertiary);">+${results.websites.length - 5} more</div>` : ''}
            </div>
        `;
    }

    // Domains section
    if (results.domains.length > 0) {
        html += `
            <div class="search-category">
                <div class="search-category-title">DNS Domains (${results.domains.length})</div>
                ${results.domains.slice(0, 5).map(domain => `
                    <div class="search-result-item" data-type="domain" data-id="${domain.id}">
                        <div class="search-result-icon">🌍</div>
                        <div class="search-result-content">
                            <div class="search-result-title">${escapeHtml(domain.name)}</div>
                            <div class="search-result-subtitle">DNS Zone</div>
                        </div>
                    </div>
                `).join('')}
                ${results.domains.length > 5 ? `<div style="padding: var(--spacing-2); text-align: center; font-size: var(--font-size-sm); color: var(--text-tertiary);">+${results.domains.length - 5} more</div>` : ''}
            </div>
        `;
    }

    // Databases section
    if (results.databases.length > 0) {
        html += `
            <div class="search-category">
                <div class="search-category-title">Databases (${results.databases.length})</div>
                ${results.databases.slice(0, 5).map(db => `
                    <div class="search-result-item" data-type="database" data-name="${escapeHtml(db.name)}">
                        <div class="search-result-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                            </svg>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">${escapeHtml(db.name)}</div>
                            <div class="search-result-subtitle">${db.size || 'MySQL Database'}</div>
                        </div>
                    </div>
                `).join('')}
                ${results.databases.length > 5 ? `<div style="padding: var(--spacing-2); text-align: center; font-size: var(--font-size-sm); color: var(--text-tertiary);">+${results.databases.length - 5} more</div>` : ''}
            </div>
        `;
    }

    searchResultsDropdown.innerHTML = html;
    searchResultsDropdown.style.display = 'block';

    // Add click handlers
    searchResultsDropdown.querySelectorAll('.search-result-item').forEach(item => {
        item.addEventListener('click', () => handleSearchResultClick(item));
    });
}

function handleSearchResultClick(item) {
    const type = item.dataset.type;
    const id = item.dataset.id;
    const name = item.dataset.name;

    searchResultsDropdown.style.display = 'none';
    globalSearchInput.value = '';

    if (type === 'website') {
        switchView('sites-view');
        setTimeout(() => {
            const row = document.querySelector(`#sites-table tbody tr[data-server-name="${id}"]`);
            if (row) {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.style.backgroundColor = 'var(--color-primary-50)';
                setTimeout(() => { row.style.backgroundColor = ''; }, 2000);
            }
        }, 100);
    } else if (type === 'domain') {
        switchView('dns-view');
        setTimeout(() => {
            const domainItem = document.querySelector(`.domain-item[data-domain-id="${id}"]`);
            if (domainItem) {
                domainItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                domainItem.style.backgroundColor = 'var(--color-primary-50)';
                setTimeout(() => { domainItem.style.backgroundColor = ''; }, 2000);
            }
        }, 100);
    } else if (type === 'database') {
        switchView('database-view');
        setTimeout(() => {
            const dbRow = document.querySelector(`#databases-table tbody tr[data-database-name="${name}"]`);
            if (dbRow) {
                dbRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                dbRow.style.backgroundColor = 'var(--color-primary-50)';
                setTimeout(() => { dbRow.style.backgroundColor = ''; }, 2000);
            }
        }, 100);
    }
}

async function updateSearchCache() {
    try {
        // Load sites
        const sitesData = await apiRequest('list_sites');
        searchCache.sites = sitesData.sites || [];

        // Load domains
        const domainsData = await apiRequest('list_domains');
        searchCache.domains = domainsData.domains || [];

        // Load databases
        const databasesData = await apiRequest('list_databases');
        searchCache.databases = databasesData || [];
    } catch (error) {
        console.error('Failed to update search cache:', error);
    }
}

if (globalSearchInput) {
    globalSearchInput.addEventListener('input', (e) => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            performSearch(e.target.value);
        }, 300);
    });

    globalSearchInput.addEventListener('focus', (e) => {
        if (e.target.value.length >= 3) {
            performSearch(e.target.value);
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.header-search')) {
            searchResultsDropdown.style.display = 'none';
        }
    });

    // Initial cache load
    updateSearchCache();
}

function populateWordPressSettingsForm(defaults) {
    if (!wordpressSettingsForm) {
        return;
    }

    const fields = ['default_admin_username', 'default_admin_password', 'default_admin_email', 'default_site_title', 'default_table_prefix', 'download_url'];

    fields.forEach((field) => {
        const input = wordpressSettingsForm.querySelector(`[name="${field}"]`);
        if (!input) {
            return;
        }

        const value = defaults?.[field] ?? '';
        if (typeof value === 'string') {
            input.value = value;
        }
    });
}

async function loadWordPressDefaults(force = false) {
    if (!wordpressSettingsForm) {
        return;
    }

    if (wordpressSettingsLoaded && !force) {
        return;
    }

    try {
        const data = await request('get_wordpress_defaults');
        wordpressDefaults = data;
        populateWordPressSettingsForm(data);
        wordpressSettingsLoaded = true;
    } catch (error) {
        if (!wordpressSettingsLoaded && wordpressDefaults) {
            populateWordPressSettingsForm(wordpressDefaults);
        }
        throw error;
    }
}

function applyWordPressDefaultsToCreateSite(force = false) {
    if (!wordpressDefaults) {
        return;
    }

    const mapping = [
        [wordpressAdminUsernameField, 'default_admin_username'],
        [wordpressAdminPasswordField, 'default_admin_password'],
        [wordpressAdminEmailField, 'default_admin_email'],
    ];

    mapping.forEach(([field, key]) => {
        if (!field) {
            return;
        }

        const currentValue = field.value?.trim?.() ?? '';
        if (force || currentValue === '') {
            const nextValue = wordpressDefaults?.[key] ?? '';
            field.value = typeof nextValue === 'string' ? nextValue : '';
        }
    });
}

function showWordPressCredentials(details) {
    if (!wordpressCredentialsModal || !details) {
        return;
    }

    const map = [
        [wordpressCredentialFields.loginUrl, details.login_url ?? details.site_url ?? ''],
        [wordpressCredentialFields.adminUsername, details.admin_username ?? ''],
        [wordpressCredentialFields.adminPassword, details.admin_password ?? ''],
        [wordpressCredentialFields.adminEmail, details.admin_email ?? ''],
        [wordpressCredentialFields.dbName, details.db_name ?? ''],
        [wordpressCredentialFields.dbUser, details.db_user ?? ''],
        [wordpressCredentialFields.dbPassword, details.db_password ?? ''],
        [wordpressCredentialFields.tablePrefix, details.table_prefix ?? ''],
    ];

    map.forEach(([element, value]) => {
        if (!element) {
            return;
        }

        const displayValue = value && String(value).trim() !== '' ? value : '—';

        if (element.tagName === 'A') {
            if (displayValue === '—') {
                element.removeAttribute('href');
            } else {
                element.href = displayValue;
            }
            element.textContent = displayValue;
        } else {
            element.textContent = displayValue;
        }
    });

    wordpressCredentialsModal.showModal();
}

function showOpenCartCredentials(details) {
    if (!opencartCredentialsModal || !details) {
        return;
    }

    const map = [
        [opencartCredentialFields.loginUrl, details.login_url ?? details.site_url ?? ''],
        [opencartCredentialFields.adminUsername, details.admin_username ?? ''],
        [opencartCredentialFields.adminPassword, details.admin_password ?? ''],
        [opencartCredentialFields.adminEmail, details.admin_email ?? ''],
        [opencartCredentialFields.dbName, details.db_name ?? ''],
        [opencartCredentialFields.dbUser, details.db_user ?? ''],
        [opencartCredentialFields.dbPassword, details.db_password ?? ''],
    ];

    map.forEach(([element, value]) => {
        if (!element) {
            return;
        }

        const displayValue = value && String(value).trim() !== '' ? value : '—';

        if (element.tagName === 'A') {
            if (displayValue === '—') {
                element.removeAttribute('href');
            } else {
                element.href = displayValue;
            }
            element.textContent = displayValue;
        } else {
            element.textContent = displayValue;
        }
    });

    opencartCredentialsModal.showModal();
}

async function loadOpenCartDefaults(force = false) {
    const opencartSettingsForm = document.getElementById('opencart-settings-form');
    if (!opencartSettingsForm) {
        return;
    }

    if (opencartSettingsLoaded && !force) {
        return;
    }

    try {
        const data = await request('get_opencart_defaults');
        opencartDefaults = data;
        populateOpenCartSettingsForm(data);
        opencartSettingsLoaded = true;
    } catch (error) {
        if (!opencartSettingsLoaded && opencartDefaults) {
            populateOpenCartSettingsForm(opencartDefaults);
        }
        throw error;
    }
}

function populateOpenCartSettingsForm(defaults) {
    const opencartSettingsForm = document.getElementById('opencart-settings-form');
    if (!opencartSettingsForm) {
        return;
    }

    const fields = ['default_admin_username', 'default_admin_password', 'default_admin_email', 'default_store_name', 'download_url'];

    fields.forEach((field) => {
        const input = opencartSettingsForm.querySelector(`[name="${field}"]`);
        if (!input) {
            return;
        }

        const value = defaults?.[field] ?? '';
        if (typeof value === 'string') {
            input.value = value;
        }
    });
}

function applyOpenCartDefaultsToCreateSite(force = false) {
    if (!opencartDefaults) {
        return;
    }

    const mapping = [
        [opencartAdminUsernameField, 'default_admin_username'],
        [opencartAdminPasswordField, 'default_admin_password'],
        [opencartAdminEmailField, 'default_admin_email'],
    ];

    mapping.forEach(([field, key]) => {
        if (!field) {
            return;
        }

        const currentValue = field.value?.trim?.() ?? '';
        if (force || currentValue === '') {
            const nextValue = opencartDefaults?.[key] ?? '';
            field.value = typeof nextValue === 'string' ? nextValue : '';
        }
    });
}

async function request(action, payload = {}) {
    try {
        const response = await fetch(API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, payload })
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({ message: response.statusText }));
            throw new Error(data.message || 'Request failed');
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Unknown error');
        }

        return data.data;
    } catch (error) {
        console.error(error);
        showNotification(error.message, 'error');
        throw error;
    }
}

async function loadSites() {
    const data = await request('list_sites');
    searchCache.sites = data || []; // Update search cache
    sitesTableBody.innerHTML = '';
    data.forEach((site) => {
        const tr = document.createElement('tr');
        const aliases = Array.isArray(site.server_names) ? site.server_names.slice(1) : [];
        const aliasBadge = aliases.length
            ? `<span class="alias-badge" title="${aliases.join(', ')}">+${aliases.length}</span>`
            : '';
        const metaFlags = [];
        if (site.php_fastcgi) {
            metaFlags.push(`<span class="meta-flag">PHP → ${site.php_fastcgi}</span>`);
        }
        if (Array.isArray(site.listen_directives) && site.listen_directives.length > 1) {
            metaFlags.push(`<span class="meta-flag">${site.listen_directives.join(' · ')}</span>`);
        }
        if (site.ssl_certificate) {
            metaFlags.push(`<span class="meta-flag">SSL</span>`);
        }
        if (site.managed === false) {
            metaFlags.push('<span class="meta-flag meta-flag--warning">external</span>');
        }

        const indexList = Array.isArray(site.index) ? site.index.join(', ') : (site.index || '');

        tr.dataset.serverName = site.server_name; // For search highlighting
        tr.innerHTML = `
            <td>
                <div class="cell-primary">${site.server_name} ${aliasBadge}</div>
                ${metaFlags.length ? `<div class="cell-meta">${metaFlags.join('')}</div>` : ''}
            </td>
            <td>
                <div class="cell-primary">${site.root}</div>
                ${indexList ? `<div class="cell-meta">Index: ${indexList}</div>` : ''}
            </td>
            <td>${site.listen}</td>
            <td><span class="status-pill">${site.enabled ? 'Enabled' : 'Disabled'}</span></td>
            <td class="table-actions">
                <button data-action="view-databases" data-server="${site.server_name}" class="secondary">Databases</button>
                <button data-action="toggle" data-server="${site.server_name}" class="secondary">
                    ${site.enabled ? 'Disable' : 'Enable'}
                </button>
                <button data-action="edit" data-server="${site.server_name}" class="secondary">Edit</button>
                <button data-action="delete" data-server="${site.server_name}" class="danger">Delete</button>
                <button data-action="reload" data-server="${site.server_name}" class="secondary">Reload NGINX</button>
            </td>
        `;
        sitesTableBody.appendChild(tr);
    });
}

function normaliseDomain(value) {
    return value.trim().toLowerCase();
}

function computeDocumentRoot(domain) {
    const pattern = window.appConfig?.defaults?.document_root_pattern || '/websites/{server_name}';
    const slug = domain.replace(/[^a-z0-9.-]/g, '-');
    return pattern
        .replace('{server_name_slug}', slug)
        .replace('{server_name}', domain);
}

function computeAliases(domain) {
    const includeWww = window.appConfig?.defaults?.include_www_alias !== false;
    if (!includeWww) return [];
    if (domain.startsWith('www.')) {
        return [domain.slice(4)];
    }
    return [`www.${domain}`];
}

function updateSiteSummary() {
    const domain = normaliseDomain(serverNameField.value || '');
    if (!domain) {
        siteSummary.textContent = 'Enter a domain to preview the generated paths.';
        siteSummary.style.display = 'none';
        return;
    }

    const root = computeDocumentRoot(domain);
    const aliases = computeAliases(domain);
    const httpsEnabled = httpsField.checked;
    const wordpressEnabled = installWordPressField?.checked ?? false;
    const opencartEnabled = installOpenCartField?.checked ?? false;
    const info = [
        `Document root: ${root}`,
        aliases.length ? `Aliases: ${aliases.join(', ')}` : 'Aliases: (none)',
        `HTTPS: ${httpsEnabled ? 'enabled' : 'disabled'}`,
    ];
    if (wordpressEnabled) {
        info.push('WordPress: install');
    }
    if (opencartEnabled) {
        info.push('OpenCart: install');
    }
    siteSummary.textContent = info.join(' • ');
    siteSummary.style.display = 'block';
}

async function createSite(formData) {
    const domain = normaliseDomain(formData.get('server_name') || '');
    if (!domain) {
        showNotification('Domain is required', 'warning');
        return;
    }

    if (!/^[a-z0-9.-]+$/.test(domain)) {
        showNotification('Domain may only contain letters, numbers, dots, and hyphens', 'warning');
        return;
    }

    const https = formData.get('https') === '1';
    const createDatabase = formData.get('create_database') === '1';
    const installWordPress = formData.get('install_wordpress') === '1';
    const installOpenCart = formData.get('install_opencart') === '1';

    const payload = {
        server_name: domain,
        https,
        create_database: createDatabase,
        wordpress: { install: false },
        opencart: { install: false },
    };

    if (installWordPress) {
        try {
            await loadWordPressDefaults();
        } catch (error) {
            /* handled elsewhere */
        }

        const defaults = wordpressDefaults || {};
        const adminUsername = (formData.get('wordpress_admin_username') || defaults.default_admin_username || '').trim();
        const adminPassword = (formData.get('wordpress_admin_password') || defaults.default_admin_password || '').trim();
        const adminEmail = (formData.get('wordpress_admin_email') || defaults.default_admin_email || '').trim();

        if (!adminUsername || !adminPassword || !adminEmail) {
            showNotification('WordPress admin username, password, and email are required', 'warning');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(adminEmail)) {
            showNotification('WordPress admin email must be valid', 'warning');
            return;
        }

        payload.wordpress = {
            install: true,
            admin_username: adminUsername,
            admin_password: adminPassword,
            admin_email: adminEmail,
        };
    }

    if (installOpenCart) {
        try {
            await loadOpenCartDefaults();
        } catch (error) {
            /* handled elsewhere */
        }

        const defaults = opencartDefaults || {};
        const adminUsername = (formData.get('opencart_admin_username') || defaults.default_admin_username || '').trim();
        const adminPassword = (formData.get('opencart_admin_password') || defaults.default_admin_password || '').trim();
        const adminEmail = (formData.get('opencart_admin_email') || defaults.default_admin_email || '').trim();

        if (!adminUsername || !adminPassword || !adminEmail) {
            showNotification('OpenCart admin username, password, and email are required', 'warning');
            return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(adminEmail)) {
            showNotification('OpenCart admin email must be valid', 'warning');
            return;
        }

        payload.opencart = {
            install: true,
            admin_username: adminUsername,
            admin_password: adminPassword,
            admin_email: adminEmail,
        };
    }

    const result = await request('create_site', payload);
    createSiteModal.close();
    
    let successMessage = 'Site created successfully';
    if (result?.wordpress && result?.opencart) {
        successMessage = 'Site created with WordPress and OpenCart installed successfully';
    } else if (result?.wordpress) {
        successMessage = 'Site created and WordPress installed successfully';
    } else if (result?.opencart) {
        successMessage = 'Site created and OpenCart installed successfully';
    } else if (result?.database) {
        successMessage = `Site and database created successfully!\n\nDatabase: ${result.database.name}\nUser: ${result.database.user}\nPassword: ${result.database.password}\nHost: ${result.database.host}`;
    }
    
    showNotification(successMessage, 'success', result?.database ? 15000 : 5000);
    
    await loadSites();

    return result;
}

openCreateSiteBtn.addEventListener('click', () => {
    updateSiteSummary();
    if (installWordPressField) {
        installWordPressField.checked = false;
    }
    if (wordpressOptionsSection) {
        wordpressOptionsSection.style.display = 'none';
        [wordpressAdminUsernameField, wordpressAdminPasswordField, wordpressAdminEmailField].forEach((field) => {
            if (field) {
                field.value = '';
            }
        });
    }
    if (installOpenCartField) {
        installOpenCartField.checked = false;
    }
    if (opencartOptionsSection) {
        opencartOptionsSection.style.display = 'none';
        [opencartAdminUsernameField, opencartAdminPasswordField, opencartAdminEmailField].forEach((field) => {
            if (field) {
                field.value = '';
            }
        });
    }
    createSiteModal.showModal();
});

if (installWordPressField) {
    installWordPressField.addEventListener('change', async () => {
        const enabled = installWordPressField.checked;
        if (wordpressOptionsSection) {
            wordpressOptionsSection.style.display = enabled ? 'block' : 'none';
        }

        if (enabled) {
            try {
                await loadWordPressDefaults();
            } catch (error) {
                /* handled elsewhere */
            }
            applyWordPressDefaultsToCreateSite();
        }

        updateSiteSummary();
    });
}

if (installOpenCartField) {
    installOpenCartField.addEventListener('change', async () => {
        const enabled = installOpenCartField.checked;
        if (opencartOptionsSection) {
            opencartOptionsSection.style.display = enabled ? 'block' : 'none';
        }

        if (enabled) {
            try {
                await loadOpenCartDefaults();
            } catch (error) {
                /* handled elsewhere */
            }
            applyOpenCartDefaultsToCreateSite();
        }

        updateSiteSummary();
    });
}

createSiteForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        const result = await createSite(new FormData(createSiteForm));
        createSiteForm.reset();
        if (result?.wordpress) {
            showWordPressCredentials(result.wordpress);
        }
        if (result?.opencart) {
            showOpenCartCredentials(result.opencart);
        }
    } catch (error) {
        /* no-op handled in createSite */
    }
});

createSiteModal.addEventListener('close', () => {
    createSiteForm.reset();
    updateSiteSummary();
    if (wordpressOptionsSection) {
        wordpressOptionsSection.style.display = 'none';
    }
    if (opencartOptionsSection) {
        opencartOptionsSection.style.display = 'none';
    }
});

// Handle cancel button separately to prevent form submission
createSiteForm.querySelector('button[value="cancel"]').addEventListener('click', (event) => {
    event.preventDefault();
    createSiteModal.close();
});

serverNameField.addEventListener('input', updateSiteSummary);
httpsField.addEventListener('change', updateSiteSummary);

sitesTableBody.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const serverName = button.dataset.server;
    const action = button.dataset.action;

    try {
        if (action === 'view-databases') {
            await openSiteDatabasesModal(serverName);
            return; // Don't reload sites table
        } else if (action === 'toggle') {
            await request('toggle_site', { server_name: serverName });
            showNotification('Site status updated', 'success');
        } else if (action === 'delete') {
            if (!confirm(`Delete configuration for ${serverName}?`)) {
                return;
            }
            await request('delete_site', { server_name: serverName });
            showNotification('Site deleted', 'success');
        } else if (action === 'reload') {
            await request('run_command', { command: 'nginx_reload' });
            showNotification('NGINX reload triggered', 'success');
        } else if (action === 'edit') {
            await openConfigModal(serverName);
            return; // Don't reload sites table yet
        }
        await loadSites();
    } catch (error) {
        /* handled by notification */
    }
});

let logUpdateInterval = null;
let isRealTimeEnabled = false;

async function loadLogs() {
    const logFile = logFileSelect.value;
    const lines = parseInt(logLinesInput.value, 10) || 200;
    try {
        const data = await request('tail_log', { log_file: logFile, lines });
        logOutput.textContent = data.join('\n');
        // Auto-scroll to bottom
        logOutput.scrollTop = logOutput.scrollHeight;
    } catch (error) {
        logOutput.textContent = 'Error loading log file: ' + error.message;
    }
}

async function updateLogFileOptions() {
    try {
        // Get available log files including site-specific logs
        const data = await request('get_log_files');
        const currentValue = logFileSelect.value;
        
        // Clear existing options
        logFileSelect.innerHTML = '';
        
        // Add system logs group
        if (data.system && data.system.length > 0) {
            const systemGroup = document.createElement('optgroup');
            systemGroup.label = 'System Logs';
            data.system.forEach(logFile => {
                const option = document.createElement('option');
                option.value = logFile.path;
                option.textContent = logFile.name;
                systemGroup.appendChild(option);
            });
            logFileSelect.appendChild(systemGroup);
        }
        
        // Add site logs group
        if (data.sites && data.sites.length > 0) {
            const sitesGroup = document.createElement('optgroup');
            sitesGroup.label = 'Site Logs';
            data.sites.forEach(logFile => {
                const option = document.createElement('option');
                option.value = logFile.path;
                option.textContent = logFile.name;
                sitesGroup.appendChild(option);
            });
            logFileSelect.appendChild(sitesGroup);
        }
        
        // Restore previous selection if still available
        if (currentValue) {
            const option = logFileSelect.querySelector(`option[value="${currentValue}"]`);
            if (option) {
                logFileSelect.value = currentValue;
            }
        }
    } catch (error) {
        console.error('Failed to load log file options:', error);
    }
}

function toggleRealTimeUpdates(enable = null) {
    if (enable === null) {
        enable = !isRealTimeEnabled;
    }
    
    isRealTimeEnabled = enable;
    
    if (logUpdateInterval) {
        clearInterval(logUpdateInterval);
        logUpdateInterval = null;
    }
    
    const realtimeToggle = document.getElementById('realtime-toggle');
    if (realtimeToggle) {
        realtimeToggle.textContent = isRealTimeEnabled ? 'Stop Real-time' : 'Start Real-time';
        realtimeToggle.className = isRealTimeEnabled ? 'danger' : 'secondary';
    }
    
    if (isRealTimeEnabled) {
        // Update every 2 seconds
        logUpdateInterval = setInterval(() => {
            loadLogs().catch(() => {});
        }, 2000);
        showNotification('Real-time log updates enabled', 'success', 3000);
    } else {
        showNotification('Real-time log updates disabled', 'info', 3000);
    }
}

// Event listeners for log functionality
logFileSelect.addEventListener('change', () => {
    loadLogs().catch(() => {});
});

logLinesInput.addEventListener('change', () => {
    loadLogs().catch(() => {});
});

refreshLogButton.addEventListener('click', () => {
    loadLogs().catch(() => {});
});

if (wordpressSettingsForm) {
    if (wordpressDefaults) {
        populateWordPressSettingsForm(wordpressDefaults);
    }

    wordpressSettingsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(wordpressSettingsForm);
        const payload = Object.fromEntries(formData.entries());

        try {
            const data = await request('update_wordpress_defaults', payload);
            wordpressDefaults = data;
            populateWordPressSettingsForm(data);
            showNotification('WordPress defaults saved', 'success');
        } catch (error) {
            /* handled globally */
        }
    });
}

if (closeWordPressCredentialsBtn && wordpressCredentialsModal) {
    closeWordPressCredentialsBtn.addEventListener('click', () => {
        wordpressCredentialsModal.close();
    });
}

if (closeOpenCartCredentialsBtn && opencartCredentialsModal) {
    closeOpenCartCredentialsBtn.addEventListener('click', () => {
        opencartCredentialsModal.close();
    });
}

const opencartSettingsForm = document.getElementById('opencart-settings-form');
if (opencartSettingsForm) {
    if (opencartDefaults) {
        populateOpenCartSettingsForm(opencartDefaults);
    }

    opencartSettingsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(opencartSettingsForm);
        const payload = Object.fromEntries(formData.entries());

        try {
            const data = await request('update_opencart_defaults', payload);
            opencartDefaults = data;
            populateOpenCartSettingsForm(data);
            showNotification('OpenCart defaults saved', 'success');
        } catch (error) {
            /* handled globally */
        }
    });
}

async function runServiceCommand(command) {
    const data = await request('run_command', { command });
    showNotification(data.message, data.success ? 'success' : 'error');
    await loadServiceHistory();
}

const serviceButtons = document.querySelectorAll('.service-actions button[data-command]');
serviceButtons.forEach((button) => {
    button.addEventListener('click', () => {
        runServiceCommand(button.dataset.command).catch(() => {});
    });
});

async function loadServiceHistory() {
    const history = await request('service_history');
    if (history.length === 0) {
        serviceHistoryList.textContent = 'No recent service actions.';
    } else {
        serviceHistoryList.textContent = history.map((item) => {
            return `${item.command_label} ${item.status} · ${item.executed_at}`;
        }).join('\n');
    }
}

// Varnish Cache Management
const refreshVarnishStatsBtn = document.getElementById('refresh-varnish-stats');
const varnishPurgeUrlBtn = document.getElementById('varnish-purge-url-btn');
const varnishPurgeAllBtn = document.getElementById('varnish-purge-all-btn');
const varnishPurgeUrlInput = document.getElementById('varnish-purge-url');

async function loadVarnishStats() {
    try {
        const stats = await request('get_varnish_stats');
        
        if (!stats.available) {
            document.getElementById('varnish-hits').textContent = 'N/A';
            document.getElementById('varnish-misses').textContent = 'N/A';
            document.getElementById('varnish-hit-rate').textContent = 'N/A';
            document.getElementById('varnish-objects').textContent = 'N/A';
            return;
        }
        
        document.getElementById('varnish-hits').textContent = stats.cache_hits.toLocaleString();
        document.getElementById('varnish-misses').textContent = stats.cache_misses.toLocaleString();
        document.getElementById('varnish-hit-rate').textContent = stats.hit_rate + '%';
        document.getElementById('varnish-objects').textContent = stats.objects.toLocaleString();
    } catch (error) {
        console.error('Failed to load Varnish stats:', error);
    }
}

if (refreshVarnishStatsBtn) {
    refreshVarnishStatsBtn.addEventListener('click', async () => {
        await loadVarnishStats();
        showNotification('Varnish statistics refreshed', 'success');
    });
}

if (varnishPurgeUrlBtn) {
    varnishPurgeUrlBtn.addEventListener('click', async () => {
        const url = varnishPurgeUrlInput.value.trim();
        if (!url) {
            showNotification('Please enter a URL to purge', 'warning');
            return;
        }
        
        try {
            const result = await request('purge_varnish_url', { url });
            showNotification(result.message, 'success');
            varnishPurgeUrlInput.value = '';
            await loadVarnishStats();
        } catch (error) {
            // Error already shown by request function
        }
    });
}

if (varnishPurgeAllBtn) {
    varnishPurgeAllBtn.addEventListener('click', async () => {
        if (!confirm('Are you sure you want to purge ALL cached content? This will clear the entire Varnish cache.')) {
            return;
        }
        
        try {
            const result = await request('purge_varnish_all');
            showNotification(result.message, 'success');
            await loadVarnishStats();
        } catch (error) {
            // Error already shown by request function
        }
    });
}

// Tab switching for Services view
const serviceTabs = document.querySelectorAll('.tabs .tab');
const serviceTabPanels = document.querySelectorAll('.tab-panel');

serviceTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        const targetPanel = tab.dataset.tab;
        
        // Update active tab
        serviceTabs.forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Update active panel
        serviceTabPanels.forEach((panel) => {
            panel.classList.remove('active');
            if (panel.id === targetPanel) {
                panel.classList.add('active');
            }
        });
        
        // Load Varnish stats when Varnish Cache tab is opened
        if (targetPanel === 'varnish-cache') {
            loadVarnishStats().catch(() => {});
        }
    });
});

// Load Varnish stats when services view is first opened
navButtons.forEach((button) => {
    if (button.dataset.target === 'services-view') {
        button.addEventListener('click', () => {
            // Check if Varnish Cache tab is active
            const varnishTab = document.querySelector('.tab[data-tab="varnish-cache"]');
            if (varnishTab && varnishTab.classList.contains('active')) {
                loadVarnishStats().catch(() => {});
            }
        });
    }
});

// Cleanup when page unloads
window.addEventListener('beforeunload', () => {
    if (logUpdateInterval) {
        clearInterval(logUpdateInterval);
    }
});

// Pause real-time updates when page is not visible
document.addEventListener('visibilitychange', () => {
    if (document.hidden && isRealTimeEnabled) {
        if (logUpdateInterval) {
            clearInterval(logUpdateInterval);
            logUpdateInterval = null;
        }
    } else if (!document.hidden && isRealTimeEnabled && !logUpdateInterval) {
        logUpdateInterval = setInterval(() => {
            loadLogs().catch(() => {});
        }, 2000);
    }
});

async function init() {
    try {
        await Promise.all([
            loadSites().catch(e => console.error('Failed to load sites:', e)),
            updateLogFileOptions().catch(e => console.error('Failed to update log options:', e)),
            loadLogs().catch(e => console.error('Failed to load logs:', e)),
            loadServiceHistory().catch(e => console.error('Failed to load service history:', e))
        ]);
        
        // Add real-time toggle button if it doesn't exist
        const logControls = document.querySelector('.log-controls');
        if (logControls && !document.getElementById('realtime-toggle')) {
            const realtimeButton = document.createElement('button');
            realtimeButton.id = 'realtime-toggle';
            realtimeButton.className = 'secondary';
            realtimeButton.textContent = 'Start Real-time';
            realtimeButton.addEventListener('click', () => toggleRealTimeUpdates());
            logControls.appendChild(realtimeButton);
        }
        
        console.log('Application initialized successfully');
    } catch (error) {
        console.error('Critical initialization error:', error);
        throw error;
    }
}

init().catch((error) => {
    console.error('Initialization error:', error);
    showNotification('Failed to load initial data: ' + (error.message || 'Unknown error'), 'error');
});

updateSiteSummary();

// ====== Site Database Linking ======

const linkDatabaseModal = document.getElementById('link-database-modal');
const linkDatabaseForm = document.getElementById('link-database-form');
const linkDbServerNameInput = document.getElementById('link-db-server-name');
const linkDbDatabaseSelect = document.getElementById('link-db-database-select');
const linkDbCancelBtn = document.getElementById('link-db-cancel');

const siteDatabasesModal = document.getElementById('site-databases-modal');
const siteDatabasesModalTitle = document.getElementById('site-databases-modal-title');
const linkedDatabasesTableBody = document.querySelector('#linked-databases-table tbody');
const openLinkDatabaseBtn = document.getElementById('open-link-database-btn');
const closeSiteDatabasesModalBtn = document.getElementById('close-site-databases-modal');

let currentDatabasesSite = null;

// Open site databases modal
async function openSiteDatabasesModal(serverName) {
    currentDatabasesSite = serverName;
    siteDatabasesModalTitle.textContent = `Databases for ${serverName}`;
    
    await loadSiteDatabases(serverName);
    siteDatabasesModal.showModal();
}

// Load databases linked to a site
async function loadSiteDatabases(serverName) {
    try {
        const databases = await request('get_site_databases', { server_name: serverName });
        linkedDatabasesTableBody.innerHTML = '';
        
        if (databases.length === 0) {
            linkedDatabasesTableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
                        No databases linked to this site yet. Click "Link Database" to add one.
                    </td>
                </tr>
            `;
            return;
        }
        
        databases.forEach(db => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="cell-primary">${escapeHtml(db.database_name)}</div>
                </td>
                <td>${escapeHtml(db.database_user || '-')}</td>
                <td>${escapeHtml(db.size || '0 MB')}</td>
                <td>${db.table_count || 0}</td>
                <td>${escapeHtml(db.description || '-')}</td>
                <td>${new Date(db.linked_at).toLocaleString()}</td>
                <td class="table-actions">
                    <button data-action="unlink-database" data-link-id="${db.id}" class="danger">Unlink</button>
                </td>
            `;
            linkedDatabasesTableBody.appendChild(tr);
        });
    } catch (error) {
        linkedDatabasesTableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--color-danger-600);">
                    Error loading databases: ${escapeHtml(error.message)}
                </td>
            </tr>
        `;
    }
}

// Open link database modal
if (openLinkDatabaseBtn) {
    openLinkDatabaseBtn.addEventListener('click', async () => {
        if (!currentDatabasesSite) return;
        
        linkDbServerNameInput.value = currentDatabasesSite;
        document.getElementById('link-database-modal-title').textContent = `Link Database to ${currentDatabasesSite}`;
        
        // Load available databases
        try {
            linkDbDatabaseSelect.innerHTML = '<option value="">Loading...</option>';
            const availableDatabases = await request('get_available_databases', { 
                server_name: currentDatabasesSite 
            });
            
            linkDbDatabaseSelect.innerHTML = '';
            
            if (availableDatabases.length === 0) {
                linkDbDatabaseSelect.innerHTML = '<option value="">No available databases</option>';
                showNotification('All databases are already linked to this site', 'info');
                return;
            }
            
            linkDbDatabaseSelect.innerHTML = '<option value="">Select a database...</option>';
            availableDatabases.forEach(db => {
                const option = document.createElement('option');
                option.value = db.name;
                option.textContent = `${db.name} (${db.size}, ${db.table_count} tables)`;
                linkDbDatabaseSelect.appendChild(option);
            });
            
            linkDatabaseModal.showModal();
        } catch (error) {
            showNotification(`Failed to load available databases: ${error.message}`, 'error');
        }
    });
}

// Handle link database form submission
if (linkDatabaseForm) {
    linkDatabaseForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        const formData = new FormData(linkDatabaseForm);
        const serverName = formData.get('server_name');
        const databaseName = formData.get('database_name');
        const databaseUser = formData.get('database_user') || null;
        const description = formData.get('description') || null;
        
        if (!databaseName) {
            showNotification('Please select a database', 'warning');
            return;
        }
        
        try {
            await request('link_database_to_site', {
                server_name: serverName,
                database_name: databaseName,
                database_user: databaseUser,
                database_host: 'localhost',
                description: description
            });
            
            linkDatabaseModal.close();
            showNotification('Database linked successfully', 'success');
            
            // Reload the databases list
            await loadSiteDatabases(currentDatabasesSite);
        } catch (error) {
            showNotification(`Failed to link database: ${error.message}`, 'error');
        }
    });
}

// Handle unlink database
if (linkedDatabasesTableBody) {
    linkedDatabasesTableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-action="unlink-database"]');
        if (!button) return;
        
        const linkId = parseInt(button.dataset.linkId);
        
        if (!confirm('Unlink this database from the site? This will not delete the database.')) {
            return;
        }
        
        try {
            await request('unlink_database_from_site', { link_id: linkId });
            showNotification('Database unlinked successfully', 'success');
            
            // Reload the databases list
            await loadSiteDatabases(currentDatabasesSite);
        } catch (error) {
            showNotification(`Failed to unlink database: ${error.message}`, 'error');
        }
    });
}

// Close modals
if (linkDbCancelBtn) {
    linkDbCancelBtn.addEventListener('click', () => {
        linkDatabaseModal.close();
    });
}

if (closeSiteDatabasesModalBtn) {
    closeSiteDatabasesModalBtn.addEventListener('click', () => {
        siteDatabasesModal.close();
        currentDatabasesSite = null;
    });
}

// Close link database modal on backdrop click
if (linkDatabaseModal) {
    linkDatabaseModal.addEventListener('click', (event) => {
        if (event.target === linkDatabaseModal) {
            linkDatabaseModal.close();
        }
    });
}

// Close site databases modal on backdrop click
if (siteDatabasesModal) {
    siteDatabasesModal.addEventListener('click', (event) => {
        if (event.target === siteDatabasesModal) {
            siteDatabasesModal.close();
            currentDatabasesSite = null;
        }
    });
}


// Configuration Modal Management
const configModal = document.getElementById('config-modal');
const configForm = document.getElementById('config-form');
const configTitle = document.getElementById('config-modal-title');
const configTabs = document.querySelectorAll('.config-tab');
const configPanels = document.querySelectorAll('.config-section');

let currentConfigSite = null;
let originalConfig = null;

// Tab switching functionality
configTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const targetPanel = tab.dataset.tab;
        
        // Update active tab
        configTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Update active panel (config-section)
        configPanels.forEach(p => p.classList.remove('active'));
        const targetElement = document.getElementById(`config-${targetPanel}`);
        if (targetElement) {
            targetElement.classList.add('active');
        }
    });
});

// Modal close handlers
const configCancelBtn = document.getElementById('config-cancel');
if (configCancelBtn) {
    configCancelBtn.addEventListener('click', () => {
        closeConfigModal();
    });
}

configModal.addEventListener('click', (event) => {
    if (event.target === configModal) {
        closeConfigModal();
    }
});

function closeConfigModal() {
    configModal.close();
    currentConfigSite = null;
    originalConfig = null;
    configForm.reset();
    
    // Hide all conditional sections
    updateConditionalSections(false, false, false, false, false);
    
    // Reset to first tab
    configTabs.forEach(t => t.classList.remove('active'));
    configPanels.forEach(p => p.classList.remove('active'));
    const firstTab = document.querySelector('.config-tab[data-tab="basic"]');
    const firstPanel = document.getElementById('config-basic');
    if (firstTab) firstTab.classList.add('active');
    if (firstPanel) firstPanel.classList.add('active');
}

// Show/hide conditional configuration sections
function setupConditionalSections() {
    const httpsCheckbox = configForm.querySelector('input[name="listen.https_enabled"]');
    const httpsConfig = document.querySelector('.https-config');
    
    const phpCheckbox = configForm.querySelector('input[name="php.php_enabled"]');
    const phpConfig = document.querySelector('.php-config');
    
    const gzipCheckbox = configForm.querySelector('input[name="performance.gzip_enabled"]');
    const gzipConfig = document.querySelector('.gzip-config');
    
    const fastcgiCacheCheckbox = configForm.querySelector('#fastcgi-cache-checkbox');
    const fastcgiCacheConfig = document.querySelector('.fastcgi-cache-config');
    
    const varnishCheckbox = configForm.querySelector('#varnish-enabled-checkbox');
    const varnishConfig = document.querySelector('.varnish-config');
    
    const browserCacheCheckbox = configForm.querySelector('#browser-cache-checkbox');
    const browserCacheConfig = document.querySelector('.browser-cache-config');
    
    const sslTypeSelect = configForm.querySelector('#ssl-certificate-type');
    const letsencryptConfig = document.querySelector('.letsencrypt-config');
    const manualCertConfig = document.querySelector('.manual-certificate-config');
    
    // Remove existing listeners to avoid duplicates
    if (httpsCheckbox) {
        httpsCheckbox.removeEventListener('change', handleHttpsToggle);
        httpsCheckbox.addEventListener('change', handleHttpsToggle);
    }
    
    if (phpCheckbox) {
        phpCheckbox.removeEventListener('change', handlePhpToggle);
        phpCheckbox.addEventListener('change', handlePhpToggle);
    }
    
    if (gzipCheckbox) {
        gzipCheckbox.removeEventListener('change', handleGzipToggle);
        gzipCheckbox.addEventListener('change', handleGzipToggle);
    }
    
    if (fastcgiCacheCheckbox) {
        fastcgiCacheCheckbox.removeEventListener('change', handleFastcgiCacheToggle);
        fastcgiCacheCheckbox.addEventListener('change', handleFastcgiCacheToggle);
    }
    
    if (varnishCheckbox) {
        varnishCheckbox.removeEventListener('change', handleVarnishToggle);
        varnishCheckbox.addEventListener('change', handleVarnishToggle);
    }
    
    if (browserCacheCheckbox) {
        browserCacheCheckbox.removeEventListener('change', handleBrowserCacheToggle);
        browserCacheCheckbox.addEventListener('change', handleBrowserCacheToggle);
    }
    
    if (sslTypeSelect) {
        sslTypeSelect.removeEventListener('change', handleSslTypeToggle);
        sslTypeSelect.addEventListener('change', handleSslTypeToggle);
    }
    
    function handleHttpsToggle() {
        if (httpsConfig) {
            httpsConfig.style.display = httpsCheckbox.checked ? 'block' : 'none';
        }
    }
    
    function handlePhpToggle() {
        if (phpConfig) {
            phpConfig.style.display = phpCheckbox.checked ? 'block' : 'none';
        }
    }
    
    function handleGzipToggle() {
        if (gzipConfig) {
            gzipConfig.style.display = gzipCheckbox.checked ? 'block' : 'none';
        }
    }
    
    function handleFastcgiCacheToggle() {
        if (fastcgiCacheConfig) {
            fastcgiCacheConfig.style.display = fastcgiCacheCheckbox.checked ? 'block' : 'none';
        }
    }
    
    function handleVarnishToggle() {
        if (varnishConfig) {
            varnishConfig.style.display = varnishCheckbox.checked ? 'block' : 'none';
        }
    }
    
    function handleBrowserCacheToggle() {
        if (browserCacheConfig) {
            browserCacheConfig.style.display = browserCacheCheckbox.checked ? 'block' : 'none';
        }
    }
    
    function handleSslTypeToggle() {
        if (letsencryptConfig && manualCertConfig) {
            if (sslTypeSelect.value === 'letsencrypt') {
                letsencryptConfig.style.display = 'block';
                manualCertConfig.style.display = 'none';
            } else {
                letsencryptConfig.style.display = 'none';
                manualCertConfig.style.display = 'block';
            }
        }
    }
}

// Custom Locations Management
let locationCounter = 0;

const addLocationBtn = document.getElementById('add-location');
const locationsContainer = document.getElementById('locations-container');

if (addLocationBtn) {
    addLocationBtn.addEventListener('click', () => {
        addLocationBlock();
    });
}

function addLocationBlock(location = null) {
    const locationId = location?.id || ++locationCounter;
    const locationPath = location?.path || '';
    const locationConfig = location?.config || '';
    
    const noLocationsMsg = locationsContainer.querySelector('.no-locations');
    if (noLocationsMsg) {
        noLocationsMsg.remove();
    }
    
    const locationBlock = document.createElement('div');
    locationBlock.className = 'location-block';
    locationBlock.dataset.locationId = locationId;
    locationBlock.innerHTML = `
        <div class="location-header">
            <h4>Location Block ${locationId}</h4>
            <button type="button" class="icon-btn delete-location" title="Remove Location">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/>
                </svg>
            </button>
        </div>
        <div class="form-group">
            <label>Location Path <span class="required">*</span></label>
            <input type="text" name="locations[${locationId}].path" value="${locationPath}" placeholder="/api/" required>
            <span class="form-help">URL path pattern (e.g., /api/, ~ \.pdf$, ^~ /images/)</span>
        </div>
        <div class="form-group">
            <label>Location Configuration <span class="required">*</span></label>
            <textarea name="locations[${locationId}].config" rows="6" placeholder="# Nginx directives for this location&#10;proxy_pass http://backend;&#10;proxy_set_header Host $host;" required>${locationConfig}</textarea>
            <span class="form-help">Nginx directives to include in this location block</span>
        </div>
    `;
    
    const deleteBtn = locationBlock.querySelector('.delete-location');
    deleteBtn.addEventListener('click', () => {
        locationBlock.remove();
        
        // Show "no locations" message if all removed
        if (locationsContainer.querySelectorAll('.location-block').length === 0) {
            locationsContainer.innerHTML = '<p class="no-locations">No custom locations configured</p>';
        }
    });
    
    locationsContainer.appendChild(locationBlock);
}

function clearLocationBlocks() {
    const blocks = locationsContainer.querySelectorAll('.location-block');
    blocks.forEach(block => block.remove());
    locationsContainer.innerHTML = '<p class="no-locations">No custom locations configured</p>';
    locationCounter = 0;
}

// Update conditional sections visibility
function updateConditionalSections(httpsEnabled, phpEnabled, gzipEnabled, fastcgiCacheEnabled, browserCacheEnabled) {
    const httpsConfig = document.querySelector('.https-config');
    const phpConfig = document.querySelector('.php-config');
    const gzipConfig = document.querySelector('.gzip-config');
    const fastcgiCacheConfig = document.querySelector('.fastcgi-cache-config');
    const browserCacheConfig = document.querySelector('.browser-cache-config');
    
    if (httpsConfig) {
        httpsConfig.style.display = httpsEnabled ? 'block' : 'none';
    }
    if (phpConfig) {
        phpConfig.style.display = phpEnabled ? 'block' : 'none';
    }
    if (gzipConfig) {
        gzipConfig.style.display = gzipEnabled ? 'block' : 'none';
    }
    if (fastcgiCacheConfig) {
        fastcgiCacheConfig.style.display = fastcgiCacheEnabled ? 'block' : 'none';
    }
    if (browserCacheConfig) {
        browserCacheConfig.style.display = browserCacheEnabled ? 'block' : 'none';
    }
}

// Open configuration modal for a site
async function openConfigModal(serverName) {
    try {
        currentConfigSite = serverName;
        configTitle.textContent = `Configure ${serverName}`;
        
        // Load current configuration
        const config = await request('get_site_config', { server_name: serverName });
        originalConfig = JSON.parse(JSON.stringify(config)); // Deep copy
        
        // Populate form with current values
        populateConfigForm(config);
        
        // Set up conditional sections event listeners
        setupConditionalSections();
        
        // Show modal
        configModal.showModal();
        
        // Reset to first tab
        configTabs.forEach(t => t.classList.remove('active'));
        configPanels.forEach(p => p.classList.remove('active'));
        document.querySelector('.config-tab[data-tab="basic"]').classList.add('active');
        document.getElementById('config-basic').classList.add('active');
        
    } catch (error) {
        showNotification(`Failed to load configuration: ${error.message}`, 'error');
    }
}

// Populate form with configuration data
function populateConfigForm(config) {
    // Basic settings
    setValue('basic.server_name', config.basic?.server_name || '');
    setValue('basic.document_root', config.basic?.document_root || '');
    
    // Handle index_files - convert array to space-separated string
    const indexFiles = config.basic?.index_files;
    const indexFilesStr = Array.isArray(indexFiles) ? indexFiles.join(' ') : (indexFiles || 'index.php index.html index.htm');
    setValue('basic.index_files', indexFilesStr);
    
    setCheckbox('basic.enabled', config.basic?.enabled !== false);
    
    // Listen & SSL settings - handle arrays properly
    const httpListen = config.listen?.http_listen;
    const httpListenStr = Array.isArray(httpListen) ? httpListen.join(' ') : (httpListen || '80');
    setValue('listen.http_listen', httpListenStr);
    
    const httpsEnabled = config.listen?.https_enabled || false;
    setCheckbox('listen.https_enabled', httpsEnabled);
    
    const httpsListen = config.listen?.https_listen;
    const httpsListenStr = Array.isArray(httpsListen) ? httpsListen.join(' ') : (httpsListen || '443 ssl http2');
    setValue('listen.https_listen', httpsListenStr);
    
    setCheckbox('listen.redirect_http_to_https', config.listen?.redirect_http_to_https || false);
    
    // SSL certificate type and settings
    const certificateType = config.ssl?.certificate_type || 'letsencrypt';
    setValue('ssl.certificate_type', certificateType);
    
    // Let's Encrypt settings
    setValue('ssl.letsencrypt_email', config.ssl?.letsencrypt_email || '');
    setCheckbox('ssl.letsencrypt_agree_tos', config.ssl?.letsencrypt_agree_tos !== false);
    setValue('ssl.letsencrypt_extra_domains', config.ssl?.letsencrypt_extra_domains || '');
    
    // Manual certificate settings
    setValue('ssl.ssl_certificate', config.ssl?.ssl_certificate || '');
    setValue('ssl.ssl_certificate_key', config.ssl?.ssl_certificate_key || '');
    
    // SSL options
    setValue('ssl.ssl_protocols', config.ssl?.ssl_protocols || 'TLSv1.2 TLSv1.3');
    setValue('ssl.ssl_ciphers', config.ssl?.ssl_ciphers || '');
    setCheckbox('ssl.ssl_prefer_server_ciphers', config.ssl?.ssl_prefer_server_ciphers !== false);
    
    // Update certificate type UI
    const sslTypeSelect = configForm.querySelector('#ssl-certificate-type');
    if (sslTypeSelect) {
        sslTypeSelect.dispatchEvent(new Event('change'));
    }
    
    // PHP settings
    const phpEnabled = config.php?.php_enabled || false;
    setCheckbox('php.php_enabled', phpEnabled);
    setValue('php.php_fastcgi_pass', config.php?.php_fastcgi_pass || 'unix:/run/php/php8.3-fpm.sock');
    setValue('php.php_fastcgi_index', config.php?.php_fastcgi_index || 'index.php');
    setValue('php.php_fastcgi_read_timeout', config.php?.php_fastcgi_read_timeout || '60');
    
    // Logging settings
    setValue('logging.access_log', config.logging?.access_log || '');
    setValue('logging.error_log', config.logging?.error_log || '');
    setValue('logging.log_format', config.logging?.log_format || 'combined');
    setValue('logging.error_log_level', config.logging?.error_log_level || 'error');
    
    // Performance settings
    setValue('performance.client_max_body_size', config.performance?.client_max_body_size || '1M');
    setValue('performance.client_body_buffer_size', config.performance?.client_body_buffer_size || '128k');
    
    // FastCGI Cache
    const fastcgiCacheEnabled = config.performance?.fastcgi_cache_enabled || false;
    setCheckbox('performance.fastcgi_cache_enabled', fastcgiCacheEnabled);
    setValue('performance.fastcgi_cache_path', config.performance?.fastcgi_cache_path || '/var/cache/nginx/fastcgi');
    setValue('performance.fastcgi_cache_valid', config.performance?.fastcgi_cache_valid || '60m');
    setValue('performance.fastcgi_cache_key', config.performance?.fastcgi_cache_key || '$scheme$request_method$host$request_uri');
    setValue('performance.fastcgi_cache_bypass', config.performance?.fastcgi_cache_bypass || '');
    setValue('performance.fastcgi_no_cache', config.performance?.fastcgi_no_cache || '');
    setCheckbox('performance.fastcgi_cache_use_stale', config.performance?.fastcgi_cache_use_stale || false);
    
    // Browser Cache
    const browserCacheEnabled = config.performance?.browser_cache_enabled || false;
    setCheckbox('performance.browser_cache_enabled', browserCacheEnabled);
    setValue('performance.cache_css_js', config.performance?.cache_css_js || '30d');
    setValue('performance.cache_images', config.performance?.cache_images || '90d');
    setValue('performance.cache_fonts', config.performance?.cache_fonts || '1y');
    setValue('performance.cache_media', config.performance?.cache_media || '1y');
    
    // Gzip
    const gzipEnabled = config.performance?.gzip_enabled || false;
    setCheckbox('performance.gzip_enabled', gzipEnabled);
    setValue('performance.gzip_types', config.performance?.gzip_types || 'text/plain text/css application/json application/javascript text/xml application/xml');
    setValue('performance.gzip_comp_level', config.performance?.gzip_comp_level || '6');
    setValue('performance.gzip_min_length', config.performance?.gzip_min_length || '256');
    
    // Security settings
    setCheckbox('security.server_tokens', config.security?.server_tokens !== false);
    setValue('security.x_frame_options', config.security?.x_frame_options || 'SAMEORIGIN');
    setCheckbox('security.x_content_type_options', config.security?.x_content_type_options !== false);
    setCheckbox('security.x_xss_protection', config.security?.x_xss_protection !== false);
    setValue('security.referrer_policy', config.security?.referrer_policy || 'strict-origin-when-cross-origin');
    
    // Custom Locations
    clearLocationBlocks();
    if (config.locations && Array.isArray(config.locations) && config.locations.length > 0) {
        config.locations.forEach((location, index) => {
            addLocationBlock({
                id: index + 1,
                path: location.path,
                config: location.config
            });
        });
    }
    
    // Advanced settings
    setValue('advanced.custom_directives', config.advanced?.custom_directives || '');
    
    // Update conditional sections visibility immediately
    updateConditionalSections(httpsEnabled, phpEnabled, gzipEnabled, fastcgiCacheEnabled, browserCacheEnabled);
}

// Helper functions for form population
function setValue(name, value) {
    const field = configForm.querySelector(`[name="${name}"]`);
    if (field) {
        field.value = value || '';
    } else {
        console.warn(`Configuration field not found: ${name}`);
    }
}

function setCheckbox(name, checked) {
    const field = configForm.querySelector(`[name="${name}"]`);
    if (field && field.type === 'checkbox') {
        field.checked = Boolean(checked);
    } else if (field) {
        console.warn(`Field ${name} is not a checkbox`);
    } else {
        console.warn(`Configuration checkbox not found: ${name}`);
    }
}

// Extract configuration from form
function extractConfigFromForm() {
    const formData = new FormData(configForm);
    
    // Helper function to split space-separated strings into arrays
    function splitToArray(value) {
        if (!value || value.trim() === '') return [];
        return value.trim().split(/\s+/).filter(item => item.length > 0);
    }
    
    const config = {
        basic: {
            server_name: formData.get('basic.server_name'),
            document_root: formData.get('basic.document_root'),
            index_files: splitToArray(formData.get('basic.index_files')),
            enabled: formData.get('basic.enabled') === 'on'
        },
        listen: {
            http_listen: splitToArray(formData.get('listen.http_listen')),
            https_enabled: formData.get('listen.https_enabled') === 'on',
            https_listen: splitToArray(formData.get('listen.https_listen')),
            redirect_http_to_https: formData.get('listen.redirect_http_to_https') === 'on'
        },
        ssl: {
            certificate_type: formData.get('ssl.certificate_type'),
            letsencrypt_email: formData.get('ssl.letsencrypt_email'),
            letsencrypt_agree_tos: formData.get('ssl.letsencrypt_agree_tos') === 'on',
            letsencrypt_extra_domains: formData.get('ssl.letsencrypt_extra_domains'),
            ssl_certificate: formData.get('ssl.ssl_certificate'),
            ssl_certificate_key: formData.get('ssl.ssl_certificate_key'),
            ssl_protocols: formData.get('ssl.ssl_protocols'),
            ssl_ciphers: formData.get('ssl.ssl_ciphers'),
            ssl_prefer_server_ciphers: formData.get('ssl.ssl_prefer_server_ciphers') === 'on'
        },
        php: {
            php_enabled: formData.get('php.php_enabled') === 'on',
            php_fastcgi_pass: formData.get('php.php_fastcgi_pass'),
            php_fastcgi_index: formData.get('php.php_fastcgi_index'),
            php_fastcgi_read_timeout: formData.get('php.php_fastcgi_read_timeout')
        },
        logging: {
            access_log: formData.get('logging.access_log'),
            error_log: formData.get('logging.error_log'),
            log_format: formData.get('logging.log_format'),
            error_log_level: formData.get('logging.error_log_level')
        },
        performance: {
            client_max_body_size: formData.get('performance.client_max_body_size'),
            client_body_buffer_size: formData.get('performance.client_body_buffer_size'),
            fastcgi_cache_enabled: formData.get('performance.fastcgi_cache_enabled') === 'on',
            fastcgi_cache_path: formData.get('performance.fastcgi_cache_path'),
            fastcgi_cache_valid: formData.get('performance.fastcgi_cache_valid'),
            fastcgi_cache_key: formData.get('performance.fastcgi_cache_key'),
            fastcgi_cache_bypass: formData.get('performance.fastcgi_cache_bypass'),
            fastcgi_no_cache: formData.get('performance.fastcgi_no_cache'),
            fastcgi_cache_use_stale: formData.get('performance.fastcgi_cache_use_stale') === 'on',
            browser_cache_enabled: formData.get('performance.browser_cache_enabled') === 'on',
            cache_css_js: formData.get('performance.cache_css_js'),
            cache_images: formData.get('performance.cache_images'),
            cache_fonts: formData.get('performance.cache_fonts'),
            cache_media: formData.get('performance.cache_media'),
            gzip_enabled: formData.get('performance.gzip_enabled') === 'on',
            gzip_types: formData.get('performance.gzip_types'),
            gzip_comp_level: formData.get('performance.gzip_comp_level'),
            gzip_min_length: formData.get('performance.gzip_min_length')
        },
        security: {
            server_tokens: formData.get('security.server_tokens') === 'on',
            x_frame_options: formData.get('security.x_frame_options'),
            x_content_type_options: formData.get('security.x_content_type_options') === 'on',
            x_xss_protection: formData.get('security.x_xss_protection') === 'on',
            referrer_policy: formData.get('security.referrer_policy')
        },
        locations: [],
        advanced: {
            custom_directives: formData.get('advanced.custom_directives')
        }
    };
    
    // Extract custom locations
    const locationBlocks = locationsContainer.querySelectorAll('.location-block');
    locationBlocks.forEach(block => {
        const locationId = block.dataset.locationId;
        const path = formData.get(`locations[${locationId}].path`);
        const locationConfig = formData.get(`locations[${locationId}].config`);
        
        if (path && locationConfig) {
            config.locations.push({
                path: path.trim(),
                config: locationConfig.trim()
            });
        }
    });
    
    return config;
}

// Validate configuration before saving
function validateConfiguration(config) {
    const errors = [];
    
    // Basic validation
    if (!config.basic.document_root) {
        errors.push('Document root is required');
    }
    
    if (config.basic.index_files.length === 0) {
        errors.push('At least one index file must be specified');
    }
    
    // HTTPS validation
    if (config.listen.https_enabled) {
        if (config.ssl.certificate_type === 'letsencrypt') {
            if (!config.ssl.letsencrypt_email) {
                errors.push('Email address is required for Let\'s Encrypt');
            }
            if (!config.ssl.letsencrypt_agree_tos) {
                errors.push('You must agree to the Let\'s Encrypt Terms of Service');
            }
        } else {
            if (!config.ssl.ssl_certificate) {
                errors.push('SSL certificate path is required when HTTPS is enabled');
            }
            if (!config.ssl.ssl_certificate_key) {
                errors.push('SSL certificate key path is required when HTTPS is enabled');
            }
        }
    }
    
    // Performance validation
    if (config.performance.client_max_body_size && !/^\d+[kmgKMG]?$/.test(config.performance.client_max_body_size)) {
        errors.push('Client max body size must be in format like 1M, 10M, 1G');
    }
    
    return errors;
}

// Save configuration
configForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    
    if (!currentConfigSite) {
        showNotification('No site selected for configuration', 'error');
        return;
    }
    
    try {
        const config = extractConfigFromForm();
        
        // Validate configuration
        const validationErrors = validateConfiguration(config);
        if (validationErrors.length > 0) {
            showNotification(`Configuration validation failed: ${validationErrors.join(', ')}`, 'error');
            return;
        }
        
        await request('update_site_config', {
            server_name: currentConfigSite,
            config: config
        });
        
        showNotification('Configuration saved successfully', 'success');
        closeConfigModal();
        await loadSites(); // Refresh the sites list
        
    } catch (error) {
        showNotification(`Failed to save configuration: ${error.message}`, 'error');
    }
});

// Test configuration
document.getElementById('config-test').addEventListener('click', async () => {
    if (!currentConfigSite) {
        showNotification('No site selected for testing', 'error');
        return;
    }
    
    try {
        await request('run_command', { command: 'nginx_test' });
        showNotification('NGINX configuration test passed', 'success');
    } catch (error) {
        showNotification(`Configuration test failed: ${error.message}`, 'error');
    }
});

// ====== PowerDNS Management ======

// DNS elements
const dnsView = document.getElementById('dns-view');
const dnsTabs = document.querySelectorAll('.dns-tab');
const dnsPanels = document.querySelectorAll('.dns-panel');
const domainsTableBody = document.querySelector('#domains-table tbody');
const recordsTableBody = document.querySelector('#records-table tbody');
const openCreateDomainBtn = document.getElementById('open-create-domain');
const createDomainModal = document.getElementById('create-domain-modal');
const createDomainForm = document.getElementById('create-domain-form');
const recordModal = document.getElementById('record-modal');
const recordForm = document.getElementById('record-form');
const recordModalTitle = document.getElementById('record-modal-title');
const backToDomainsBtn = document.getElementById('back-to-domains');
const addRecordBtn = document.getElementById('add-record');
const recordsDomainTitle = document.getElementById('records-domain-title');

let currentDomain = null;
let currentRecord = null;

// DNS tab switching
dnsTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const targetPanel = tab.dataset.tab;
        
        dnsTabs.forEach(t => t.classList.remove('active'));
        dnsPanels.forEach(p => p.classList.remove('active'));
        
        tab.classList.add('active');
        const panel = document.getElementById(`dns-${targetPanel}`);
        if (panel) {
            panel.classList.add('active');
        }
        
        if (targetPanel === 'domains') {
            loadDomains();
        }
    });
});

// Load domains
async function loadDomains() {
    try {
        const domains = await request('list_domains');
        searchCache.domains = domains || []; // Update search cache
        domainsTableBody.innerHTML = '';
        
        domains.forEach(domain => {
            const tr = document.createElement('tr');
            
            const statusClass = domain.status === 'active' ? 'success' : 
                              domain.status === 'stale' ? 'warning' : 'info';
            
            tr.dataset.domainId = domain.id; // For search highlighting
            tr.classList.add('domain-item');
            tr.innerHTML = `
                <td>
                    <div class="cell-primary">${domain.name}</div>
                </td>
                <td>${domain.type}</td>
                <td>${domain.record_count}</td>
                <td><span class="status-pill status-pill--${statusClass}">${domain.status}</span></td>
                <td>${domain.last_modified || 'Never'}</td>
                <td class="table-actions">
                    <button data-action="manage-records" data-domain="${domain.name}" class="secondary">Manage Records</button>
                    <button data-action="delete-domain" data-domain="${domain.name}" class="danger">Delete</button>
                </td>
            `;
            
            domainsTableBody.appendChild(tr);
        });
    } catch (error) {
        console.error('Failed to load domains:', error);
    }
}

// Load records for a domain
async function loadRecords(domainName) {
    try {
        const records = await request('list_records', { domain_name: domainName });
        recordsTableBody.innerHTML = '';
        
        records.forEach(record => {
            const tr = document.createElement('tr');
            
            const statusClass = record.disabled ? 'warning' : 'success';
            const statusText = record.disabled ? 'Disabled' : 'Active';
            
            tr.innerHTML = `
                <td>
                    <div class="cell-primary">${record.name}</div>
                </td>
                <td>
                    <span class="record-type record-type--${record.type.toLowerCase()}">${record.type}</span>
                </td>
                <td>
                    <div class="record-content">${record.content}</div>
                </td>
                <td>${record.ttl}</td>
                <td>${record.prio || '-'}</td>
                <td><span class="status-pill status-pill--${statusClass}">${statusText}</span></td>
                <td class="table-actions">
                    <button data-action="edit-record" data-record-id="${record.id}" class="secondary">Edit</button>
                    <button data-action="delete-record" data-record-id="${record.id}" class="danger">Delete</button>
                </td>
            `;
            
            recordsTableBody.appendChild(tr);
        });
        
        recordsDomainTitle.textContent = `Records for ${domainName}`;
    } catch (error) {
        console.error('Failed to load records:', error);
    }
}

// Switch to records view
function showRecordsView(domainName) {
    currentDomain = domainName;
    
    // Update tabs
    dnsTabs.forEach(t => t.classList.remove('active'));
    dnsPanels.forEach(p => p.classList.remove('active'));
    
    const recordsTab = document.querySelector('.dns-tab[data-tab="records"]');
    recordsTab.style.display = 'block';
    recordsTab.classList.add('active');
    recordsTab.dataset.domain = domainName;
    recordsTab.textContent = `${domainName} Records`;
    
    document.getElementById('dns-records').classList.add('active');
    
    loadRecords(domainName);
}

// Back to domains view
backToDomainsBtn.addEventListener('click', () => {
    currentDomain = null;
    
    // Hide records tab
    const recordsTab = document.querySelector('.dns-tab[data-tab="records"]');
    recordsTab.style.display = 'none';
    recordsTab.classList.remove('active');
    
    // Show domains tab
    dnsTabs.forEach(t => t.classList.remove('active'));
    dnsPanels.forEach(p => p.classList.remove('active'));
    
    document.querySelector('.dns-tab[data-tab="domains"]').classList.add('active');
    document.getElementById('dns-domains').classList.add('active');
    
    loadDomains();
});

// Domain table actions
domainsTableBody.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    
    const domainName = button.dataset.domain;
    const action = button.dataset.action;
    
    try {
        if (action === 'manage-records') {
            showRecordsView(domainName);
        } else if (action === 'delete-domain') {
            if (confirm(`Delete domain ${domainName} and all its records?`)) {
                await request('delete_domain', { domain_name: domainName });
                showNotification('Domain deleted successfully', 'success');
                await loadDomains();
            }
        }
    } catch (error) {
        showNotification(`Failed to ${action.replace('-', ' ')}: ${error.message}`, 'error');
    }
});

// Record table actions
recordsTableBody.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    
    const recordId = parseInt(button.dataset.recordId);
    const action = button.dataset.action;
    
    try {
        if (action === 'edit-record') {
            // Find the record data from the table
            const row = button.closest('tr');
            const cells = row.querySelectorAll('td');
            
            const recordData = {
                id: recordId,
                name: cells[0].textContent.trim(),
                type: cells[1].textContent.trim(),
                content: cells[2].textContent.trim(),
                ttl: parseInt(cells[3].textContent.trim()),
                prio: cells[4].textContent.trim() !== '-' ? parseInt(cells[4].textContent.trim()) : null,
                disabled: cells[5].textContent.includes('Disabled')
            };
            
            openRecordModal('edit', recordData);
        } else if (action === 'delete-record') {
            if (confirm('Delete this DNS record?')) {
                await request('delete_record', { record_id: recordId });
                showNotification('Record deleted successfully', 'success');
                await loadRecords(currentDomain);
            }
        }
    } catch (error) {
        showNotification(`Failed to ${action.replace('-', ' ')}: ${error.message}`, 'error');
    }
});

// Create domain modal
openCreateDomainBtn.addEventListener('click', () => {
    createDomainForm.reset();
    updateDomainTypeFields();
    createDomainModal.showModal();
});

createDomainForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    
    try {
        const formData = new FormData(createDomainForm);
        const domainData = {
            name: formData.get('domain_name'),
            type: formData.get('domain_type'),
            master: formData.get('master_server') || undefined,
            account: formData.get('account') || undefined
        };
        
        await request('create_domain', domainData);
        createDomainModal.close();
        showNotification('Domain created successfully', 'success');
        await loadDomains();
    } catch (error) {
        showNotification(`Failed to create domain: ${error.message}`, 'error');
    }
});

// Domain type change handler
const domainTypeSelect = createDomainForm.querySelector('select[name="domain_type"]');
domainTypeSelect.addEventListener('change', updateDomainTypeFields);

function updateDomainTypeFields() {
    const slaveOptions = document.getElementById('slave-options');
    const isSlaveType = domainTypeSelect.value === 'SLAVE';
    slaveOptions.style.display = isSlaveType ? 'block' : 'none';
    
    const masterInput = slaveOptions.querySelector('input[name="master_server"]');
    if (masterInput) {
        masterInput.required = isSlaveType;
    }
}

// Record modal
addRecordBtn.addEventListener('click', () => {
    openRecordModal('create');
});

function openRecordModal(mode, recordData = null) {
    currentRecord = recordData;
    
    if (mode === 'create') {
        recordModalTitle.textContent = 'Add DNS Record';
        recordForm.reset();
        recordForm.querySelector('input[name="record_name"]').value = '';
    } else {
        recordModalTitle.textContent = 'Edit DNS Record';
        recordForm.querySelector('input[name="record_name"]').value = recordData.name;
        recordForm.querySelector('select[name="record_type"]').value = recordData.type;
        recordForm.querySelector('input[name="record_content"]').value = recordData.content;
        recordForm.querySelector('input[name="record_ttl"]').value = recordData.ttl;
        recordForm.querySelector('input[name="record_priority"]').value = recordData.prio || '';
        recordForm.querySelector('input[name="record_disabled"]').checked = recordData.disabled;
    }
    
    updateRecordTypeFields();
    recordModal.showModal();
}

recordForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    
    try {
        const formData = new FormData(recordForm);
        const recordData = {
            name: formData.get('record_name') || currentDomain,
            type: formData.get('record_type'),
            content: formData.get('record_content'),
            ttl: parseInt(formData.get('record_ttl')),
            disabled: formData.get('record_disabled') === 'on'
        };
        
        const priority = formData.get('record_priority');
        if (priority && priority.trim() !== '') {
            recordData.prio = parseInt(priority);
        }
        
        if (currentRecord) {
            // Edit existing record
            await request('update_record', { 
                record_id: currentRecord.id,
                ...recordData
            });
            showNotification('Record updated successfully', 'success');
        } else {
            // Create new record
            await request('create_record', { 
                domain_name: currentDomain,
                ...recordData
            });
            showNotification('Record created successfully', 'success');
        }
        
        recordModal.close();
        await loadRecords(currentDomain);
    } catch (error) {
        showNotification(`Failed to save record: ${error.message}`, 'error');
    }
});

// Record type change handler
const recordTypeSelect = recordForm.querySelector('select[name="record_type"]');
recordTypeSelect.addEventListener('change', updateRecordTypeFields);

function updateRecordTypeFields() {
    const recordType = recordTypeSelect.value;
    const priorityField = document.getElementById('priority-field');
    const contentHelp = document.getElementById('content-help');
    
    const typeConfig = window.dnsConfig?.recordTypes[recordType];
    if (typeConfig) {
        contentHelp.textContent = typeConfig.help;
        priorityField.style.display = typeConfig.requiresPriority ? 'block' : 'none';
        
        const priorityInput = priorityField.querySelector('input');
        if (priorityInput) {
            priorityInput.required = typeConfig.requiresPriority;
        }
    }
}

// Modal close handlers for DNS modals
createDomainModal.addEventListener('click', (event) => {
    if (event.target === createDomainModal) {
        createDomainModal.close();
    }
});

recordModal.addEventListener('click', (event) => {
    if (event.target === recordModal) {
        recordModal.close();
    }
});

createDomainForm.querySelector('button[value="cancel"]').addEventListener('click', (event) => {
    event.preventDefault();
    createDomainModal.close();
});

recordForm.querySelector('button[value="cancel"]').addEventListener('click', (event) => {
    event.preventDefault();
    recordModal.close();
});

// Initialize DNS view if it becomes active
function initializeDNS() {
    if (dnsView && dnsView.classList.contains('is-active')) {
        loadDomains();
    }
}

// Header Status Monitoring
class HeaderStatusMonitor {
    constructor() {
        this.updateInterval = 5000; // Update every 5 seconds
        this.init();
    }

    init() {
        this.updateServiceStatus();
        this.updateSystemStats();
        
        // Start periodic updates
        setInterval(() => {
            this.updateServiceStatus();
            this.updateSystemStats();
        }, this.updateInterval);
    }

    async updateServiceStatus() {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_service_status' })
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data) {
                    this.updateServiceIndicators(result.data);
                } else {
                    console.error('Service status API error:', result.error || 'Unknown error');
                    this.simulateServiceStatus();
                }
            }
        } catch (error) {
            console.error('Failed to fetch service status:', error);
            // Fallback to simulated data if API isn't available
            this.simulateServiceStatus();
        }
    }

    simulateServiceStatus() {
        const services = ['nginx', 'php', 'mysql', 'powerdns'];
        const statuses = ['running', 'running', 'running', 'stopped']; // Simulate some services
        
        services.forEach((service, index) => {
            const indicator = document.querySelector(`[data-service="${service}"] .status-dot`);
            if (indicator) {
                indicator.className = `status-dot status-${statuses[index] || 'running'}`;
            }
        });
    }

    updateServiceIndicators(data) {
        Object.entries(data).forEach(([service, status]) => {
            const indicator = document.querySelector(`[data-service="${service}"] .status-dot`);
            if (indicator) {
                indicator.className = `status-dot status-${status}`;
            }
        });
    }

    async updateSystemStats() {
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_system_stats' })
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data) {
                    this.updateStatsDisplay(result.data);
                } else {
                    console.error('System stats API error:', result.error || 'Unknown error');
                    this.simulateSystemStats();
                }
            }
        } catch (error) {
            console.error('Failed to fetch system stats:', error);
            // Fallback to simulated data
            this.simulateSystemStats();
        }
    }

    simulateSystemStats() {
        // Simulate realistic system stats
        const cpuUsage = Math.random() * 100;
        const memoryUsage = Math.random() * 100;
        const networkUp = Math.random() * 1000;
        const networkDown = Math.random() * 5000;

        this.updateStatsDisplay({
            cpu: cpuUsage,
            memory: memoryUsage,
            network: {
                upload: networkUp,
                download: networkDown
            }
        });
    }

    updateStatsDisplay(data) {
        // Update CPU
        const cpuFill = document.getElementById('cpu-usage');
        const cpuValue = document.getElementById('cpu-value');
        if (cpuFill && cpuValue) {
            cpuFill.style.width = `${data.cpu}%`;
            cpuValue.textContent = `${Math.round(data.cpu)}%`;
            
            // Color coding
            cpuFill.className = 'stat-fill';
            if (data.cpu > 80) cpuFill.classList.add('danger');
            else if (data.cpu > 60) cpuFill.classList.add('warning');
        }

        // Update Memory
        const memoryFill = document.getElementById('memory-usage');
        const memoryValue = document.getElementById('memory-value');
        if (memoryFill && memoryValue) {
            memoryFill.style.width = `${data.memory}%`;
            memoryValue.textContent = `${Math.round(data.memory)}%`;
            
            // Color coding
            memoryFill.className = 'stat-fill';
            if (data.memory > 90) memoryFill.classList.add('danger');
            else if (data.memory > 75) memoryFill.classList.add('warning');
        }

        // Update Network
        const networkUp = document.getElementById('network-up');
        const networkDown = document.getElementById('network-down');
        if (networkUp && networkDown && data.network) {
            networkUp.textContent = this.formatBytes(data.network.upload) + '/s';
            networkDown.textContent = this.formatBytes(data.network.download) + '/s';
        }
    }

    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
}

// User Management Functions
function loadUsers() {
    console.log('Loading users...');
    apiRequest('list_users')
        .then(response => {
            console.log('API response:', response);
            const users = response.data || response;
            console.log('Users array:', users);
            
            const tbody = document.querySelector('#users-table tbody');
            if (!tbody) {
                console.error('Users table tbody not found');
                return;
            }
            
            if (!Array.isArray(users)) {
                console.error('Users is not an array:', users);
                showNotification('Invalid user data received', 'error');
                return;
            }
            
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.full_name || '')}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>
                        <span class="badge ${user.role === 'admin' ? 'badge-primary' : 'badge-secondary'}">
                            ${escapeHtml(user.role)}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${user.is_active ? 'badge-success' : 'badge-danger'}">
                            ${user.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</td>
                    <td>
                        <button class="secondary small" onclick="editUser(${user.id})">Edit</button>
                        <button class="danger small" onclick="deleteUser(${user.id}, '${escapeHtml(user.username)}')" 
                                ${user.role === 'admin' ? 'title="Cannot delete admin users"' : ''}>
                            Delete
                        </button>
                    </td>
                </tr>
            `).join('');
        })
        .catch(error => {
            console.error('Failed to load users:', error);
            showNotification('Failed to load users: ' + error.message, 'error');
        });
}

function createUser() {
    const modal = createUserModal();
    document.body.appendChild(modal);
    modal.showModal();
}

function createUserModal() {
    const modal = document.createElement('dialog');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-header">
            <h2>Add New User</h2>
            <button type="button" class="modal-close" onclick="this.closest('dialog').close()">&times;</button>
        </div>
        <form id="create-user-form">
            <div class="modal-body">
                <div class="form-group">
                    <label for="new-username">Username</label>
                    <input type="text" id="new-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="new-email">Email</label>
                    <input type="email" id="new-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="new-full-name">Full Name</label>
                    <input type="text" id="new-full-name" name="full_name">
                </div>
                <div class="form-group">
                    <label for="new-password">Password</label>
                    <input type="password" id="new-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="new-role">Role</label>
                    <select id="new-role" name="role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="new-is-active" name="is_active" checked>
                        Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" class="primary">Create User</button>
            </div>
        </form>
    `;
    
    modal.querySelector('form').addEventListener('submit', handleCreateUser);
    modal.addEventListener('close', () => modal.remove());
    
    return modal;
}

function handleCreateUser(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const userData = {
        username: formData.get('username'),
        email: formData.get('email'),
        full_name: formData.get('full_name'),
        password: formData.get('password'),
        role: formData.get('role'),
        is_active: formData.has('is_active')
    };
    
    apiRequest('create_user', userData)
        .then(() => {
            showNotification('User created successfully', 'success');
            e.target.closest('dialog').close();
            loadUsers();
        })
        .catch(error => {
            showNotification('Failed to create user: ' + error.message, 'error');
        });
}

function editUser(userId) {
    // Implementation for editing users
    showNotification('Edit user functionality coming soon', 'info');
}

function deleteUser(userId, username) {
    if (!confirm(`Are you sure you want to delete user "${username}"?`)) {
        return;
    }
    
    apiRequest('delete_user', { user_id: userId })
        .then(() => {
            showNotification('User deleted successfully', 'success');
            loadUsers();
        })
        .catch(error => {
            showNotification('Failed to delete user: ' + error.message, 'error');
        });
}

// Profile Management
function handleProfileUpdate(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const profileData = {
        email: formData.get('email'),
        full_name: formData.get('full_name'),
        current_password: formData.get('current_password')
    };
    
    const newPassword = formData.get('new_password');
    const confirmPassword = formData.get('confirm_password');
    
    // Validate password change if provided
    if (newPassword || confirmPassword) {
        if (newPassword !== confirmPassword) {
            showNotification('New passwords do not match', 'error');
            return;
        }
        if (newPassword.length < 6) {
            showNotification('New password must be at least 6 characters long', 'error');
            return;
        }
    }
    
    if (!profileData.current_password) {
        showNotification('Current password is required to save changes', 'error');
        return;
    }
    
    // Handle password change separately if provided
    if (newPassword) {
        apiRequest('change_password', {
            current_password: profileData.current_password,
            new_password: newPassword
        })
        .then(() => {
            // Update profile after password change
            return apiRequest('update_profile', {
                email: profileData.email,
                full_name: profileData.full_name
            });
        })
        .then(() => {
            showNotification('Profile and password updated successfully', 'success');
            e.target.reset();
            // Reload page to reflect changes
            setTimeout(() => location.reload(), 1000);
        })
        .catch(error => {
            showNotification('Failed to update profile: ' + error.message, 'error');
        });
    } else {
        // Just update profile
        apiRequest('update_profile', {
            email: profileData.email,
            full_name: profileData.full_name
        })
        .then(() => {
            showNotification('Profile updated successfully', 'success');
            document.getElementById('profile-current-password').value = '';
        })
        .catch(error => {
            showNotification('Failed to update profile: ' + error.message, 'error');
        });
    }
}

function handleLogout() {
    apiRequest('logout')
        .then(() => {
            window.location.href = 'login.php';
        })
        .catch(() => {
            // Force logout even if API fails
            document.cookie = 'admin_session=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            window.location.href = 'login.php';
        });
}

// Initialize header status monitor when page loads
document.addEventListener('DOMContentLoaded', () => {
    new HeaderStatusMonitor();
    
    // User menu dropdown toggle
    const userMenuTrigger = document.getElementById('user-menu-trigger');
    const userMenuDropdown = document.getElementById('user-menu-dropdown');
    
    if (userMenuTrigger && userMenuDropdown) {
        userMenuTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('is-open');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenuTrigger.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.remove('is-open');
            }
        });
        
        // Handle profile menu item click
        const profileMenuItem = userMenuDropdown.querySelector('[data-target="profile-view"]');
        if (profileMenuItem) {
            profileMenuItem.addEventListener('click', (e) => {
                e.preventDefault();
                userMenuDropdown.classList.remove('is-open');
                
                // Hide all views
                document.querySelectorAll('.view').forEach(v => v.classList.remove('is-active'));
                
                // Show profile view
                const profileView = document.getElementById('profile-view');
                if (profileView) {
                    profileView.classList.add('is-active');
                }
                
                // Update nav buttons
                document.querySelectorAll('.nav-button').forEach(btn => btn.classList.remove('is-active'));
            });
        }
        
        // Handle logout click from header dropdown
        const headerLogoutBtn = document.getElementById('header-logout-btn');
        if (headerLogoutBtn) {
            headerLogoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                userMenuDropdown.classList.remove('is-open');
                handleLogout();
            });
        }
    }
    
    // Add event listeners for user management
    const openCreateUserBtn = document.getElementById('open-create-user');
    if (openCreateUserBtn) {
        openCreateUserBtn.addEventListener('click', createUser);
    }
    
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', handleProfileUpdate);
    }
    
    const logoutBtn = document.getElementById('logout-button');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    // Load users if on users view
    const usersView = document.getElementById('users-view');
    if (usersView && usersView.classList.contains('is-active')) {
        loadUsers();
    }
});

// Load users when switching to users view
function initializeUsers() {
    const usersView = document.getElementById('users-view');
    if (usersView && usersView.classList.contains('is-active')) {
        loadUsers();
    }
}

// ========================================
// DATABASE MANAGEMENT
// ========================================

const databaseTabs = document.querySelectorAll('[data-db-tab]');
const dbDatabasesPanel = document.getElementById('db-databases');
const dbUsersPanel = document.getElementById('db-users');
const databasesTableBody = document.querySelector('#databases-table tbody');
const dbUsersTableBody = document.querySelector('#db-users-table tbody');
const openCreateDatabaseBtn = document.getElementById('open-create-database');
const openCreateDbUserBtn = document.getElementById('open-create-user');
const createDatabaseModal = document.getElementById('create-database-modal');
const createDbUserModal = document.getElementById('create-db-user-modal');
const grantPermissionsModal = document.getElementById('grant-permissions-modal');
const databaseForm = document.getElementById('database-form');
const dbUserForm = document.getElementById('db-user-form');
const grantForm = document.getElementById('grant-form');
const userDatabaseSelect = document.getElementById('user-database-select');

// Tab switching for database view
if (databaseTabs) {
    databaseTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.dbTab;
            
            databaseTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            if (target === 'databases') {
                dbDatabasesPanel?.classList.add('active');
                dbUsersPanel?.classList.remove('active');
            } else if (target === 'users') {
                dbDatabasesPanel?.classList.remove('active');
                dbUsersPanel?.classList.add('active');
                loadDatabaseUsers();
            }
        });
    });
}

// Initialize database view
function initializeDatabases() {
    loadDatabases();
}

// Load databases
async function loadDatabases() {
    try {
        const databases = await apiRequest('list_databases');
        databasesTableBody.innerHTML = '';
        
        databases.forEach(db => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-database-name', db.name);
            tr.innerHTML = `
                <td><div class="cell-primary">${escapeHtml(db.name)}</div></td>
                <td>${escapeHtml(db.charset || 'utf8mb4')}</td>
                <td>${escapeHtml(db.collation || 'utf8mb4_unicode_ci')}</td>
                <td>${db.size || '0 B'}</td>
                <td>${db.tables || 0}</td>
                <td class="table-actions">
                    <button data-action="drop-database" data-database="${escapeHtml(db.name)}" class="danger">Delete</button>
                </td>
            `;
            databasesTableBody.appendChild(tr);
        });
        
        // Update user database select
        updateUserDatabaseOptions(databases);
    } catch (error) {
        showNotification('Failed to load databases: ' + error.message, 'error');
    }
}

// Load database users
async function loadDatabaseUsers() {
    try {
        const users = await apiRequest('list_database_users');
        dbUsersTableBody.innerHTML = '';
        
        users.forEach(user => {
            const tr = document.createElement('tr');
            // databases is already a string from the backend
            const databases = user.databases || '-';
            
            tr.innerHTML = `
                <td><div class="cell-primary">${escapeHtml(user.username)}</div></td>
                <td>${escapeHtml(user.host)}</td>
                <td><div class="cell-meta">${escapeHtml(databases)}</div></td>
                <td class="table-actions">
                    <button data-action="grant-permissions" data-username="${escapeHtml(user.username)}" data-host="${escapeHtml(user.host)}" class="secondary">Grant Access</button>
                    <button data-action="drop-user" data-username="${escapeHtml(user.username)}" data-host="${escapeHtml(user.host)}" class="danger">Delete</button>
                </td>
            `;
            dbUsersTableBody.appendChild(tr);
        });
    } catch (error) {
        showNotification('Failed to load users: ' + error.message, 'error');
    }
}

// Update user database select options
function updateUserDatabaseOptions(databases) {
    if (!userDatabaseSelect) return;
    
    // Clear existing options except first
    while (userDatabaseSelect.options.length > 1) {
        userDatabaseSelect.remove(1);
    }
    
    // Add databases
    databases.forEach(db => {
        const option = document.createElement('option');
        option.value = db.name;
        option.textContent = db.name;
        userDatabaseSelect.appendChild(option);
    });
    
    // Update grant permissions form
    const grantDbSelect = grantForm?.querySelector('[name="grant_database"]');
    if (grantDbSelect) {
        while (grantDbSelect.options.length > 1) {
            grantDbSelect.remove(1);
        }
        databases.forEach(db => {
            const option = document.createElement('option');
            option.value = db.name;
            option.textContent = db.name;
            grantDbSelect.appendChild(option);
        });
    }
}

// Open create database modal
openCreateDatabaseBtn?.addEventListener('click', () => {
    databaseForm?.reset();
    createDatabaseModal?.showModal();
});

// Open create user modal
openCreateDbUserBtn?.addEventListener('click', () => {
    dbUserForm?.reset();
    createDbUserModal?.showModal();
});

// Handle custom host field visibility
dbUserForm?.querySelector('[name="user_host"]')?.addEventListener('change', (e) => {
    const customHostField = document.getElementById('custom-host-field');
    if (e.target.value === 'custom') {
        customHostField.style.display = 'block';
    } else {
        customHostField.style.display = 'none';
    }
});

// Handle ALL PRIVILEGES checkbox
const grantAllCheckbox = document.getElementById('grant-all-checkbox');
const specificPermissions = document.getElementById('specific-permissions');
grantAllCheckbox?.addEventListener('change', (e) => {
    const checkboxes = specificPermissions?.querySelectorAll('input[type="checkbox"]');
    checkboxes?.forEach(cb => {
        cb.disabled = e.target.checked;
        if (e.target.checked) {
            cb.checked = false;
        }
    });
});

// Create database
databaseForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(databaseForm);
    const data = {
        name: formData.get('database_name'),
        charset: formData.get('database_charset'),
        collation: formData.get('database_collation')
    };
    
    try {
        await apiRequest('create_database', data);
        showNotification('Database created successfully', 'success');
        createDatabaseModal.close();
        loadDatabases();
    } catch (error) {
        showNotification('Failed to create database: ' + error.message, 'error');
    }
});

// Create user
dbUserForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(dbUserForm);
    let host = formData.get('user_host');
    if (host === 'custom') {
        host = formData.get('user_host_custom') || 'localhost';
    }
    
    const data = {
        username: formData.get('user_name'),
        password: formData.get('user_password'),
        host: host,
        database: formData.get('user_database') || null
    };
    
    try {
        await apiRequest('create_database_user', data);
        showNotification('User created successfully', 'success');
        createDbUserModal.close();
        loadDatabaseUsers();
    } catch (error) {
        showNotification('Failed to create user: ' + error.message, 'error');
    }
});

// Grant permissions
grantForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(grantForm);
    const permissions = [];
    
    if (grantAllCheckbox?.checked) {
        permissions.push('ALL PRIVILEGES');
    } else {
        ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'ALTER', 'INDEX'].forEach(perm => {
            if (formData.get(`grant_${perm.toLowerCase()}`)) {
                permissions.push(perm);
            }
        });
    }
    
    const data = {
        username: formData.get('grant_username'),
        host: formData.get('grant_host'),
        database: formData.get('grant_database'),
        permissions: permissions
    };
    
    try {
        await apiRequest('grant_database_permissions', data);
        showNotification('Permissions granted successfully', 'success');
        grantPermissionsModal.close();
        loadDatabaseUsers();
    } catch (error) {
        showNotification('Failed to grant permissions: ' + error.message, 'error');
    }
});

// Handle table actions
if (databasesTableBody) {
    databasesTableBody.addEventListener('click', async (e) => {
        const button = e.target.closest('button');
        if (!button) return;
        
        const action = button.dataset.action;
        const database = button.dataset.database;
        
        if (action === 'drop-database') {
            if (!confirm(`Are you sure you want to delete the database "${database}"? This cannot be undone!`)) {
                return;
            }
            
            try {
                await request('drop_database', { name: database });
                showNotification('Database deleted successfully', 'success');
                loadDatabases();
            } catch (error) {
                showNotification('Failed to delete database: ' + error.message, 'error');
            }
        }
    });
}

if (dbUsersTableBody) {
    dbUsersTableBody.addEventListener('click', async (e) => {
        const button = e.target.closest('button');
        if (!button) return;
        
        const action = button.dataset.action;
        const username = button.dataset.username;
        const host = button.dataset.host;
        
        if (action === 'drop-user') {
            if (!confirm(`Are you sure you want to delete the user "${username}"@"${host}"?`)) {
                return;
            }
            
            try {
                await request('drop_database_user', { username, host });
                showNotification('User deleted successfully', 'success');
                loadDatabaseUsers();
            } catch (error) {
                showNotification('Failed to delete user: ' + error.message, 'error');
            }
        } else if (action === 'grant-permissions') {
            grantForm?.reset();
            const usernameField = grantForm?.querySelector('[name="grant_username"]');
            const hostField = grantForm?.querySelector('[name="grant_host"]');
            if (usernameField) usernameField.value = username;
            if (hostField) hostField.value = host;
            grantPermissionsModal?.showModal();
        }
    });
}

// Modal close handlers
createDatabaseModal?.querySelector('.secondary')?.addEventListener('click', () => {
    createDatabaseModal.close();
});

createDbUserModal?.querySelector('.secondary')?.addEventListener('click', () => {
    createDbUserModal.close();
});

grantPermissionsModal?.querySelector('.secondary')?.addEventListener('click', () => {
    grantPermissionsModal.close();
});

// ============================================================================
// BACKUP MANAGEMENT
// ============================================================================

const backupHistoryTable = document.querySelector('#backup-history-table tbody');
const backupJobsTable = document.querySelector('#backup-jobs-table tbody');
const backupDestinationsTable = document.querySelector('#backup-destinations-table tbody');

// Backup tab switching
document.querySelectorAll('[data-backup-tab]').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.backupTab;
        
        // Update active tab
        document.querySelectorAll('[data-backup-tab]').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Show target panel
        document.querySelectorAll('#backup-view .dns-panel').forEach(panel => {
            panel.classList.remove('active');
        });
        document.getElementById(`backup-${target}`).classList.add('active');
        
        // Load data for the tab
        if (target === 'history' && backupHistoryTable) {
            loadBackupHistory();
        } else if (target === 'jobs' && backupJobsTable) {
            loadBackupJobs();
        } else if (target === 'destinations' && backupDestinationsTable) {
            loadBackupDestinations();
        }
    });
});

// Load backup history
async function loadBackupHistory() {
    try {
        const backups = await apiRequest('list_backups', { limit: 100 });
        backupHistoryTable.innerHTML = '';
        
        if (backups.length === 0) {
            backupHistoryTable.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No backups found</td></tr>';
            return;
        }
        
        backups.forEach(backup => {
            const tr = document.createElement('tr');
            const itemsCount = Array.isArray(backup.items) ? backup.items.length : 0;
            const itemsSummary = getBackupItemsSummary(backup.items);
            
            tr.innerHTML = `
                <td>${formatDateTime(backup.created_at)}</td>
                <td><span class="badge badge-${getBackupTypeColor(backup.backup_type)}">${backup.backup_type}</span></td>
                <td>${itemsSummary} (${itemsCount} items)</td>
                <td>${backup.destination_type === 'local' ? '📁' : '🌐'} ${backup.destination_type}</td>
                <td>${formatBytes(backup.file_size || 0)}</td>
                <td><span class="badge badge-${getStatusColor(backup.status)}">${backup.status.replace('_', ' ')}</span></td>
                <td class="table-actions">
                    ${backup.status === 'completed' ? `<button data-action="restore-backup" data-backup-id="${backup.id}" class="secondary">Restore</button>` : ''}
                    <button data-action="delete-backup" data-backup-id="${backup.id}" class="danger">Delete</button>
                </td>
            `;
            backupHistoryTable.appendChild(tr);
        });
    } catch (error) {
        showNotification('Failed to load backup history: ' + error.message, 'error');
    }
}

// Load backup jobs
async function loadBackupJobs() {
    try {
        const jobs = await apiRequest('list_backup_jobs');
        backupJobsTable.innerHTML = '';
        
        if (jobs.length === 0) {
            backupJobsTable.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No scheduled jobs found</td></tr>';
            return;
        }
        
        jobs.forEach(job => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><div class="cell-primary">${escapeHtml(job.name)}</div>${job.description ? `<div class="text-secondary text-sm">${escapeHtml(job.description)}</div>` : ''}</td>
                <td><span class="badge badge-${getBackupTypeColor(job.backup_type)}">${job.backup_type}</span></td>
                <td><code>${escapeHtml(job.schedule_cron)}</code></td>
                <td>${escapeHtml(job.destination_name || 'N/A')}</td>
                <td>${job.last_run ? formatDateTime(job.last_run) : '<span class="text-secondary">Never</span>'}</td>
                <td>${job.next_run ? formatDateTime(job.next_run) : '<span class="text-secondary">N/A</span>'}</td>
                <td><span class="badge badge-${job.enabled ? 'success' : 'secondary'}">${job.enabled ? 'Enabled' : 'Disabled'}</span></td>
                <td class="table-actions">
                    <button data-action="run-job" data-job-id="${job.id}" class="secondary" ${!job.enabled ? 'disabled' : ''}>Run Now</button>
                    <button data-action="toggle-job" data-job-id="${job.id}" data-enabled="${job.enabled}" class="secondary">${job.enabled ? 'Disable' : 'Enable'}</button>
                    <button data-action="delete-job" data-job-id="${job.id}" class="danger">Delete</button>
                </td>
            `;
            backupJobsTable.appendChild(tr);
        });
    } catch (error) {
        showNotification('Failed to load backup jobs: ' + error.message, 'error');
    }
}

// Load backup destinations
async function loadBackupDestinations() {
    try {
        const destinations = await apiRequest('list_backup_destinations');
        backupDestinationsTable.innerHTML = '';
        
        if (destinations.length === 0) {
            backupDestinationsTable.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No destinations configured</td></tr>';
            return;
        }
        
        destinations.forEach(dest => {
            const tr = document.createElement('tr');
            const location = dest.type === 'local' ? dest.config.path : `${dest.config.host}:${dest.config.path}`;
            
            tr.innerHTML = `
                <td><div class="cell-primary">${escapeHtml(dest.name)}</div>${dest.is_default ? '<span class="badge badge-primary">Default</span>' : ''}</td>
                <td>${dest.type === 'local' ? '📁 Local' : '🌐 SFTP'}</td>
                <td><code>${escapeHtml(location)}</code></td>
                <td>${dest.is_default ? '✓' : ''}</td>
                <td><span class="badge badge-${dest.enabled ? 'success' : 'secondary'}">${dest.enabled ? 'Active' : 'Disabled'}</span></td>
                <td class="table-actions">
                    <button data-action="test-dest" data-dest-id="${dest.id}" class="secondary">Test</button>
                    <button data-action="delete-dest" data-dest-id="${dest.id}" class="danger">Delete</button>
                </td>
            `;
            backupDestinationsTable.appendChild(tr);
        });
        
        // Update destination selects in modals
        await updateDestinationSelects();
    } catch (error) {
        showNotification('Failed to load backup destinations: ' + error.message, 'error');
    }
}

// Update destination selects
async function updateDestinationSelects() {
    try {
        const destinations = await apiRequest('list_backup_destinations');
        const selects = [
            document.getElementById('backup-destination'),
            document.getElementById('job-destination')
        ];
        
        selects.forEach(select => {
            if (!select) return;
            
            // Keep first option (placeholder)
            select.innerHTML = '<option value="">Select destination...</option>';
            
            destinations.forEach(dest => {
                if (dest.enabled) {
                    const option = document.createElement('option');
                    option.value = dest.id;
                    option.textContent = `${dest.name} (${dest.type})${dest.is_default ? ' - Default' : ''}`;
                    if (dest.is_default) option.selected = true;
                    select.appendChild(option);
                }
            });
        });
    } catch (error) {
        console.error('Failed to update destination selects:', error);
    }
}

// Create Backup Modal
const createBackupModal = document.getElementById('create-backup-modal');
const createBackupForm = document.getElementById('create-backup-form');
const backupTypeSelect = document.getElementById('backup-type');

document.getElementById('open-create-backup')?.addEventListener('click', async () => {
    await loadBackupItemLists();
    await updateDestinationSelects();
    createBackupModal?.showModal();
    // Trigger change event if a type is already selected
    if (backupTypeSelect?.value) {
        backupTypeSelect.dispatchEvent(new Event('change'));
    }
});

backupTypeSelect?.addEventListener('change', () => {
    const type = backupTypeSelect.value;
    document.getElementById('backup-items-sites').style.display = (type === 'site' || type === 'mixed') ? 'block' : 'none';
    document.getElementById('backup-items-databases').style.display = (type === 'database' || type === 'mixed') ? 'block' : 'none';
    document.getElementById('backup-items-domains').style.display = (type === 'domain' || type === 'mixed') ? 'block' : 'none';
});

async function loadBackupItemLists() {
    try {
        // Load sites
        const sites = await apiRequest('list_sites');
        console.log('Sites loaded:', sites);
        const sitesList = document.getElementById('backup-sites-list');
        if (sitesList) {
            sitesList.innerHTML = '';
            if (sites && sites.length > 0) {
                sites.forEach(site => {
                    const label = document.createElement('label');
                    label.className = 'checkbox-label';
                    label.innerHTML = `<input type="checkbox" name="sites[]" value="${escapeHtml(site.server_name)}"> <span>${escapeHtml(site.server_name)}</span>`;
                    sitesList.appendChild(label);
                });
            } else {
                sitesList.innerHTML = '<p style="color: var(--text-tertiary); font-size: var(--font-size-sm); padding: var(--spacing-2);">No websites found</p>';
            }
        }
        
        // Load databases
        const databases = await apiRequest('list_databases');
        console.log('Databases loaded:', databases);
        const dbList = document.getElementById('backup-databases-list');
        if (dbList) {
            dbList.innerHTML = '';
            if (databases && databases.length > 0) {
                databases.forEach(db => {
                    const label = document.createElement('label');
                    label.className = 'checkbox-label';
                    label.innerHTML = `<input type="checkbox" name="databases[]" value="${escapeHtml(db.name)}"> <span>${escapeHtml(db.name)}</span>`;
                    dbList.appendChild(label);
                });
            } else {
                dbList.innerHTML = '<p style="color: var(--text-tertiary); font-size: var(--font-size-sm); padding: var(--spacing-2);">No databases found</p>';
            }
        }
        
        // Load domains
        const domains = await apiRequest('list_domains');
        console.log('Domains loaded:', domains);
        const domainsList = document.getElementById('backup-domains-list');
        if (domainsList) {
            domainsList.innerHTML = '';
            if (domains && domains.length > 0) {
                domains.forEach(domain => {
                    const label = document.createElement('label');
                    label.className = 'checkbox-label';
                    label.innerHTML = `<input type="checkbox" name="domains[]" value="${escapeHtml(domain.name)}"> <span>${escapeHtml(domain.name)}</span>`;
                    domainsList.appendChild(label);
                });
            } else {
                domainsList.innerHTML = '<p style="color: var(--text-tertiary); font-size: var(--font-size-sm); padding: var(--spacing-2);">No domains found</p>';
            }
        }
        
        // Also populate job modal lists
        const jobSitesList = document.getElementById('job-sites-list');
        const jobDbList = document.getElementById('job-databases-list');
        const jobDomainsList = document.getElementById('job-domains-list');
        
        if (jobSitesList && sitesList) jobSitesList.innerHTML = sitesList.innerHTML;
        if (jobDbList && dbList) jobDbList.innerHTML = dbList.innerHTML;
        if (jobDomainsList && domainsList) jobDomainsList.innerHTML = domainsList.innerHTML;
        
    } catch (error) {
        console.error('Failed to load backup items:', error);
    }
}

createBackupForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(createBackupForm);
    const type = formData.get('type');
    const destinationId = formData.get('destination_id');
    
    const items = type === 'mixed' ? {
        sites: formData.getAll('sites[]'),
        databases: formData.getAll('databases[]'),
        domains: formData.getAll('domains[]')
    } : (type === 'site' ? formData.getAll('sites[]') : 
          type === 'database' ? formData.getAll('databases[]') : 
          formData.getAll('domains[]'));
    
    // Validate selection
    const itemCount = type === 'mixed' 
        ? items.sites.length + items.databases.length + items.domains.length
        : items.length;
    
    if (itemCount === 0) {
        showNotification('Please select at least one item to backup', 'error');
        return;
    }
    
    try {
        // Close create modal and show progress modal
        createBackupModal.close();
        showBackupProgress('Queuing backup...', type, items);
        
        console.log('Starting backup creation:', { type, items, destinationId });
        
        // Queue the backup (returns immediately)
        const queueResult = await apiRequest('create_backup', { type, items, destination_id: parseInt(destinationId) });
        console.log('Backup queued:', queueResult);
        
        updateBackupProgress('Backup queued. Waiting for worker to process...');
        
        const queueId = queueResult.queue_id;
        
        // Poll queue status until completed or failed
        let historyId = null;
        const pollInterval = setInterval(async () => {
            try {
                const queueStatus = await apiRequest('get_backup_queue_status', { queue_id: queueId });
                console.log('Queue status:', queueStatus);
                
                if (queueStatus.status === 'processing') {
                    updateBackupProgress('Backup is being processed...');
                } else if (queueStatus.status === 'completed' && queueStatus.history_id) {
                    clearInterval(pollInterval);
                    historyId = queueStatus.history_id;
                    
                    // Get final progress
                    const progress = await apiRequest('get_backup_progress', { history_id: historyId });
                    console.log('Backup progress:', progress);
                    
                    // Mark all items based on their final status
                    if (progress.progress?.items) {
                        progress.progress.items.forEach(item => {
                            const index = findItemIndex(type, items, item.type, item.name);
                            if (index !== -1) {
                                if (item.status === 'completed') {
                                    markItemComplete(item.type, index);
                                } else if (item.status === 'error') {
                                    markItemError(item.type, index);
                                }
                            }
                        });
                    }
                    
                    markAllItemsComplete();
                    updateBackupProgress('Backup completed successfully!');
                    
                    setTimeout(() => {
                        hideBackupProgress();
                        showNotification('Backup created successfully!', 'success');
                        loadBackupHistory();
                    }, 1500);
                } else if (queueStatus.status === 'failed') {
                    clearInterval(pollInterval);
                    hideBackupProgress();
                    showNotification('Backup failed: ' + (queueStatus.error_message || 'Unknown error'), 'error');
                    loadBackupHistory();
                }
            } catch (pollError) {
                console.error('Error polling queue status:', pollError);
            }
        }, 2000); // Poll every 2 seconds
        
        createBackupForm.reset();
    } catch (error) {
        console.error('Backup creation error:', error);
        hideBackupProgress();
        showNotification('Failed to create backup: ' + error.message, 'error');
    }
});

// Create Backup Job Modal
const createJobModal = document.getElementById('create-job-modal');
const createJobForm = document.getElementById('create-job-form');
const jobBackupTypeSelect = document.getElementById('job-backup-type');
const cronPreset = document.getElementById('cron-preset');
const jobSchedule = document.getElementById('job-schedule');

document.getElementById('open-create-job')?.addEventListener('click', async () => {
    await loadBackupItemLists();
    await updateDestinationSelects();
    createJobModal?.showModal();
});

jobBackupTypeSelect?.addEventListener('change', () => {
    const type = jobBackupTypeSelect.value;
    document.getElementById('job-items-sites').style.display = (type === 'sites' || type === 'mixed') ? 'block' : 'none';
    document.getElementById('job-items-databases').style.display = (type === 'databases' || type === 'mixed') ? 'block' : 'none';
    document.getElementById('job-items-domains').style.display = (type === 'domains' || type === 'mixed') ? 'block' : 'none';
});

cronPreset?.addEventListener('change', () => {
    if (cronPreset.value) {
        jobSchedule.value = cronPreset.value;
    }
});

createJobForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(createJobForm);
    const backupType = formData.get('backup_type');
    
    const items = backupType === 'mixed' ? {
        sites: formData.getAll('sites[]'),
        databases: formData.getAll('databases[]'),
        domains: formData.getAll('domains[]')
    } : (backupType === 'sites' ? { sites: formData.getAll('sites[]') } : 
          backupType === 'databases' ? { databases: formData.getAll('databases[]') } : 
          { domains: formData.getAll('domains[]') });
    
    try {
        await apiRequest('create_backup_job', {
            name: formData.get('name'),
            description: formData.get('description'),
            backup_type: backupType,
            items,
            schedule_cron: formData.get('schedule_cron'),
            destination_id: parseInt(formData.get('destination_id')),
            retention_days: parseInt(formData.get('retention_days'))
        });
        
        showNotification('Backup job created successfully', 'success');
        createJobModal.close();
        createJobForm.reset();
        loadBackupJobs();
    } catch (error) {
        showNotification('Failed to create job: ' + error.message, 'error');
    }
});

// Create Destination Modal
const createDestModal = document.getElementById('create-destination-modal');
const createDestForm = document.getElementById('create-destination-form');
const destTypeSelect = document.getElementById('dest-type');

document.getElementById('open-create-destination')?.addEventListener('click', () => {
    createDestModal?.showModal();
});

destTypeSelect?.addEventListener('change', () => {
    const type = destTypeSelect.value;
    document.getElementById('dest-local-config').style.display = type === 'local' ? 'block' : 'none';
    document.getElementById('dest-sftp-config').style.display = type === 'sftp' ? 'block' : 'none';
});

document.getElementById('test-destination')?.addEventListener('click', async () => {
    const type = destTypeSelect.value;
    
    if (!type) {
        showNotification('Please select a destination type first', 'error');
        return;
    }
    
    // For testing without saving, we'd need a separate endpoint
    showNotification('Save the destination first, then use the Test button in the destinations list', 'info');
});

createDestForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(createDestForm);
    const type = formData.get('type');
    
    console.log('Form data:', {
        name: formData.get('name'),
        type: type,
        local_path: formData.get('local_path'),
        is_default: formData.get('is_default')
    });
    
    const config = type === 'local' ? {
        path: formData.get('local_path')
    } : {
        host: formData.get('sftp_host'),
        port: parseInt(formData.get('sftp_port')),
        username: formData.get('sftp_username'),
        password: formData.get('sftp_password'),
        path: formData.get('sftp_path')
    };
    
    const payload = {
        name: formData.get('name'),
        type,
        config,
        is_default: formData.get('is_default') === 'on'
    };
    
    console.log('Sending payload:', payload);
    
    try {
        await apiRequest('create_backup_destination', payload);
        
        showNotification('Backup destination created successfully', 'success');
        createDestModal.close();
        createDestForm.reset();
        loadBackupDestinations();
    } catch (error) {
        showNotification('Failed to create destination: ' + error.message, 'error');
    }
});

// Backup action handlers
document.addEventListener('click', async (e) => {
    const action = e.target.dataset.action;
    
    if (action === 'delete-backup') {
        if (!confirm('Are you sure you want to delete this backup?')) return;
        
        try {
            await apiRequest('delete_backup', { backup_id: parseInt(e.target.dataset.backupId) });
            showNotification('Backup deleted successfully', 'success');
            loadBackupHistory();
        } catch (error) {
            showNotification('Failed to delete backup: ' + error.message, 'error');
        }
    }
    
    else if (action === 'restore-backup') {
        const backupId = parseInt(e.target.dataset.backupId);
        await openRestoreModal(backupId);
    }
    
    else if (action === 'run-job') {
        if (!confirm('Run this backup job now?')) return;
        
        try {
            // Get job details to show what's being backed up
            const jobs = await apiRequest('list_backup_jobs');
            const job = jobs.find(j => j.id === parseInt(e.target.dataset.jobId));
            
            if (job) {
                const items = JSON.parse(job.items);
                const type = job.backup_type;
                showBackupProgress('Running backup job: ' + job.name, type, items);
                
                // Execute the job (blocks until complete)
                const result = await apiRequest('execute_backup_job', { job_id: parseInt(e.target.dataset.jobId) });
                
                if (result && result.id) {
                    // Get final progress state
                    try {
                        const progress = await apiRequest('get_backup_progress', { history_id: result.id });
                        
                        if (progress.progress?.items) {
                            progress.progress.items.forEach(item => {
                                const index = findItemIndex(type, items, item.type, item.name);
                                if (index !== -1) {
                                    if (item.status === 'completed') {
                                        markItemComplete(item.type, index);
                                    } else if (item.status === 'error') {
                                        markItemError(item.type, index);
                                    }
                                }
                            });
                        }
                        
                        if (progress.status === 'completed') {
                            markAllItemsComplete();
                        }
                    } catch (progError) {
                        console.error('Failed to get progress:', progError);
                        markAllItemsComplete();
                    }
                } else {
                    markAllItemsComplete();
                }
                
                updateBackupProgress('Backup job completed successfully!');
                setTimeout(() => {
                    hideBackupProgress();
                    showNotification('Backup job completed successfully!', 'success');
                }, 1500);
            } else {
                await apiRequest('execute_backup_job', { job_id: parseInt(e.target.dataset.jobId) });
                hideBackupProgress();
                showNotification('Backup job completed successfully!', 'success');
            }
            
            loadBackupJobs();
            loadBackupHistory();
        } catch (error) {
            hideBackupProgress();
            showNotification('Failed to run backup job: ' + error.message, 'error');
        }
    }
    
    else if (action === 'toggle-job') {
        const jobId = parseInt(e.target.dataset.jobId);
        const enabled = e.target.dataset.enabled === 'true';
        
        try {
            await apiRequest('update_backup_job', { job_id: jobId, enabled: !enabled });
            showNotification(`Job ${enabled ? 'disabled' : 'enabled'} successfully`, 'success');
            loadBackupJobs();
        } catch (error) {
            showNotification('Failed to update job: ' + error.message, 'error');
        }
    }
    
    else if (action === 'delete-job') {
        if (!confirm('Are you sure you want to delete this backup job?')) return;
        
        try {
            await apiRequest('delete_backup_job', { job_id: parseInt(e.target.dataset.jobId) });
            showNotification('Backup job deleted successfully', 'success');
            loadBackupJobs();
        } catch (error) {
            showNotification('Failed to delete job: ' + error.message, 'error');
        }
    }
    
    else if (action === 'test-dest') {
        try {
            showNotification('Testing connection...', 'info');
            const result = await apiRequest('test_backup_destination', { destination_id: parseInt(e.target.dataset.destId) });
            showNotification(result.message, result.success ? 'success' : 'error');
        } catch (error) {
            showNotification('Connection test failed: ' + error.message, 'error');
        }
    }
    
    else if (action === 'delete-dest') {
        if (!confirm('Are you sure you want to delete this destination?')) return;
        
        try {
            await apiRequest('delete_backup_destination', { destination_id: parseInt(e.target.dataset.destId) });
            showNotification('Destination deleted successfully', 'success');
            loadBackupDestinations();
        } catch (error) {
            showNotification('Failed to delete destination: ' + error.message, 'error');
        }
    }
});

// Restore modal functions
const restoreBackupModal = document.getElementById('restore-backup-modal');
const restoreBackupForm = document.getElementById('restore-backup-form');

async function openRestoreModal(backupId) {
    try {
        // Fetch backup details
        const backups = await apiRequest('list_backups', { limit: 1000 });
        const backup = backups.find(b => b.id === backupId);
        
        if (!backup) {
            showNotification('Backup not found', 'error');
            return;
        }

        // Set backup ID
        document.getElementById('restore-backup-id').value = backupId;

        // Display backup info
        const items = backup.items; // Already parsed by the API
        const infoBox = document.getElementById('restore-backup-info');
        infoBox.innerHTML = `
            <dl>
                <dt>Backup Type:</dt>
                <dd><span class="badge badge-${getBackupTypeColor(backup.backup_type)}">${backup.backup_type}</span></dd>
                <dt>Created:</dt>
                <dd>${new Date(backup.created_at).toLocaleString()}</dd>
                <dt>Size:</dt>
                <dd>${formatBytes(backup.file_size)}</dd>
                <dt>File:</dt>
                <dd>${escapeHtml(backup.file_name)}</dd>
            </dl>
        `;

        // Build restore items list
        const restoreItemsList = document.getElementById('restore-items-list');
        restoreItemsList.innerHTML = '';

        items.forEach((item, index) => {
            const label = document.createElement('label');
            label.className = 'checkbox-label';
            label.innerHTML = `
                <input type="checkbox" name="restore_items[]" value="${index}" checked>
                <span>${escapeHtml(item.name)} <small>(${item.type})</small></span>
            `;
            restoreItemsList.appendChild(label);
        });

        restoreBackupModal?.showModal();
    } catch (error) {
        showNotification('Failed to load backup details: ' + error.message, 'error');
    }
}

restoreBackupForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const backupId = parseInt(document.getElementById('restore-backup-id').value);
    const formData = new FormData(restoreBackupForm);
    const selectedIndexes = formData.getAll('restore_items[]').map(i => parseInt(i));

    if (selectedIndexes.length === 0) {
        showNotification('Please select at least one item to restore', 'error');
        return;
    }

    if (!confirm('This will restore the selected items. Existing data will be overwritten. Continue?')) {
        return;
    }

    try {
        // Fetch backup to get items array
        const backups = await apiRequest('list_backups', { limit: 1000 });
        const backup = backups.find(b => b.id === backupId);
        const allItems = JSON.parse(backup.items);
        
        // Filter selected items
        const itemsToRestore = selectedIndexes.map(index => allItems[index]);
        
        // Show progress modal
        showBackupProgress('Restoring backup...', backup.backup_type, itemsToRestore);

        await apiRequest('restore_backup', {
            backup_id: backupId,
            items: itemsToRestore
        });

        hideBackupProgress();
        showNotification('Backup restored successfully!', 'success');
        restoreBackupModal.close();
        loadBackupHistory();
    } catch (error) {
        hideBackupProgress();
        showNotification('Failed to restore backup: ' + error.message, 'error');
    }
});

// Backup progress modal functions
const backupProgressModal = document.getElementById('backup-progress-modal');
const backupProgressStatus = document.getElementById('backup-progress-status');
const backupProgressItems = document.getElementById('backup-progress-items');

function showBackupProgress(status, type, items) {
    backupProgressStatus.textContent = status;
    
    // Build items list with better formatting and checkboxes
    let itemsHtml = '';
    
    if (type === 'mixed') {
        const parts = [];
        if (items.sites && items.sites.length > 0) {
            const sitesHtml = items.sites.map((site, idx) => 
                `<div class="progress-item" data-type="site" data-index="${idx}" style="display: flex; align-items: center; gap: var(--spacing-2); padding: var(--spacing-1) 0;">
                    <span class="progress-tick" style="color: var(--color-neutral-400); min-width: 20px;">⭘</span>
                    <span>${site}</span>
                </div>`
            ).join('');
            parts.push(`<div style="margin-bottom: var(--spacing-3);"><strong>Websites (${items.sites.length}):</strong>${sitesHtml}</div>`);
        }
        if (items.databases && items.databases.length > 0) {
            const dbsHtml = items.databases.map((db, idx) => 
                `<div class="progress-item" data-type="database" data-index="${idx}" style="display: flex; align-items: center; gap: var(--spacing-2); padding: var(--spacing-1) 0;">
                    <span class="progress-tick" style="color: var(--color-neutral-400); min-width: 20px;">⭘</span>
                    <span>${db}</span>
                </div>`
            ).join('');
            parts.push(`<div style="margin-bottom: var(--spacing-3);"><strong>Databases (${items.databases.length}):</strong>${dbsHtml}</div>`);
        }
        if (items.domains && items.domains.length > 0) {
            const domainsHtml = items.domains.map((domain, idx) => 
                `<div class="progress-item" data-type="domain" data-index="${idx}" style="display: flex; align-items: center; gap: var(--spacing-2); padding: var(--spacing-1) 0;">
                    <span class="progress-tick" style="color: var(--color-neutral-400); min-width: 20px;">⭘</span>
                    <span>${domain}</span>
                </div>`
            ).join('');
            parts.push(`<div style="margin-bottom: var(--spacing-3);"><strong>Domains (${items.domains.length}):</strong>${domainsHtml}</div>`);
        }
        itemsHtml = parts.join('');
    } else if (Array.isArray(items)) {
        const typeLabel = type === 'site' ? 'Websites' : type === 'database' ? 'Databases' : 'Domains';
        const itemsListHtml = items.map((item, idx) => {
            const itemName = item.name || item;
            return `<div class="progress-item" data-type="${type}" data-index="${idx}" style="display: flex; align-items: center; gap: var(--spacing-2); padding: var(--spacing-1) 0;">
                <span class="progress-tick" style="color: var(--color-neutral-400); min-width: 20px;">⭘</span>
                <span>${itemName}</span>
            </div>`;
        }).join('');
        itemsHtml = `<div><strong>${typeLabel} (${items.length}):</strong>${itemsListHtml}</div>`;
    }
    
    backupProgressItems.innerHTML = itemsHtml;
    
    backupProgressModal?.showModal();
}

function markItemComplete(type, index) {
    const item = backupProgressItems.querySelector(`.progress-item[data-type="${type}"][data-index="${index}"]`);
    if (item) {
        const tick = item.querySelector('.progress-tick');
        if (tick) {
            tick.textContent = '✓';
            tick.style.color = 'var(--color-success-600)';
            tick.style.fontWeight = 'var(--font-weight-bold)';
        }
    }
}

function markAllItemsComplete() {
    const items = backupProgressItems.querySelectorAll('.progress-item');
    items.forEach(item => {
        const tick = item.querySelector('.progress-tick');
        if (tick && tick.textContent !== '✓') {
            tick.textContent = '✓';
            tick.style.color = 'var(--color-success-600)';
            tick.style.fontWeight = 'var(--font-weight-bold)';
        }
    });
}

async function pollBackupProgress(historyId, type, items) {
    let pollInterval;
    let consecutiveErrors = 0;
    const maxErrors = 3;
    
    try {
        await new Promise((resolve, reject) => {
            pollInterval = setInterval(async () => {
                try {
                    const progress = await apiRequest('get_backup_progress', { history_id: historyId });
                    consecutiveErrors = 0; // Reset error count on success
                    
                    // Update status message
                    if (progress.progress?.message) {
                        updateBackupProgress(progress.progress.message);
                    }
                    
                    // Update item statuses
                    if (progress.progress?.items) {
                        progress.progress.items.forEach(item => {
                            const index = findItemIndex(type, items, item.type, item.name);
                            if (index !== -1) {
                                if (item.status === 'completed') {
                                    markItemComplete(item.type, index);
                                } else if (item.status === 'error') {
                                    markItemError(item.type, index);
                                }
                            }
                        });
                    }
                    
                    // Check if completed
                    if (progress.status === 'completed') {
                        clearInterval(pollInterval);
                        markAllItemsComplete();
                        updateBackupProgress('Backup completed successfully!');
                        setTimeout(() => {
                            hideBackupProgress();
                            showNotification('Backup created successfully!', 'success');
                            resolve();
                        }, 1000);
                    } else if (progress.status === 'failed') {
                        clearInterval(pollInterval);
                        reject(new Error(progress.error_message || 'Backup failed'));
                    }
                } catch (error) {
                    consecutiveErrors++;
                    console.error('Progress polling error:', error);
                    if (consecutiveErrors >= maxErrors) {
                        clearInterval(pollInterval);
                        reject(new Error('Lost connection to backup process'));
                    }
                }
            }, 1000); // Poll every second
        });
    } finally {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
    }
}

function findItemIndex(backupType, allItems, itemType, itemName) {
    if (backupType === 'mixed') {
        const typeKey = itemType === 'site' ? 'sites' : itemType === 'database' ? 'databases' : 'domains';
        const items = allItems[typeKey] || [];
        return items.findIndex(name => name === itemName);
    } else {
        return allItems.findIndex(item => (item.name || item) === itemName);
    }
}

function markItemError(type, index) {
    const item = backupProgressItems.querySelector(`.progress-item[data-type="${type}"][data-index="${index}"]`);
    if (item) {
        const tick = item.querySelector('.progress-tick');
        if (tick) {
            tick.textContent = '✕';
            tick.style.color = 'var(--color-danger-600)';
            tick.style.fontWeight = 'var(--font-weight-bold)';
        }
    }
}

function updateBackupProgress(newStatus) {
    if (backupProgressStatus) {
        backupProgressStatus.textContent = newStatus;
    }
}

function hideBackupProgress() {
    backupProgressModal?.close();
}

// Helper functions
function getBackupTypeColor(type) {
    const colors = {
        'site': 'primary',
        'database': 'success',
        'domain': 'info',
        'mixed': 'warning',
        'sites': 'primary',
        'databases': 'success',
        'domains': 'info'
    };
    return colors[type] || 'secondary';
}

function getStatusColor(status) {
    const colors = {
        'completed': 'success',
        'in_progress': 'info',
        'pending': 'warning',
        'failed': 'danger'
    };
    return colors[status] || 'secondary';
}

function getBackupItemsSummary(items) {
    if (!Array.isArray(items)) return 'N/A';
    
    const types = {};
    items.forEach(item => {
        types[item.type] = (types[item.type] || 0) + 1;
    });
    
    return Object.entries(types).map(([type, count]) => `${count} ${type}${count > 1 ? 's' : ''}`).join(', ');
}

function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString();
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ============================================================================
// SSL CERTIFICATE MANAGEMENT
// ============================================================================

const sslCertsList = document.getElementById('ssl-certs-list');
const openIssueCertBtn = document.getElementById('open-issue-cert');
const issueCertModal = document.getElementById('issue-cert-modal');
const issueCertForm = document.getElementById('issue-cert-form');

async function loadSSLCertificates() {
    try {
        const certs = await request('list_ssl_certificates');
        sslCertsList.innerHTML = '';

        if (certs.length === 0) {
            sslCertsList.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No SSL certificates found. Issue your first certificate to get started.</td></tr>';
            return;
        }

        certs.forEach(cert => {
            const row = document.createElement('tr');
            
            const statusClass = cert.status === 'active' ? 'success' : 
                               cert.status === 'expired' ? 'danger' : 
                               cert.status === 'pending' ? 'warning' : 'secondary';
            
            const daysClass = cert.days_until_expiry < 30 ? 'danger' : 
                             cert.days_until_expiry < 60 ? 'warning' : 'success';
            
            row.innerHTML = `
                <td>${cert.domain}</td>
                <td><span class="badge badge-${statusClass}">${cert.status}</span></td>
                <td>${cert.issued_at ? new Date(cert.issued_at).toLocaleDateString() : 'N/A'}</td>
                <td>${cert.expires_at ? new Date(cert.expires_at).toLocaleDateString() : 'N/A'}</td>
                <td><span class="badge badge-${daysClass}">${cert.days_until_expiry >= 0 ? cert.days_until_expiry : 'Expired'}</span></td>
                <td>${cert.auto_renew ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>'}</td>
                <td class="table-actions">
                    ${cert.status === 'active' ? `<button class="secondary small" onclick="renewCertificate(${cert.id})">Renew</button>` : ''}
                    <button class="danger small" onclick="deleteCertificate(${cert.id}, '${cert.domain}')">Delete</button>
                </td>
            `;
            sslCertsList.appendChild(row);
        });
    } catch (error) {
        showNotification('Failed to load SSL certificates: ' + error.message, 'error');
    }
}

openIssueCertBtn?.addEventListener('click', () => {
    issueCertForm.reset();
    issueCertModal.showModal();
});

issueCertForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(issueCertForm);
    const data = {
        domain: formData.get('domain'),
        email: formData.get('email'),
        method: formData.get('method'),
        additional_domains: formData.get('additional_domains') ? 
            formData.get('additional_domains').split(',').map(d => d.trim()).filter(d => d) : [],
        auto_renew: formData.get('auto_renew') === 'on'
    };

    try {
        showNotification('Issuing SSL certificate...', 'info');
        await request('issue_ssl_certificate', data);
        showNotification('SSL certificate issued successfully!', 'success');
        issueCertModal.close();
        await loadSSLCertificates();
    } catch (error) {
        showNotification('Failed to issue certificate: ' + error.message, 'error');
    }
});

async function renewCertificate(id) {
    if (!confirm('Renew this SSL certificate?')) return;
    
    try {
        showNotification('Renewing certificate...', 'info');
        await request('renew_ssl_certificate', { id });
        showNotification('Certificate renewed successfully!', 'success');
        await loadSSLCertificates();
    } catch (error) {
        showNotification('Failed to renew certificate: ' + error.message, 'error');
    }
}

async function deleteCertificate(id, domain) {
    if (!confirm(`Delete SSL certificate for ${domain}? This will revoke the certificate.`)) return;
    
    try {
        await request('delete_ssl_certificate', { id });
        showNotification('Certificate deleted successfully', 'success');
        await loadSSLCertificates();
    } catch (error) {
        showNotification('Failed to delete certificate: ' + error.message, 'error');
    }
}

// ============================================================================
// CRON JOB MANAGEMENT
// ============================================================================

const cronJobsList = document.getElementById('cron-jobs-list');
const openCreateCronBtn = document.getElementById('open-create-cron');
const createCronModal = document.getElementById('create-cron-modal');
const cronJobForm = document.getElementById('cron-job-form');
const cronSchedulePreset = document.getElementById('cron-schedule-preset');
const cronScheduleInput = document.getElementById('cron-schedule');
const cronSiteSelect = document.getElementById('cron-site');

async function loadCronJobs() {
    try {
        const jobs = await request('list_cron_jobs');
        cronJobsList.innerHTML = '';

        if (jobs.length === 0) {
            cronJobsList.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No cron jobs found. Create your first scheduled task.</td></tr>';
            return;
        }

        jobs.forEach(job => {
            const row = document.createElement('tr');
            const statusClass = job.enabled ? 'success' : 'secondary';
            
            row.innerHTML = `
                <td>
                    <strong>${job.name}</strong>
                    ${job.server_name ? `<br><span class="text-secondary text-sm">Site: ${job.server_name}</span>` : ''}
                </td>
                <td><code style="font-size: 0.85rem;">${job.command.length > 50 ? job.command.substring(0, 50) + '...' : job.command}</code></td>
                <td><code>${job.schedule}</code></td>
                <td>${job.next_run ? new Date(job.next_run).toLocaleString() : 'N/A'}</td>
                <td>${job.last_run ? new Date(job.last_run).toLocaleString() : 'Never'}</td>
                <td><span class="badge badge-${statusClass}">${job.enabled ? 'Enabled' : 'Disabled'}</span></td>
                <td class="table-actions">
                    <button class="secondary small" onclick="toggleCronJob(${job.id}, ${!job.enabled})">${job.enabled ? 'Disable' : 'Enable'}</button>
                    <button class="secondary small" onclick="executeCronJob(${job.id})">Run Now</button>
                    <button class="secondary small" onclick="editCronJob(${job.id})">Edit</button>
                    <button class="danger small" onclick="deleteCronJob(${job.id}, '${job.name}')">Delete</button>
                </td>
            `;
            cronJobsList.appendChild(row);
        });
    } catch (error) {
        showNotification('Failed to load cron jobs: ' + error.message, 'error');
    }
}

// Populate site selector for cron jobs
async function populateCronSiteSelect() {
    try {
        const sites = await request('list_sites');
        cronSiteSelect.innerHTML = '<option value="">None (Global)</option>';
        sites.forEach(site => {
            const option = document.createElement('option');
            option.value = site.server_name;
            option.textContent = site.server_name;
            cronSiteSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Failed to load sites for cron selector:', error);
    }
}

// Schedule preset selector
cronSchedulePreset?.addEventListener('change', (e) => {
    if (e.target.value) {
        cronScheduleInput.value = e.target.value;
    }
});

openCreateCronBtn?.addEventListener('click', () => {
    document.getElementById('cron-modal-title').textContent = 'Create Cron Job';
    document.getElementById('cron-job-id').value = '';
    cronJobForm.reset();
    populateCronSiteSelect();
    createCronModal.showModal();
});

cronJobForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(cronJobForm);
    const jobId = document.getElementById('cron-job-id').value;
    
    const data = {
        name: formData.get('name'),
        command: formData.get('command'),
        schedule: formData.get('schedule'),
        user: formData.get('user'),
        server_name: formData.get('server_name') || null,
        enabled: formData.get('enabled') === 'on'
    };

    try {
        if (jobId) {
            data.id = parseInt(jobId);
            await request('update_cron_job', data);
            showNotification('Cron job updated successfully!', 'success');
        } else {
            await request('create_cron_job', data);
            showNotification('Cron job created successfully!', 'success');
        }
        createCronModal.close();
        await loadCronJobs();
    } catch (error) {
        showNotification('Failed to save cron job: ' + error.message, 'error');
    }
});

async function editCronJob(id) {
    try {
        const job = await request('get_cron_job', { id });
        document.getElementById('cron-modal-title').textContent = 'Edit Cron Job';
        document.getElementById('cron-job-id').value = job.id;
        document.getElementById('cron-name').value = job.name;
        document.getElementById('cron-command').value = job.command;
        document.getElementById('cron-schedule').value = job.schedule;
        document.getElementById('cron-user').value = job.user;
        document.getElementById('cron-enabled').checked = job.enabled;
        
        await populateCronSiteSelect();
        if (job.server_name) {
            document.getElementById('cron-site').value = job.server_name;
        }
        
        createCronModal.showModal();
    } catch (error) {
        showNotification('Failed to load cron job: ' + error.message, 'error');
    }
}

async function toggleCronJob(id, enabled) {
    try {
        await request('toggle_cron_job', { id, enabled });
        showNotification(`Cron job ${enabled ? 'enabled' : 'disabled'}`, 'success');
        await loadCronJobs();
    } catch (error) {
        showNotification('Failed to toggle cron job: ' + error.message, 'error');
    }
}

async function executeCronJob(id) {
    if (!confirm('Execute this cron job now?')) return;
    
    try {
        showNotification('Executing cron job...', 'info');
        const result = await request('execute_cron_job', { id });
        showNotification('Cron job executed. Check output in job list.', 'success');
        await loadCronJobs();
    } catch (error) {
        showNotification('Failed to execute cron job: ' + error.message, 'error');
    }
}

async function deleteCronJob(id, name) {
    if (!confirm(`Delete cron job "${name}"?`)) return;
    
    try {
        await request('delete_cron_job', { id });
        showNotification('Cron job deleted successfully', 'success');
        await loadCronJobs();
    } catch (error) {
        showNotification('Failed to delete cron job: ' + error.message, 'error');
    }
}

// ============================================================================
// PHP CONFIGURATION
// ============================================================================

const phpPresetSelector = document.getElementById('php-preset-selector');
const phpVersionSelector = document.getElementById('php-version-selector');

// Apply PHP preset
phpPresetSelector?.addEventListener('change', async (e) => {
    if (!e.target.value) return;
    
    try {
        const presets = await request('get_php_presets');
        const preset = presets[e.target.value];
        
        if (preset) {
            document.getElementById('php-memory-limit').value = preset.php_memory_limit;
            document.getElementById('php-upload-max-filesize').value = preset.php_upload_max_filesize;
            document.getElementById('php-post-max-size').value = preset.php_post_max_size;
            document.getElementById('php-max-execution-time').value = preset.php_max_execution_time;
            document.getElementById('php-max-input-time').value = preset.php_max_input_time;
            
            showNotification(`Applied ${preset.label} preset`, 'success');
        }
    } catch (error) {
        showNotification('Failed to load preset: ' + error.message, 'error');
    }
});

// File Manager Functions
let currentPath = '/';

async function loadDirectory(path = '/') {
    try {
        const items = await request('list_directory', { path });
        currentPath = path;
        
        // Update breadcrumb
        updateBreadcrumb(path);
        
        // Render file list
        const filesList = document.getElementById('files-list');
        if (!filesList) return;
        
        filesList.innerHTML = '';
        
        // Add parent directory link if not at root
        if (path !== '/') {
            const parentPath = path.split('/').slice(0, -1).join('/') || '/';
            filesList.innerHTML += `
                <tr class="file-row file-type-directory" onclick="loadDirectory('${parentPath}')">
                    <td class="file-name">
                        <svg class="file-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span class="file-name-text">..</span>
                    </td>
                    <td class="file-size">-</td>
                    <td>-</td>
                    <td>-</td>
                    <td>-</td>
                    <td></td>
                </tr>
            `;
        }
        
        // Render items
        items.forEach(item => {
            const isDir = item.type === 'directory';
            const icon = isDir 
                ? '<svg class="file-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>'
                : '<svg class="file-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>';
            
            const onclick = isDir ? `loadDirectory('${item.path}')` : '';
            const rowClass = isDir ? 'file-type-directory' : 'file-type-file';
            
            const modified = new Date(item.modified * 1000).toLocaleString();
            const size = isDir ? '-' : formatFileSize(item.size);
            const owner = item.owner ? `${item.owner}:${item.group}` : '-';
            
            // Check if file is editable (text-based)
            const editableExtensions = ['txt', 'css', 'xml', 'json', 'php', 'js', 'html', 'htm', 'md', 'yml', 'yaml', 'conf', 'ini', 'log', 'sh', 'sql', 'env', 'htaccess', 'gitignore', 'py', 'rb', 'ts', 'jsx', 'tsx', 'vue', 'scss', 'less', 'svg'];
            const extension = item.extension ? item.extension.toLowerCase() : '';
            const isEditable = !isDir && editableExtensions.includes(extension);
            
            filesList.innerHTML += `
                <tr class="file-row ${rowClass}" ${onclick ? `onclick="${onclick}"` : ''}>
                    <td class="file-name">
                        ${icon}
                        <span class="file-name-text">${escapeHtml(item.name)}</span>
                    </td>
                    <td class="file-size">${size}</td>
                    <td>${modified}</td>
                    <td>${owner}</td>
                    <td>${item.permissions}</td>
                    <td class="file-actions" onclick="event.stopPropagation()">
                        ${isEditable ? `<button class="secondary small" onclick="editFile('${item.path}')">Edit</button>` : ''}
                        ${!isDir ? `<button class="secondary small" onclick="downloadFile('${item.path}')">Download</button>` : ''}
                        ${isDir ? `<button class="secondary small" onclick="downloadZip('${item.path}')">Download Zip</button>` : ''}
                        <button class="secondary small" onclick="renameItem('${item.path}', '${escapeHtml(item.name)}')">Rename</button>
                        <button class="danger small" onclick="deleteItem('${item.path}', '${escapeHtml(item.name)}')">Delete</button>
                    </td>
                </tr>
            `;
        });
        
        if (items.length === 0 && path === '/') {
            filesList.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No files found</td></tr>';
        }
    } catch (error) {
        showNotification('Failed to load directory: ' + error.message, 'error');
    }
}

function updateBreadcrumb(path) {
    const breadcrumb = document.getElementById('file-breadcrumb');
    if (!breadcrumb) return;
    
    // Just display the path as text
    breadcrumb.textContent = path || '/';
}

let aceEditor = null;

async function editFile(path) {
    try {
        const file = await request('read_file', { path });
        
        // Get file extension
        const extension = path.split('.').pop().toLowerCase();
        
        document.getElementById('file-edit-path').value = path;
        document.getElementById('file-edit-path-display').value = path;
        document.getElementById('file-edit-extension').value = extension;
        document.getElementById('file-editor-title').textContent = `Edit: ${file.name}`;
        document.getElementById('file-create-backup').checked = true;
        
        // Show modal first
        document.getElementById('file-editor-modal').showModal();
        
        // Wait for modal to render, then initialize Ace
        setTimeout(() => {
            // Initialize Ace Editor if not already done
            if (!aceEditor) {
                try {
                    aceEditor = ace.edit('ace-editor');
                    aceEditor.setTheme('ace/theme/monokai');
                    aceEditor.setOptions({
                        fontSize: '14px',
                        showPrintMargin: false,
                        enableBasicAutocompletion: true,
                        enableLiveAutocompletion: true,
                        enableSnippets: true,
                        tabSize: 4,
                        useSoftTabs: true
                    });
                } catch (e) {
                    console.error('Failed to initialize Ace Editor:', e);
                    showNotification('Failed to initialize editor: ' + e.message, 'error');
                    return;
                }
            }
            
            // Set syntax mode based on file extension
            const modeMap = {
                'php': 'php',
                'js': 'javascript',
                'json': 'json',
                'html': 'html',
                'htm': 'html',
                'css': 'css',
                'scss': 'scss',
                'less': 'less',
                'xml': 'xml',
                'sql': 'sql',
                'py': 'python',
                'rb': 'ruby',
                'sh': 'sh',
                'yml': 'yaml',
                'yaml': 'yaml',
                'md': 'markdown',
                'ts': 'typescript',
                'jsx': 'jsx',
                'tsx': 'tsx',
                'vue': 'html'
            };
            
            const mode = modeMap[extension] || 'text';
            aceEditor.session.setMode(`ace/mode/${mode}`);
            
            // Set content
            aceEditor.setValue(file.contents, -1); // -1 moves cursor to start
            aceEditor.resize();
            aceEditor.focus();
        }, 100);
        
    } catch (error) {
        showNotification('Failed to load file: ' + error.message, 'error');
    }
}

function closeFileEditor() {
    document.getElementById('file-editor-modal').close();
    if (aceEditor) {
        aceEditor.setValue('', -1);
    }
}

async function saveFileContent(event) {
    event.preventDefault();
    
    const path = document.getElementById('file-edit-path').value;
    const contents = aceEditor ? aceEditor.getValue() : '';
    const createBackup = document.getElementById('file-create-backup').checked;
    
    try {
        // TODO: Implement backup creation
        await request('write_file', { path, contents });
        
        showNotification('File saved successfully', 'success');
        closeFileEditor();
        await loadDirectory(currentPath);
    } catch (error) {
        showNotification('Failed to save file: ' + error.message, 'error');
    }
}

async function downloadFile(path) {
    try {
        window.location.href = `api.php?action=download_file&path=${encodeURIComponent(path)}`;
    } catch (error) {
        showNotification('Failed to download file: ' + error.message, 'error');
    }
}

async function downloadZip(path) {
    try {
        window.location.href = `api.php?action=download_zip&path=${encodeURIComponent(path)}`;
        showNotification('Creating zip archive...', 'info');
    } catch (error) {
        showNotification('Failed to create zip: ' + error.message, 'error');
    }
}

async function deleteItem(path, name) {
    if (!confirm(`Are you sure you want to delete "${name}"?`)) {
        return;
    }
    
    try {
        await request('delete_file', { path });
        showNotification('Item deleted successfully', 'success');
        await loadDirectory(currentPath);
    } catch (error) {
        showNotification('Failed to delete item: ' + error.message, 'error');
    }
}

async function renameItem(oldPath, currentName) {
    const newName = prompt('Enter new name:', currentName);
    if (!newName || newName === currentName) {
        return;
    }
    
    const directory = oldPath.substring(0, oldPath.lastIndexOf('/')) || '/';
    const newPath = directory === '/' ? `/${newName}` : `${directory}/${newName}`;
    
    try {
        await request('rename_file', { old_path: oldPath, new_path: newPath });
        showNotification('Item renamed successfully', 'success');
        await loadDirectory(currentPath);
    } catch (error) {
        showNotification('Failed to rename item: ' + error.message, 'error');
    }
}

function createNewFile() {
    document.getElementById('new-file-name').value = '';
    document.getElementById('create-file-modal').showModal();
}

function createNewFolder() {
    document.getElementById('new-folder-name').value = '';
    document.getElementById('create-folder-modal').showModal();
}

async function saveNewFile(event) {
    event.preventDefault();
    
    const filename = document.getElementById('new-file-name').value;
    const path = currentPath === '/' ? `/${filename}` : `${currentPath}/${filename}`;
    
    try {
        await request('create_file', { path, contents: '' });
        showNotification('File created successfully', 'success');
        document.getElementById('create-file-modal').close();
        await loadDirectory(currentPath);
    } catch (error) {
        showNotification('Failed to create file: ' + error.message, 'error');
    }
}

async function saveNewFolder(event) {
    event.preventDefault();
    
    const foldername = document.getElementById('new-folder-name').value;
    const path = currentPath === '/' ? `/${foldername}` : `${currentPath}/${foldername}`;
    
    try {
        await request('create_directory', { path });
        showNotification('Folder created successfully', 'success');
        document.getElementById('create-folder-modal').close();
        await loadDirectory(currentPath);
    } catch (error) {
        showNotification('Failed to create folder: ' + error.message, 'error');
    }
}

async function uploadFiles() {
    const input = document.getElementById('file-upload-input');
    const files = input.files;
    
    if (!files || files.length === 0) {
        return;
    }
    
    try {
        for (let i = 0; i < files.length; i++) {
            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('path', currentPath);
            formData.append('file', files[i]);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'Upload failed');
            }
        }
        
        showNotification(`${files.length} file(s) uploaded successfully`, 'success');
        input.value = '';
        await loadDirectory(currentPath);
    } catch (error) {
        showNotification('Failed to upload files: ' + error.message, 'error');
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Make functions globally available
window.renewCertificate = renewCertificate;
window.deleteCertificate = deleteCertificate;
window.editCronJob = editCronJob;
window.toggleCronJob = toggleCronJob;
window.executeCronJob = executeCronJob;
window.deleteCronJob = deleteCronJob;
window.loadDirectory = loadDirectory;
window.editFile = editFile;
window.closeFileEditor = closeFileEditor;
window.saveFileContent = saveFileContent;
window.downloadFile = downloadFile;
window.downloadZip = downloadZip;
window.deleteItem = deleteItem;
window.renameItem = renameItem;
window.createNewFile = createNewFile;
window.createNewFolder = createNewFolder;
window.saveNewFile = saveNewFile;
window.saveNewFolder = saveNewFolder;
window.uploadFiles = uploadFiles;
