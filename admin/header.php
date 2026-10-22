<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdminLogin();

$currentScript = basename($_SERVER['SCRIPT_NAME']);
$storeName = getSetting('store_name', 'Royal Media');
$storeLogo = getSetting('store_logo', 'uploads/icons/store_logo.jpg');
$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin Panel') ?> - <?= htmlspecialchars($storeName) ?></title>
        <link rel="stylesheet" href="assets/style.css?v=20261022_final">
    <style>
    /* Critical Admin Mobile Drawer & Grid Styles (Zero-Cache Guarantee) */
    .mobile-hamburger-btn {
        display: none;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        width: 38px;
        height: 38px;
        border-radius: 10px;
        font-size: 18px;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        outline: none;
    }
    .sidebar-close-btn {
        display: none;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        width: 32px;
        height: 32px;
        border-radius: 8px;
        font-size: 20px;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(4px);
        z-index: 998;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }
    .sidebar-backdrop.active {
        opacity: 1;
        pointer-events: auto;
    }

    @media (max-width: 992px) {
        /* Turn sidebar into off-canvas drawer */
        .sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            bottom: 0 !important;
            width: 270px !important;
            max-width: 85vw !important;
            transform: translateX(-100%) !important;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
            z-index: 1000 !important;
            box-shadow: none !important;
        }
        .sidebar.open {
            transform: translateX(0) !important;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.95) !important;
        }
        .sidebar-backdrop { display: block !important; }
        .mobile-hamburger-btn { display: inline-flex !important; }
        .sidebar-close-btn { display: inline-flex !important; }

        .main-wrapper {
            margin-left: 0 !important;
            width: 100% !important;
            min-width: 0 !important;
        }
        .top-nav {
            padding: 12px 16px !important;
        }
        .content-body {
            padding: 16px !important;
        }

        /* Responsive Grids */
        .grid-layout-2-1,
        .grid-layout-1-2,
        .grid-layout-2,
        .grid-layout-3,
        div[style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }
    }
    @media (max-width: 640px) {
        .admin-user-pill { display: none !important; }
        .top-nav {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
        }
        .nav-actions {
            justify-content: space-between !important;
            width: 100% !important;
        }
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
            gap: 12px !important;
        }
        .card-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px !important;
        }
    }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="sidebar-backdrop" onclick="toggleSidebar(false)"></div>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                <img src="<?= $baseUrl ?>/<?= ltrim($storeLogo, '/') ?>" class="sidebar-logo" alt="Logo" onerror="this.src='../app/assets/store_logo.jpg'">
                <div style="min-width: 0;">
                    <div class="brand-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($storeName) ?></div>
                    <span class="brand-badge">ADMIN v2.0</span>
                </div>
            </div>
            <button type="button" class="sidebar-close-btn" onclick="toggleSidebar(false)" aria-label="Close menu">&times;</button>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="index.php" class="menu-link <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                    <span class="menu-icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="apps.php" class="menu-link <?= $currentScript === 'apps.php' ? 'active' : '' ?>">
                    <span class="menu-icon">📱</span>
                    <span>Apps & APKs</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="categories.php" class="menu-link <?= $currentScript === 'categories.php' ? 'active' : '' ?>">
                    <span class="menu-icon">🗂️</span>
                    <span>Categories</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="users.php" class="menu-link <?= $currentScript === 'users.php' ? 'active' : '' ?>">
                    <span class="menu-icon">👥</span>
                    <span>Store Accounts</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="dns.php" class="menu-link <?= $currentScript === 'dns.php' ? 'active' : '' ?>">
                    <span class="menu-icon">📡</span>
                    <span>DNS Manager</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="notices.php" class="menu-link <?= $currentScript === 'notices.php' ? 'active' : '' ?>">
                    <span class="menu-icon">🔔</span>
                    <span>Notices & Push</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="branding.php" class="menu-link <?= $currentScript === 'branding.php' ? 'active' : '' ?>">
                    <span class="menu-icon">🎨</span>
                    <span>Brand & Styling</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="profile.php" class="menu-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                    <span class="menu-icon">🔐</span>
                    <span>Admin Credentials</span>
                </a>
            </li>
            <li class="menu-item" style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                <a href="../app/index.html" target="_blank" class="menu-link">
                    <span class="menu-icon">📺</span>
                    <span>Open TV Store</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTheme()" title="Switch Light/Dark mode">🌓 Theme</button>
            <a href="login.php?logout=1" class="btn btn-danger btn-sm">🚪 Logout</a>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <header class="top-nav">
            <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                <button type="button" class="mobile-hamburger-btn" onclick="toggleSidebar(true)" aria-label="Open navigation menu">
                    <span>☰</span>
                </button>
                <h1 class="nav-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="nav-actions">
                <span class="admin-user-pill" style="color: var(--text-secondary); font-size: 13px;">Logged in as <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></strong></span>
                <a href="apps.php?action=add" class="btn btn-primary btn-sm">+ Upload APK</a>
            </div>
        </header>
        <main class="content-body">
