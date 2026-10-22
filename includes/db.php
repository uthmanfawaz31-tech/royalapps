<?php
/**
 * Database Connection & Auto-Migrator
 * Supports SQLite (Default, Zero-Setup) & MySQL
 */

require_once __DIR__ . '/../config.php';

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            $dbDir = dirname(DB_SQLITE_PATH);
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0755, true);
            }
            $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
        } else {
            $dsn = "mysql:host=" . DB_MYSQL_HOST . ";port=" . DB_MYSQL_PORT . ";dbname=" . DB_MYSQL_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_MYSQL_USER, DB_MYSQL_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }
        
        // Auto-initialize schema if needed
        initDatabase($pdo);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        die("Database connection failed. Please ensure SQLite or MySQL is configured.");
    }
}

function initDatabase($pdo) {
    // Check if initialized
    $tables = [];
    if (DB_DRIVER === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
        $exists = $stmt->fetch();
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        $exists = $stmt->fetch();
    }

    if ($exists) {
        return; // Already initialized
    }

    // SQLite / MySQL Compatible DDL
    $isSqlite = (DB_DRIVER === 'sqlite');
    $autoInc = $isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";
    $textType = "TEXT";

    $queries = [
        "CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(64) PRIMARY KEY,
            `value` $textType
        )",

        "CREATE TABLE IF NOT EXISTS admins (
            id $autoInc,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS categories (
            id $autoInc,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            icon VARCHAR(50) DEFAULT 'folder',
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS apps (
            id $autoInc,
            category_id INT,
            title VARCHAR(150) NOT NULL,
            package_name VARCHAR(150) NOT NULL,
            developer VARCHAR(100) DEFAULT '',
            short_desc $textType,
            description $textType,
            changelog $textType,
            icon_url VARCHAR(255) DEFAULT '',
            banner_url VARCHAR(255) DEFAULT '',
            is_featured INT DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS app_versions (
            id $autoInc,
            app_id INT NOT NULL,
            version_name VARCHAR(50) NOT NULL,
            version_code INT DEFAULT 1,
            file_path VARCHAR(255) DEFAULT '',
            file_size BIGINT DEFAULT 0,
            sha256_hash VARCHAR(64) DEFAULT '',
            downloader_code VARCHAR(20) DEFAULT '',
            mirror_main $textType,
            mirror_web $textType,
            mirror_backup $textType,
            is_current INT DEFAULT 1,
            downloads_count INT DEFAULT 0,
            created_at DATETIME NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS app_screenshots (
            id $autoInc,
            app_id INT NOT NULL,
            image_url VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0
        )",

        "CREATE TABLE IF NOT EXISTS store_users (
            id $autoInc,
            username VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            expiry_date DATE,
            max_devices INT DEFAULT 1,
            notes $textType,
            created_at DATETIME NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS user_devices (
            id $autoInc,
            user_id INT NOT NULL,
            device_id VARCHAR(128) NOT NULL,
            device_model VARCHAR(100) DEFAULT '',
            ip_address VARCHAR(45) DEFAULT '',
            last_seen DATETIME NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS dns_servers (
            id $autoInc,
            server_url VARCHAR(255) NOT NULL,
            protocol VARCHAR(10) DEFAULT 'https',
            status VARCHAR(20) DEFAULT 'online',
            response_time_ms INT DEFAULT 0,
            is_active INT DEFAULT 1,
            sort_order INT DEFAULT 0
        )",

        "CREATE TABLE IF NOT EXISTS notices (
            id $autoInc,
            title VARCHAR(200) NOT NULL,
            content $textType NOT NULL,
            app_id INT DEFAULT NULL,
            is_active INT DEFAULT 1,
            created_at DATETIME NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS download_logs (
            id $autoInc,
            version_id INT,
            app_id INT,
            ip_address VARCHAR(45),
            engine VARCHAR(50) DEFAULT 'turbo',
            created_at DATETIME NOT NULL
        )"
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }

    // Insert Default Settings
    $defaultSettings = [
        'store_name' => 'Royal Media',
        'store_subtitle' => 'All your Royal streaming apps in one place',
        'store_logo' => 'uploads/icons/store_logo.jpg',
        'store_banner' => '',
        'theme_accent_color' => '#f59e0b',
        'theme_glow_color' => '#00d2d3',
        'xtream_enabled' => '1',
        'xtream_server_url' => 'http://demo.xtream-codes.com:8080',
        'onesignal_app_id' => '',
        'onesignal_api_key' => '',
        'admin_dark_mode' => '1',
        'installed' => '1'
    ];

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (`key`, `value`) VALUES (?, ?)");
    foreach ($defaultSettings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // Insert Default Admin (admin / admin123)
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)")
        ->execute(['admin', $adminPass, date('Y-m-d H:i:s')]);

    // Insert Default Categories
    $catStmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, sort_order, created_at) VALUES (?, ?, ?, ?, ?)");
    $now = date('Y-m-d H:i:s');
    $catStmt->execute(['IPTV Players', 'iptv-players', 'tv', 1, $now]);
    $catStmt->execute(['Utilities & Tools', 'utilities', 'wrench', 2, $now]);

    // Insert Default Store User (kasun / 123456)
    $userPass = password_hash('123456', PASSWORD_BCRYPT);
    $userExpiry = date('Y-m-d', strtotime('+365 days'));
    $pdo->prepare("INSERT INTO store_users (username, password_hash, status, expiry_date, max_devices, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute(['kasun', $userPass, 'active', $userExpiry, 3, 'Royal Media VIP Member', $now]);

    // Seed Initial Apps from user's assets
    seedInitialApps($pdo);
}

function seedInitialApps($pdo) {
    $now = date('Y-m-d H:i:s');
    
    // 1. RoyalTvXciptv.apk (Code: 925742)
    $stmt = $pdo->prepare("INSERT INTO apps (category_id, title, package_name, developer, short_desc, description, changelog, icon_url, is_featured, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        1,
        'RoyalTvXciptv.apk',
        'com.royaltv.xciptv',
        'Royal Media',
        'Official RoyalTvXciptv client for Android TV, Fire TV, and mobile.',
        'RoyalTvXciptv official IPTV client. Optimized for high speed streaming on Android TV, Fire TV, and mobile devices. Supports live TV, movies, series, EPG TV guide, and multi-screen view.',
        "⚡ Multi-connection streaming support\n📺 Full remote D-Pad control\n📱 Downloader Code: 925742",
        'uploads/icons/royaltv_xc.jpg',
        1,
        1,
        $now
    ]);
    $royalTvId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO app_versions (app_id, version_name, version_code, file_size, sha256_hash, downloader_code, mirror_main, mirror_web, mirror_backup, is_current, downloads_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $royalTvId,
            'v1.0',
            1,
            48200000,
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            '925742',
            'uploads/apks/RoyalTvXciptv.apk',
            'http://aftv.news/925742',
            'http://aftv.news/925742',
            1,
            0,
            $now
        ]);

    // 2. Royal Media IBO (Code: 5451030)
    $stmt->execute([
        1,
        'Royal Media IBO',
        'com.royalmedia.ibo',
        'Royal Media',
        'Royal Media IBO player with fast channel switching.',
        'Royal Media IBO player with next-gen interface, fast channel switching, 4K/UHD video playback pipeline, and easy playlist loading.',
        "🎥 Fast playlist loading and caching\n📺 Subtitle and audio track selection\n📱 Downloader Code: 5451030",
        'uploads/icons/royal_ibo.jpg',
        1,
        2,
        $now
    ]);
    $iboId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO app_versions (app_id, version_name, version_code, file_size, sha256_hash, downloader_code, mirror_main, mirror_web, mirror_backup, is_current, downloads_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $iboId,
            'v1.0',
            1,
            52600000,
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            '5451030',
            'uploads/apks/Royal_Media_IBO.apk',
            'http://aftv.news/5451030',
            'http://aftv.news/5451030',
            1,
            0,
            $now
        ]);

    // 3. Royal Media TiVi (Code: 8542493)
    $stmt->execute([
        1,
        'Royal Media TiVi',
        'com.royalmedia.tivi',
        'Royal Media',
        'Royal Media TiVi player with advanced multi-screen and custom layout.',
        'Royal Media TiVi companion app for high performance video and audio playback, advanced favorites management, and TV remote integration.',
        "⚡ Ultra-smooth remote navigation\n📺 Custom grid and list layout\n📱 Downloader Code: 8542493",
        'uploads/icons/royal_tivi.jpg',
        1,
        3,
        $now
    ]);
    $tiviId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO app_versions (app_id, version_name, version_code, file_size, sha256_hash, downloader_code, mirror_main, mirror_web, mirror_backup, is_current, downloads_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $tiviId,
            'v1.0',
            1,
            46100000,
            'f2ca1bb6c7e907d06dafe4687e579fce76b37e4e93b7605022da52e6ccc26fd2',
            '8542493',
            'uploads/apks/Royal_Media_TiVi.apk',
            'http://aftv.news/8542493',
            'http://aftv.news/8542493',
            1,
            0,
            $now
        ]);

    // 4. Royal Media Smarters (Code: 6595514)
    $stmt->execute([
        1,
        'Royal Media Smarters',
        'com.royalmedia.smarters',
        'Royal Media',
        'Royal Media Smarters app with full Xtream Codes support.',
        'Royal Media Smarters app with full Xtream Codes support, multi-user login, parental controls, and catch-up TV.',
        "🔐 Multi-account support\n📺 Catchup and EPG guide\n📱 Downloader Code: 6595514",
        'uploads/icons/royal_smarters.jpg',
        0,
        4,
        $now
    ]);
    $smartersId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO app_versions (app_id, version_name, version_code, file_size, sha256_hash, downloader_code, mirror_main, mirror_web, mirror_backup, is_current, downloads_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $smartersId,
            'v1.0',
            1,
            55800000,
            '9f83c078910d71cf9d8dc7da1d35d6b4122d057a702b8d4cf72d8e48b8109a90',
            '6595514',
            'uploads/apks/Royal_Media_Smarters.apk',
            'http://aftv.news/6595514',
            'http://aftv.news/6595514',
            1,
            0,
            $now
        ]);

    // 5. ROYAL VPN (Code: 6808531)
    $stmt->execute([
        2,
        'ROYAL VPN',
        'com.royalmedia.vpn',
        'Royal Media',
        'Official ROYAL VPN application for streaming protection.',
        'Official ROYAL VPN application. Protect your online streaming, prevent ISP throttling, and unblock global servers on Android TV, Fire TV, and mobile.',
        "🛡️ High-speed streaming VPN servers\n⚡ Zero log policy and kill switch\n📱 Downloader Code: 6808531",
        'uploads/icons/royal_vpn.jpg',
        1,
        5,
        $now
    ]);
    $vpnId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO app_versions (app_id, version_name, version_code, file_size, sha256_hash, downloader_code, mirror_main, mirror_web, mirror_backup, is_current, downloads_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $vpnId,
            'v1.0',
            1,
            28400000,
            'd41d8cd98f00b204e9800998ecf8427e',
            '6808531',
            'uploads/apks/ROYAL_VPN.apk',
            'http://aftv.news/6808531',
            'http://aftv.news/6808531',
            1,
            0,
            $now
        ]);

    // Insert Default Notices
    $notStmt = $pdo->prepare("INSERT INTO notices (title, content, app_id, is_active, created_at) VALUES (?, ?, ?, ?, ?)");
    $notStmt->execute([
        'RoyalTvXciptv.apk is ready for download',
        "⚡ RoyalTvXciptv.apk is available now.\n- Downloader Code: 925742\n- Direct Link: http://aftv.news/925742\n- Full remote D-Pad control and multi-screen support.",
        $royalTvId,
        1,
        date('Y-m-d H:i:s', strtotime('-1 day'))
    ]);
    $notStmt->execute([
        'Royal Media IBO Player & TiVi updates',
        "🎥 Royal Media IBO (Code: 5451030) and Royal Media TiVi (Code: 8542493) are online with enhanced high definition streaming pipelines.",
        $iboId,
        1,
        date('Y-m-d H:i:s', strtotime('-1 day'))
    ]);
    $notStmt->execute([
        'ROYAL VPN for IPTV protection',
        "🛡️ Download ROYAL VPN (Code: 6808531) to protect your streaming and unblock unrestricted speeds.",
        $vpnId,
        1,
        date('Y-m-d H:i:s', strtotime('-2 days'))
    ]);

    // Default DNS Servers
    $dnsStmt = $pdo->prepare("INSERT INTO dns_servers (server_url, protocol, status, response_time_ms, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    $dnsStmt->execute(['https://api.yourstoredomain.com', 'https', 'online', 42, 1, 1]);
    $dnsStmt->execute(['http://backup.yourstoredomain.com', 'http', 'online', 65, 1, 2]);
}
