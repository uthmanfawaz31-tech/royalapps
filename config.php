<?php
/**
 * Global Configuration - Next-Gen TV App Store
 * Optimized for Free PHP Hosting (PHP 7.4 - 8.5)
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

// Security Secret Key for Signing Download URLs & API Requests
define('APP_SECRET_KEY', 'royal_sec_7f9c2d1840e63ba9012e845c7');

// Database Driver: 'sqlite' (Recommended for 0-setup / Free Hosting) or 'mysql'
define('DB_DRIVER', 'sqlite');

// SQLite Database File Path
define('DB_SQLITE_PATH', APP_ROOT . '/data/store.sqlite');

// MySQL Configuration (If you prefer MySQL over SQLite)
define('DB_MYSQL_HOST', 'localhost');
define('DB_MYSQL_PORT', '3306');
define('DB_MYSQL_NAME', 'newappstore');
define('DB_MYSQL_USER', 'root');
define('DB_MYSQL_PASS', '');

// Upload Paths
define('UPLOAD_DIR_APKS', APP_ROOT . '/uploads/apks');
define('UPLOAD_DIR_ICONS', APP_ROOT . '/uploads/icons');
define('UPLOAD_DIR_SCREENSHOTS', APP_ROOT . '/uploads/screenshots');
define('UPLOAD_DIR_BANNERS', APP_ROOT . '/uploads/banners');

// URL Helper
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Clean trailing slashes and subdirectories
    $scriptDir = str_replace(['/admin', '/api', '/app'], '', $scriptDir);
    $scriptDir = rtrim($scriptDir, '/\\');
    return $protocol . $host . ($scriptDir ? $scriptDir : '');
}
