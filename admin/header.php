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
    <link rel="stylesheet" href="assets/style.css">
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
