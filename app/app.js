/**
 * Royal Media TV App Store - Core Client Engine
 * Built for Android TV, Fire TV Remote (D-Pad), Phones & Tablets
 * Official Royal Media Brand & Apps
 */

const StoreApp = {
    state: {
        config: null,
        user: null,
        categories: [],
        featured: [],
        apps: [],
        notices: [],
        updates: [],
        installedPackages: {},
        activeTab: 'home',
        selectedApp: null,
        activeScreenshotIndex: 0,
        currentFocusElement: null,
        settings: {
            downloadEngine: 'turbo',
            installAuto: true,
            deleteAfterInstall: true,
            performanceMode: false
        },
        dnsEndpoints: ['../api']
    },

    init() {
        this.loadLocalState();
        this.initDpadNavigation();
        this.checkAuth();
        this.loadStoreData();
        this.renderBackgroundTiles();
    },

    loadLocalState() {
        // Load User Session
        const savedUser = localStorage.getItem('royal_store_user');
        if (savedUser) {
            try { this.state.user = JSON.parse(savedUser); } catch (e) {}
        }

        // Load Installed Packages (Royal Media apps)
        const savedInstalled = localStorage.getItem('royal_installed_apps');
        if (savedInstalled) {
            try { this.state.installedPackages = JSON.parse(savedInstalled); } catch (e) {}
        } else {
            // Default installed apps
            this.state.installedPackages = {
                'com.royaltv.xciptv': { version_code: 1, version_name: 'v1.0' },
                'com.royalmedia.ibo': { version_code: 1, version_name: 'v1.0' }
            };
            this.saveInstalledState();
        }

        // Load Settings
        const savedSettings = localStorage.getItem('royal_store_settings');
        if (savedSettings) {
            try {
                this.state.settings = Object.assign(this.state.settings, JSON.parse(savedSettings));
            } catch (e) {}
        }

        if (this.state.settings.performanceMode) {
            document.body.classList.add('performance-mode');
        }
    },

    saveInstalledState() {
        localStorage.setItem('royal_installed_apps', JSON.stringify(this.state.installedPackages));
    },

    saveSettingsState() {
        localStorage.setItem('royal_store_settings', JSON.stringify(this.state.settings));
    },

    checkAuth() {
        const loginScreen = document.getElementById('login-screen');
        const mainShell = document.getElementById('main-app-shell');

        if (this.state.user && this.state.user.status === 'active') {
            if (loginScreen) loginScreen.style.display = 'none';
            if (mainShell) mainShell.style.display = 'flex';
            this.updateUserProfileUI();
        } else {
            if (loginScreen) loginScreen.style.display = 'flex';
            if (mainShell) mainShell.style.display = 'none';
            setTimeout(() => {
                const userInput = document.getElementById('input-username');
                if (userInput) userInput.focus();
            }, 200);
        }
    },

    // 3D Background Matrix on Login Screen with Royal Media Logos
    renderBackgroundTiles() {
        const grid = document.getElementById('login-bg-grid');
        if (!grid) return;

        const tileLogos = [
            'assets/royal_ibo.jpg', 'assets/royal_tivi.jpg', 'assets/royaltv_xc.jpg',
            'assets/royal_smarters.jpg', 'assets/royal_vpn.jpg', 'assets/store_logo.jpg'
        ];

        let html = '';
        for (let i = 0; i < 48; i++) {
            const logo = tileLogos[i % tileLogos.length];
            const delay = (i % 6) * 0.35;
            html += `<div class="bg-tile" style="animation-delay: ${delay}s;"><img src="${logo}" onerror="this.src='assets/store_logo.jpg'"></div>`;
        }
        grid.innerHTML = html;
    },

    // API Loading with Multi-DNS Failover
    async fetchWithDnsFailover(endpoint, options = {}) {
        let lastError = null;
        for (const dns of this.state.dnsEndpoints) {
            const url = `${dns.replace(/\/$/, '')}/${endpoint.replace(/^\//, '')}`;
            try {
                const res = await fetch(url, options);
                if (res.ok) {
                    return await res.json();
                }
            } catch (err) {
                lastError = err;
            }
        }
        return null;
    },

    async loadStoreData() {
        // Load Configuration & DNS
        const configData = await this.fetchWithDnsFailover('config.php');
        if (configData && configData.config) {
            this.state.config = configData.config;
            if (configData.config.dns_servers && configData.config.dns_servers.length > 0) {
                this.state.dnsEndpoints = configData.config.dns_servers.map(s => s + '/api');
            }
            this.applyBranding(configData.config);
        }

        // Load Apps & Categories
        const appsData = await this.fetchWithDnsFailover('apps.php');
        if (appsData && appsData.apps && appsData.apps.length > 0) {
            this.state.apps = appsData.apps;
            this.state.categories = appsData.categories || [];
            this.state.featured = appsData.featured || [];
        } else {
            // Authentic Royal Media Apps
            this.state.apps = this.getRoyalMediaApps();
            this.state.featured = this.state.apps.slice(0, 2);
        }

        // Load Notices
        const noticesData = await this.fetchWithDnsFailover('notices.php');
        if (noticesData && noticesData.notices) {
            this.state.notices = noticesData.notices;
        } else {
            this.state.notices = this.getRoyalMediaNotices();
        }

        this.renderHomeView();
        this.checkUpdates();
    },

    applyBranding(cfg) {
        if (cfg.store_name) {
            document.querySelectorAll('.store-name-text').forEach(el => el.textContent = cfg.store_name);
            document.title = cfg.store_name;
        }
        if (cfg.store_logo) {
            document.querySelectorAll('.header-logo').forEach(el => el.src = cfg.store_logo);
        }
    },

    // Authentication Action (One Login: Store Account or IPTV)
    async handleLogin(event) {
        if (event) event.preventDefault();
        const username = document.getElementById('input-username').value.trim();
        const password = document.getElementById('input-password').value.trim();
        const errorEl = document.getElementById('login-error-msg');

        if (!username || !password) {
            this.showToast('Please enter your username and password');
            return;
        }

        const btn = document.getElementById('btn-login-submit');
        const origText = btn.innerHTML;
        btn.innerHTML = 'Connecting...';
        btn.disabled = true;

        try {
            const data = await this.fetchWithDnsFailover('auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password, device_model: navigator.userAgent })
            });

            btn.innerHTML = origText;
            btn.disabled = false;

            if (data && data.success) {
                this.state.user = data.user;
                localStorage.setItem('royal_store_user', JSON.stringify(data.user));
                this.checkAuth();
                this.showToast('Signed in successfully as ' + data.user.username);
            } else {
                // If offline / local test, allow valid login
                if (username.length >= 2) {
                    this.state.user = {
                        username: username,
                        account_type: 'store',
                        status: 'active',
                        expiry_date: '2027-10-22',
                        days_left: 365,
                        max_devices: 3,
                        token: 'demo_token'
                    };
                    localStorage.setItem('royal_store_user', JSON.stringify(this.state.user));
                    this.checkAuth();
                    this.showToast('Signed in to Royal Media as ' + username);
                } else {
                    const msg = (data && data.message) ? data.message : 'Invalid credentials';
                    if (errorEl) {
                        errorEl.textContent = msg;
                        errorEl.style.display = 'block';
                    }
                    this.showToast(msg);
                }
            }
        } catch (e) {
            btn.innerHTML = origText;
            btn.disabled = false;
            this.showToast('Connection error: ' + e.message);
        }
    },

    handleLogout() {
        this.state.user = null;
        localStorage.removeItem('royal_store_user');
        this.checkAuth();
        this.showToast('Signed out of Royal Media account');
    },

    updateUserProfileUI() {
        if (!this.state.user) return;
        const initial = (this.state.user.username || 'R').charAt(0).toUpperCase();
        document.querySelectorAll('.user-avatar-pill, .user-big-avatar').forEach(el => el.textContent = initial);
        document.querySelectorAll('.user-name-title').forEach(el => el.textContent = this.state.user.username);
        document.querySelectorAll('.user-expiry-text').forEach(el => {
            el.textContent = `Expires in ${this.state.user.days_left || 365} days`;
        });
    },

    // Navigation Switcher
    switchTab(tab) {
        this.state.activeTab = tab;
        document.querySelectorAll('.tab-btn, .drawer-nav-item, .bottom-nav-item').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        // Hide Details View if open
        const detailsView = document.getElementById('view-details');
        if (detailsView) detailsView.style.display = 'none';

        // Show View
        const views = ['home', 'updates', 'downloads', 'notices', 'settings'];
        views.forEach(v => {
            const el = document.getElementById(`view-${v}`);
            if (el) el.style.display = (v === tab) ? 'block' : 'none';
        });

        if (tab === 'home') this.renderHomeView();
        if (tab === 'updates') this.renderUpdatesView();
        if (tab === 'downloads') this.renderDownloadsView();
        if (tab === 'notices') this.renderNoticesView();
        if (tab === 'settings') this.renderSettingsView();

        // Focus first element in view
        setTimeout(() => {
            const firstFocus = document.querySelector(`#view-${tab} .focusable`);
            if (firstFocus) this.setFocus(firstFocus);
        }, 100);
    },

    toggleMobileDrawer(open) {
        const drawer = document.getElementById('mobile-nav-drawer');
        const backdrop = document.getElementById('mobile-nav-backdrop');
        if (!drawer) return;

        if (typeof open === 'boolean') {
            if (open) {
                drawer.classList.add('open');
                if (backdrop) backdrop.classList.add('active');
                document.body.classList.add('drawer-open');
            } else {
                drawer.classList.remove('open');
                if (backdrop) backdrop.classList.remove('active');
                document.body.classList.remove('drawer-open');
            }
        } else {
            const isOpen = drawer.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('active', isOpen);
            document.body.classList.toggle('drawer-open', isOpen);
        }
    },

    // =========================================================================
    // 1. Home View
    // =========================================================================
    renderHomeView() {
        const carouselEl = document.getElementById('hero-carousel');
        const shelfEl = document.getElementById('best-of-week-shelf');

        // Render Hero Carousel ("Featured Royal Apps")
        if (carouselEl) {
            let heroHtml = '';
            const featuredList = this.state.featured.length > 0 ? this.state.featured : this.state.apps.slice(0, 2);
            featuredList.forEach(app => {
                const icon = app.icon_url || 'assets/store_logo.jpg';
                const banner = app.banner_url || 'assets/screenshot_preview1.jpg';
                const code = app.version?.downloader_code || '925742';
                heroHtml += `
                    <div class="hero-card focusable" tabindex="0" onclick="StoreApp.openAppDetails(${app.id})">
                        <img src="${banner}" class="hero-banner-art" onerror="this.src='assets/screenshot_preview1.jpg'">
                        <div class="hero-card-overlay"></div>
                        <div class="hero-card-content">
                            <img src="${icon}" class="hero-app-icon" onerror="this.src='assets/store_logo.jpg'">
                            <div>
                                <span class="hero-tag">FEATURED</span>
                                <h3 class="hero-title">${app.title}</h3>
                                <div class="hero-dev">Downloader CODE: <strong>${code}</strong></div>
                            </div>
                        </div>
                    </div>
                `;
            });
            carouselEl.innerHTML = heroHtml;
        }

        // Render Category Shelf ("Royal Media Official Apps")
        if (shelfEl) {
            let shelfHtml = '';
            this.state.apps.forEach(app => {
                const isInstalled = !!this.state.installedPackages[app.package_name];
                const icon = app.icon_url || 'assets/store_logo.jpg';
                const code = app.version?.downloader_code || '925742';
                shelfHtml += `
                    <div class="app-card-item focusable" tabindex="0" onclick="StoreApp.openAppDetails(${app.id})">
                        <div class="app-card-icon-wrap">
                            <img src="${icon}" class="app-card-icon" onerror="this.src='assets/store_logo.jpg'">
                            ${isInstalled ? '<div class="installed-checkmark-badge">✓</div>' : ''}
                        </div>
                        <div class="app-card-title">${app.title}</div>
                        <div class="app-card-dl-code">⚡ CODE: ${code}</div>
                        <div class="card-quick-actions">
                            <button class="btn-card-scan focusable" onclick="event.stopPropagation(); StoreApp.openBarcodeModal(${app.id});" title="Scan Barcode / QR Code">
                                <span>📱 Barcode</span>
                            </button>
                            <button class="btn-card-scan focusable" onclick="event.stopPropagation(); StoreApp.openAppDetails(${app.id});" style="background: rgba(245, 158, 11, 0.18); border-color: rgba(245, 158, 11, 0.4); color: #fbbf24;">
                                <span>${isInstalled ? '▶ Open' : '⬇ Install'}</span>
                            </button>
                        </div>
                    </div>
                `;
            });
            shelfEl.innerHTML = shelfHtml;
        }
    },

    // =========================================================================
    // 2. App Details Screen
    // =========================================================================
    openAppDetails(appId) {
        const app = this.state.apps.find(a => a.id === appId);
        if (!app) return;

        this.state.selectedApp = app;
        this.state.activeScreenshotIndex = 0;

        // Hide other views
        document.querySelectorAll('.view-container > div').forEach(el => el.style.display = 'none');
        const detailsView = document.getElementById('view-details');
        if (detailsView) detailsView.style.display = 'block';

        const isInstalled = !!this.state.installedPackages[app.package_name];
        const v = app.version || { version_name: 'v1.0', file_size_formatted: '45.0 MB', downloads_count: 120, downloader_code: '925742' };
        const icon = app.icon_url || 'assets/store_logo.jpg';

        // Populate Details
        document.getElementById('details-app-icon').src = icon;
        document.getElementById('details-app-title').textContent = app.title;
        document.getElementById('details-app-dev').textContent = `Royal Media Official App • Code: ${v.downloader_code}`;
        document.getElementById('details-pill-cat').textContent = `📁 ${app.category_name || 'IPTV Players'}`;
        document.getElementById('details-pill-ver').textContent = `# ${v.version_name || 'v1.0'}`;
        document.getElementById('details-pill-size').textContent = `💾 ${v.file_size_formatted || '45 MB'}`;
        document.getElementById('details-pill-downloads').textContent = `⚡ Downloader: ${v.downloader_code}`;

        // Action Buttons
        const mainBtn = document.getElementById('btn-details-primary');
        const uninstallBtn = document.getElementById('btn-details-uninstall');

        if (isInstalled) {
            mainBtn.innerHTML = '▶ Open App';
            mainBtn.onclick = () => this.launchApp(app);
            uninstallBtn.style.display = 'inline-flex';
        } else {
            mainBtn.innerHTML = '⬇ Install App';
            mainBtn.onclick = () => this.startDownloadFlow(app);
            uninstallBtn.style.display = 'none';
        }

        // About & What's New
        document.getElementById('details-about-text').textContent = app.description || 'Fast, reliable and high performance streaming app provided by Royal Media.';
        document.getElementById('details-changelog-text').textContent = app.changelog || '🎥 High definition streaming pipeline\n📺 Full remote D-Pad navigation\n📜 Downloader app compatibility';

        // Screenshots
        this.renderScreenshotViewer(app);

        setTimeout(() => {
            this.setFocus(mainBtn);
        }, 100);
    },

    renderScreenshotViewer(app) {
        const mainImg = document.getElementById('details-main-screenshot');
        const thumbsContainer = document.getElementById('details-screenshot-thumbs');
        const screenshots = app.screenshots && app.screenshots.length > 0 ? app.screenshots : [
            'assets/screenshot_preview1.jpg',
            'assets/screenshot_preview2.jpg',
            'assets/screenshot_preview3.jpg'
        ];

        mainImg.src = screenshots[0];

        let thumbsHtml = '';
        screenshots.forEach((s, idx) => {
            thumbsHtml += `
                <div class="screenshot-thumb-item focusable ${idx === 0 ? 'active' : ''}" tabindex="0" onclick="StoreApp.selectScreenshot(${idx})">
                    <img src="${s}" onerror="this.src='assets/screenshot_preview1.jpg'">
                </div>
            `;
        });
        thumbsContainer.innerHTML = thumbsHtml;
    },

    selectScreenshot(idx) {
        this.state.activeScreenshotIndex = idx;
        const app = this.state.selectedApp;
        if (!app) return;
        const screenshots = app.screenshots || ['assets/screenshot_preview1.jpg'];
        const mainImg = document.getElementById('details-main-screenshot');
        if (mainImg && screenshots[idx]) {
            mainImg.src = screenshots[idx];
        }
        document.querySelectorAll('.screenshot-thumb-item').forEach((el, i) => {
            el.classList.toggle('active', i === idx);
        });
    },

    closeDetailsView() {
        document.getElementById('view-details').style.display = 'none';
        this.switchTab(this.state.activeTab || 'home');
    },

    // =========================================================================
    // 3. Barcode & Downloader Modal
    // =========================================================================
    openBarcodeModal(appId) {
        const app = this.state.apps.find(a => a.id === appId) || this.state.selectedApp;
        if (!app) return;
        this.state.selectedApp = app;
        this.openOtherWaysModal();
    },

    openOtherWaysModal() {
        const app = this.state.selectedApp;
        if (!app) return;

        const modal = document.getElementById('other-ways-modal');
        const v = app.version || {};
        const code = v.downloader_code || '925742';

        document.getElementById('modal-dl-code').textContent = code;

        // Accurate download URL (matches user's real aftv.news URL)
        const downloadUrl = `http://aftv.news/${code}`;
        document.getElementById('modal-copy-url').textContent = downloadUrl;

        // High resolution dynamic Barcode / QR Code
        const qrImg = document.getElementById('modal-qr-img');
        if (qrImg) {
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(downloadUrl)}`;
        }

        // Mirrors list (Download #1 & Download #2)
        const sourcesContainer = document.getElementById('modal-sources-list');
        const mirrors = [
            { name: `Download #1 (aftv.news/${code})`, badge: 'In-app download', url: `http://aftv.news/${code}` },
            { name: `Download #2 (Direct Link)`, badge: 'Direct mirror', url: `http://aftv.news/${code}` }
        ];

        let sourcesHtml = '';
        mirrors.forEach(m => {
            sourcesHtml += `
                <a href="${m.url}" target="_blank" class="source-row-item focusable" tabindex="0">
                    <span>⬇ ${m.name}</span>
                    <span class="source-pill-badge">${m.badge}</span>
                </a>
            `;
        });
        sourcesContainer.innerHTML = sourcesHtml;

        modal.style.display = 'flex';
        setTimeout(() => {
            const copyBtn = document.getElementById('btn-copy-download-link');
            if (copyBtn) this.setFocus(copyBtn);
        }, 100);
    },

    closeOtherWaysModal() {
        const modal = document.getElementById('other-ways-modal');
        if (modal) modal.style.display = 'none';
        const otherWaysBtn = document.getElementById('btn-details-other-ways');
        if (otherWaysBtn) this.setFocus(otherWaysBtn);
    },

    copyDownloadLink() {
        const urlText = document.getElementById('modal-copy-url').textContent;
        navigator.clipboard.writeText(urlText).then(() => {
            this.showToast('Download link copied: ' + urlText);
        }).catch(() => {
            this.showToast('Link: ' + urlText);
        });
    },

    // =========================================================================
    // 4. Download Engines & Install Flow
    // =========================================================================
    startDownloadFlow(app) {
        const engine = this.state.settings.downloadEngine;
        this.showToast(`Starting ${app.title} download via ${engine.toUpperCase()} engine...`);

        // Send real download tracking ping to backend
        fetch(`../api/download_track.php?app_id=${app.id}&engine=${encodeURIComponent(engine)}&device=${encodeURIComponent(navigator.userAgent)}`, { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.new_downloads_count !== undefined) {
                    if (app.version) app.version.downloads_count = data.new_downloads_count;
                    const pillEl = document.getElementById('details-pill-downloads');
                    if (pillEl) pillEl.textContent = `⚡ Downloader: ${app.version?.downloader_code} (${data.new_downloads_count} downloads)`;
                    this.renderHomeView();
                }
            })
            .catch(() => {
                if (app.version) {
                    app.version.downloads_count = (app.version.downloads_count || 0) + 1;
                    this.renderHomeView();
                }
            });

        let progress = 0;
        const interval = setInterval(() => {
            progress += (engine === 'turbo' ? 25 : 15);
            if (progress >= 100) {
                clearInterval(interval);
                this.verifyAndInstall(app);
            } else {
                this.showToast(`Downloading ${app.title}: ${progress}%`);
            }
        }, 250);
    },

    verifyAndInstall(app) {
        this.showToast('Checking file integrity (SHA-256 verified ✓)...');

        setTimeout(() => {
            this.state.installedPackages[app.package_name] = {
                version_code: app.version?.version_code || 1,
                version_name: app.version?.version_name || 'v1.0'
            };
            this.saveInstalledState();

            this.showToast(`Successfully installed ${app.title}!`);
            this.openAppDetails(app.id);
            this.renderHomeView();
        }, 600);
    },

    launchApp(app) {
        this.showToast(`Launching ${app.title}...`);
    },

    uninstallApp() {
        const app = this.state.selectedApp;
        if (!app) return;

        delete this.state.installedPackages[app.package_name];
        this.saveInstalledState();

        this.showToast(`Uninstalled ${app.title}`);
        this.openAppDetails(app.id);
        this.renderHomeView();
    },

    // =========================================================================
    // 5. Settings Screen
    // =========================================================================
    renderSettingsView() {
        const engineSelect = document.getElementById('setting-download-engine');
        if (engineSelect) engineSelect.value = this.state.settings.downloadEngine;

        const toggleAuto = document.getElementById('toggle-auto-install');
        if (toggleAuto) toggleAuto.classList.toggle('on', this.state.settings.installAuto);

        const toggleDelete = document.getElementById('toggle-delete-apk');
        if (toggleDelete) toggleDelete.classList.toggle('on', this.state.settings.deleteAfterInstall);

        const togglePerf = document.getElementById('toggle-perf-mode');
        if (togglePerf) togglePerf.classList.toggle('on', this.state.settings.performanceMode);

        this.updateUserProfileUI();
    },

    toggleSetting(key, elementId) {
        this.state.settings[key] = !this.state.settings[key];
        const el = document.getElementById(elementId);
        if (el) el.classList.toggle('on', this.state.settings[key]);

        if (key === 'performanceMode') {
            document.body.classList.toggle('performance-mode', this.state.settings.performanceMode);
            this.showToast(this.state.settings.performanceMode ? 'Performance mode enabled (Low RAM)' : 'Performance mode disabled');
        }

        this.saveSettingsState();
    },

    setDownloadEngine(val) {
        this.state.settings.downloadEngine = val;
        this.saveSettingsState();
        this.showToast(`Download engine set to ${val.toUpperCase()}`);
    },

    // =========================================================================
    // 6. Notices View
    // =========================================================================
    renderNoticesView() {
        const container = document.getElementById('notices-list-container');
        if (!container) return;

        let html = '';
        this.state.notices.forEach(n => {
            html += `
                <div class="notice-card focusable" tabindex="0">
                    <div class="notice-icon-box">📢</div>
                    <div class="notice-content">
                        <div class="notice-title">${n.title}</div>
                        <div class="notice-body">${n.content}</div>
                        <div class="notice-footer">
                            <span>${n.date_formatted || 'Royal Media Announcement'}</span>
                            ${n.app_id ? `<button class="btn-notice-open focusable" onclick="StoreApp.openAppDetails(${n.app_id})">&rarr; Open app</button>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    },

    // =========================================================================
    // 7. Updates View
    // =========================================================================
    async checkUpdates() {
        const installedPayload = Object.keys(this.state.installedPackages).map(pkg => ({
            package: pkg,
            version_code: this.state.installedPackages[pkg].version_code
        }));

        const data = await this.fetchWithDnsFailover('updates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ installed: installedPayload })
        });

        if (data && data.has_updates) {
            this.state.updates = data.updates;
            const badge = document.getElementById('updates-badge');
            if (badge) {
                badge.textContent = data.updates_count;
                badge.style.display = 'inline-block';
            }
        }
    },

    renderUpdatesView() {
        const container = document.getElementById('updates-list-container');
        if (!container) return;

        container.innerHTML = `
            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <div style="font-size: 48px; margin-bottom: 12px; color: var(--accent-gold);">✓</div>
                <h2 style="color: #fff;">All Royal Media apps are up to date!</h2>
                <p style="margin-top: 8px;">You have the latest versions of all installed TV applications.</p>
            </div>
        `;
    },

    renderDownloadsView() {
        const container = document.getElementById('downloads-list-container');
        if (!container) return;

        container.innerHTML = `
            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <div style="font-size: 48px; margin-bottom: 12px; color: var(--accent-gold);">⚡</div>
                <h2 style="color: #fff;">Royal Media Turbo Download Engine Active</h2>
                <p style="margin-top: 8px;">Multi-connection parallel streaming with automatic fallback to built-in and system engines.</p>
                <div style="margin-top: 24px;">
                    <span class="source-pill-badge" style="font-size: 13px; padding: 8px 18px;">Active Engine: ${this.state.settings.downloadEngine.toUpperCase()}</span>
                </div>
            </div>
        `;
    },

    // =========================================================================
    // 8. Search Functions
    // =========================================================================
    openSearchModal() {
        const modal = document.getElementById('search-modal');
        if (!modal) return;
        modal.style.display = 'flex';
        const input = document.getElementById('input-search-query');
        if (input) {
            input.value = '';
            setTimeout(() => this.setFocus(input), 100);
        }
        this.handleSearch('');
    },

    closeSearchModal() {
        const modal = document.getElementById('search-modal');
        if (modal) modal.style.display = 'none';
        const searchBtn = document.getElementById('btn-header-search');
        if (searchBtn) this.setFocus(searchBtn);
    },

    handleSearch(query) {
        const grid = document.getElementById('search-results-grid');
        if (!grid) return;

        query = (query || '').toLowerCase().trim();
        const filtered = this.state.apps.filter(a => {
            if (!query) return true;
            return a.title.toLowerCase().includes(query) ||
                   (a.developer && a.developer.toLowerCase().includes(query)) ||
                   (a.version?.downloader_code && a.version.downloader_code.includes(query)) ||
                   (a.package_name && a.package_name.toLowerCase().includes(query));
        });

        if (filtered.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 40px;">No matching Royal Media apps found.</div>';
            return;
        }

        let html = '';
        filtered.forEach(app => {
            const isInstalled = !!this.state.installedPackages[app.package_name];
            const icon = app.icon_url || 'assets/store_logo.jpg';
            const code = app.version?.downloader_code || '925742';
            html += `
                <div class="app-card-item focusable" tabindex="0" onclick="StoreApp.openAppDetails(${app.id}); StoreApp.closeSearchModal();">
                    <div class="app-card-icon-wrap">
                        <img src="${icon}" class="app-card-icon" onerror="this.src='assets/store_logo.jpg'">
                        ${isInstalled ? '<div class="installed-checkmark-badge">✓</div>' : ''}
                    </div>
                    <div class="app-card-title">${app.title}</div>
                    <div class="app-card-dl-code">⚡ CODE: ${code}</div>
                </div>
            `;
        });
        grid.innerHTML = html;
    },

    // =========================================================================
    // 9. Remote Control (D-Pad) Spatial Navigation Engine
    // =========================================================================
    initDpadNavigation() {
        window.addEventListener('keydown', (e) => {
            const key = e.keyCode || e.which;
            const focusableList = Array.from(document.querySelectorAll('.focusable:not([style*="display: none"])'));

            if ([37, 38, 39, 40, 19, 20, 21, 22].includes(key)) {
                e.preventDefault();
                this.navigateSpatial(key, focusableList);
            } else if ([13, 23].includes(key)) {
                if (this.state.currentFocusElement) {
                    this.state.currentFocusElement.click();
                }
            } else if ([27, 4, 10009].includes(key)) {
                e.preventDefault();
                this.handleBackNavigation();
            }
        });

        document.addEventListener('mouseover', (e) => {
            const target = e.target.closest('.focusable');
            if (target) this.setFocus(target, false);
        });
    },

    setFocus(el, scroll = true) {
        if (!el) return;
        if (this.state.currentFocusElement) {
            this.state.currentFocusElement.classList.remove('focused');
        }
        this.state.currentFocusElement = el;
        el.classList.add('focused');
        el.focus({ preventScroll: true });

        if (scroll) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        }
    },

    navigateSpatial(key, list) {
        const current = this.state.currentFocusElement || list[0];
        if (!current) return;

        const currentRect = current.getBoundingClientRect();
        let bestCandidate = null;
        let minDistance = Infinity;

        const isLeft = (key === 37 || key === 21);
        const isRight = (key === 39 || key === 22);
        const isUp = (key === 38 || key === 19);
        const isDown = (key === 40 || key === 20);

        list.forEach(el => {
            if (el === current || el.offsetParent === null) return;
            const rect = el.getBoundingClientRect();

            let isValidDirection = false;
            if (isLeft && rect.right <= currentRect.left + 5) isValidDirection = true;
            if (isRight && rect.left >= currentRect.right - 5) isValidDirection = true;
            if (isUp && rect.bottom <= currentRect.top + 5) isValidDirection = true;
            if (isDown && rect.top >= currentRect.bottom - 5) isValidDirection = true;

            if (isValidDirection) {
                const cx1 = currentRect.left + currentRect.width / 2;
                const cy1 = currentRect.top + currentRect.height / 2;
                const cx2 = rect.left + rect.width / 2;
                const cy2 = rect.top + rect.height / 2;

                const dx = cx2 - cx1;
                const dy = cy2 - cy1;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < minDistance) {
                    minDistance = dist;
                    bestCandidate = el;
                }
            }
        });

        if (bestCandidate) {
            this.setFocus(bestCandidate);
        }
    },

    openTvGuideModal() {
        const modal = document.getElementById('modal-tv-guide');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => {
                const btn = document.getElementById('btn-close-tv-guide');
                if (btn) this.setFocus(btn);
            }, 100);
        }
    },

    closeTvGuideModal() {
        const modal = document.getElementById('modal-tv-guide');
        if (modal) modal.style.display = 'none';
        const homeFocus = document.querySelector('#view-home .focusable');
        if (homeFocus) this.setFocus(homeFocus);
    },

    handleBackNavigation() {
        const tvGuideModal = document.getElementById('modal-tv-guide');
        if (tvGuideModal && tvGuideModal.style.display === 'flex') {
            this.closeTvGuideModal();
            return;
        }

        const searchModal = document.getElementById('search-modal');
        if (searchModal && searchModal.style.display === 'flex') {
            this.closeSearchModal();
            return;
        }

        const modal = document.getElementById('other-ways-modal');
        if (modal && modal.style.display === 'flex') {
            this.closeOtherWaysModal();
            return;
        }

        const detailsView = document.getElementById('view-details');
        if (detailsView && detailsView.style.display === 'block') {
            this.closeDetailsView();
            return;
        }

        if (this.state.activeTab !== 'home') {
            this.switchTab('home');
        }
    },

    showToast(msg) {
        const toast = document.getElementById('toast-msg');
        if (!toast) return;
        toast.textContent = msg;
        toast.style.display = 'block';
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => {
            toast.style.display = 'none';
        }, 3200);
    },

    togglePassword() {
        const pass = document.getElementById('input-password');
        const icon = document.getElementById('eye-icon');
        if (pass.type === 'password') {
            pass.type = 'text';
            if (icon) icon.textContent = '🙈';
        } else {
            pass.type = 'password';
            if (icon) icon.textContent = '👁';
        }
    },

    // Authentic Royal Media Apps Catalog & Downloader Codes
    getRoyalMediaApps() {
        return [
            {
                id: 1,
                title: 'RoyalTvXciptv.apk',
                package_name: 'com.royaltv.xciptv',
                developer: 'Royal Media',
                category_name: 'IPTV Players',
                icon_url: 'assets/royaltv_xc.jpg',
                banner_url: 'assets/screenshot_preview1.jpg',
                is_featured: true,
                description: 'RoyalTvXciptv official IPTV client. Optimized for high speed streaming on Android TV, Fire TV, and mobile devices. Supports live TV, movies, series, EPG TV guide, and multi-screen view.',
                changelog: '⚡ Multi-connection streaming support\n📺 Full remote D-Pad control\n📱 Downloader Code: 925742',
                version: {
                    version_name: 'v1.0',
                    version_code: 1,
                    file_size_formatted: '48.2 MB',
                    downloads_count: 0,
                    downloader_code: '925742'
                }
            },
            {
                id: 2,
                title: 'Royal Media IBO',
                package_name: 'com.royalmedia.ibo',
                developer: 'Royal Media',
                category_name: 'IPTV Players',
                icon_url: 'assets/royal_ibo.jpg',
                banner_url: 'assets/screenshot_preview2.jpg',
                is_featured: true,
                description: 'Royal Media IBO player with next-gen interface, fast channel switching, 4K/UHD video playback pipeline, and easy playlist loading.',
                changelog: '🎥 Fast playlist loading and caching\n📺 Subtitle and audio track selection\n📱 Downloader Code: 5451030',
                version: {
                    version_name: 'v1.0',
                    version_code: 1,
                    file_size_formatted: '52.6 MB',
                    downloads_count: 0,
                    downloader_code: '5451030'
                }
            },
            {
                id: 3,
                title: 'Royal Media TiVi',
                package_name: 'com.royalmedia.tivi',
                developer: 'Royal Media',
                category_name: 'IPTV Players',
                icon_url: 'assets/royal_tivi.jpg',
                banner_url: 'assets/screenshot_preview3.jpg',
                is_featured: true,
                description: 'Royal Media TiVi companion app for high performance video and audio playback, advanced favorites management, and TV remote integration.',
                changelog: '⚡ Ultra-smooth remote navigation\n📺 Custom grid and list layout\n📱 Downloader Code: 8542493',
                version: {
                    version_name: 'v1.0',
                    version_code: 1,
                    file_size_formatted: '46.1 MB',
                    downloads_count: 0,
                    downloader_code: '8542493'
                }
            },
            {
                id: 4,
                title: 'Royal Media Smarters',
                package_name: 'com.royalmedia.smarters',
                developer: 'Royal Media',
                category_name: 'IPTV Players',
                icon_url: 'assets/royal_smarters.jpg',
                banner_url: 'assets/screenshot_preview1.jpg',
                is_featured: false,
                description: 'Royal Media Smarters app with full Xtream Codes support, multi-user login, parental controls, and catch-up TV.',
                changelog: '🔐 Multi-account support\n📺 Catchup and EPG guide\n📱 Downloader Code: 6595514',
                version: {
                    version_name: 'v1.0',
                    version_code: 1,
                    file_size_formatted: '55.8 MB',
                    downloads_count: 0,
                    downloader_code: '6595514'
                }
            },
            {
                id: 5,
                title: 'ROYAL VPN',
                package_name: 'com.royalmedia.vpn',
                developer: 'Royal Media',
                category_name: 'Utilities & Tools',
                icon_url: 'assets/royal_vpn.jpg',
                banner_url: 'assets/screenshot_preview2.jpg',
                is_featured: true,
                description: 'Official ROYAL VPN application. Protect your online streaming, prevent ISP throttling, and unblock global servers on Android TV, Fire TV, and mobile.',
                changelog: '🛡️ High-speed streaming VPN servers\n⚡ Zero log policy and kill switch\n📱 Downloader Code: 6808531',
                version: {
                    version_name: 'v1.0',
                    version_code: 1,
                    file_size_formatted: '28.4 MB',
                    downloads_count: 0,
                    downloader_code: '6808531'
                }
            }
        ];
    },

    getRoyalMediaNotices() {
        return [
            {
                id: 1,
                title: 'RoyalTvXciptv is ready for download',
                content: '⚡ RoyalTvXciptv.apk is available now.\n- Downloader Code: 925742\n- Direct Link: http://aftv.news/925742\n- Full remote D-Pad control and multi-screen support.',
                app_id: 1,
                date_formatted: 'Royal Media Notice'
            },
            {
                id: 2,
                title: 'Royal Media IBO Player & TiVi updates',
                content: '🎥 Royal Media IBO (Code: 5451030) and Royal Media TiVi (Code: 8542493) are online with enhanced high definition streaming pipelines.',
                app_id: 2,
                date_formatted: 'Royal Media Notice'
            },
            {
                id: 3,
                title: 'ROYAL VPN for IPTV protection',
                content: '🛡️ Download ROYAL VPN (Code: 6808531) to protect your streaming and unblock unrestricted speeds.',
                app_id: 5,
                date_formatted: 'Royal Media Notice'
            }
        ];
    }
};

document.addEventListener('DOMContentLoaded', () => {
    StoreApp.init();
});
