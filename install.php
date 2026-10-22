<?php
/**
 * One-Page Web Installer
 * Sets up Database, Admin Account & Sample Data in under 2 minutes.
 * Works seamlessly on Free Hosting (InfinityFree, 000webhost, cPanel, etc.)
 */

require_once __DIR__ . '/config.php';

$isInstalled = false;
if (file_exists(DB_SQLITE_PATH)) {
    try {
        $testPdo = new PDO('sqlite:' . DB_SQLITE_PATH);
        $check = $testPdo->query("SELECT value FROM settings WHERE key = 'installed'")->fetchColumn();
        if ($check === '1') {
            $isInstalled = true;
        }
    } catch (Exception $e) {}
}

$error = '';
$success = false;

// Pre-flight Environment Checks
$checks = [
    'php_version' => [
        'title' => 'PHP Version >= 7.4',
        'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'detail' => 'Current: PHP ' . PHP_VERSION
    ],
    'pdo_sqlite' => [
        'title' => 'PDO SQLite Extension',
        'status' => extension_loaded('pdo_sqlite'),
        'detail' => extension_loaded('pdo_sqlite') ? 'Enabled (Zero-setup storage)' : 'Missing PDO SQLite'
    ],
    'zip_ext' => [
        'title' => 'ZipArchive Extension',
        'status' => class_exists('ZipArchive'),
        'detail' => class_exists('ZipArchive') ? 'Enabled (APK Parser Ready)' : 'Optional (Manual input fallback)'
    ],
    'data_writable' => [
        'title' => 'Data Directory Writable',
        'status' => is_writable(__DIR__ . '/data') || @mkdir(__DIR__ . '/data', 0755, true),
        'detail' => 'data/ folder permissions'
    ],
    'uploads_writable' => [
        'title' => 'Uploads Directory Writable',
        'status' => is_writable(__DIR__ . '/uploads') || @mkdir(__DIR__ . '/uploads/apks', 0755, true),
        'detail' => 'uploads/ folder permissions'
    ]
];

$allPass = $checks['php_version']['status'] && $checks['pdo_sqlite']['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allPass) {
    try {
        require_once __DIR__ . '/includes/db.php';
        require_once __DIR__ . '/includes/helpers.php';

        $adminUser = trim($_POST['admin_user'] ?? 'admin');
        $adminPass = trim($_POST['admin_pass'] ?? 'admin123');
        $storeName = trim($_POST['store_name'] ?? 'Royal Media');

        $pdo = getDbConnection();

        // Update Store Name
        setSetting('store_name', $storeName);
        setSetting('installed', '1');

        // Update Admin Credentials
        if (!empty($adminUser) && !empty($adminPass)) {
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT OR REPLACE INTO admins (id, username, password_hash, created_at) VALUES (1, ?, ?, ?)")
                ->execute([$adminUser, $hash, date('Y-m-d H:i:s')]);
        }

        $success = true;
    } catch (Exception $e) {
        $error = 'Installation Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Next-Gen TV App Store Installer</title>
    <style>
        :root {
            --bg: #0e1118;
            --card: #161b2a;
            --primary: #5c7cfa;
            --cyan: #00d2d3;
            --text: #f8f9fa;
            --muted: #868e96;
            --success: #40c057;
            --danger: #fa5252;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: radial-gradient(circle at 50% 20%, #1e263d 0%, #0e1118 80%);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .install-box {
            max-width: 540px;
            width: 100%;
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 36px 30px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6), 0 0 35px rgba(92, 124, 250, 0.2);
        }
        .header { text-align: center; margin-bottom: 24px; }
        .logo-icon { font-size: 44px; margin-bottom: 10px; }
        .title { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
        .sub { color: var(--muted); font-size: 13px; }
        .checks-list {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 13px;
        }
        .check-item:last-child { border-bottom: none; }
        .badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        .badge-pass { background: rgba(64, 192, 87, 0.2); color: var(--success); }
        .badge-fail { background: rgba(250, 82, 82, 0.2); color: var(--danger); }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-control {
            width: 100%;
            background: #10141f;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }
        .form-control:focus { border-color: var(--primary); }
        .btn-submit {
            width: 100%;
            background: linear-gradient(90deg, #6c5ce7, #00d2d3);
            border: none;
            color: #fff;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(0, 210, 211, 0.35);
            margin-top: 10px;
        }
        .btn-submit:hover { opacity: 0.95; }
        .success-box {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="install-box">
        <div class="header">
            <div class="logo-icon">🧙‍♂️</div>
            <h1 class="title">Web Installer (2 Minutes)</h1>
            <p class="sub">Free Hosting Ready &bull; SQLite Zero-Setup Built-in</p>
        </div>

        <?php if ($success): ?>
            <div class="success-box">
                <div style="font-size: 48px; margin-bottom: 14px;">🎉</div>
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 8px;">Installation Complete!</h2>
                <p style="color: var(--muted); font-size: 14px; margin-bottom: 24px;">Your database, sample apps, and admin account are configured and ready.</p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <a href="admin/index.php" style="background: var(--primary); color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 10px; font-weight: 600; font-size: 14px;">Admin Panel &rarr;</a>
                    <a href="app/index.html" style="background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 10px; font-weight: 600; font-size: 14px;">Open TV Store &rarr;</a>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div style="background: rgba(250,82,82,0.2); color: var(--danger); padding: 10px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="checks-list">
                <?php foreach ($checks as $c): ?>
                    <div class="check-item">
                        <span><?= $c['title'] ?> <small style="color: var(--muted);">(<?= $c['detail'] ?>)</small></span>
                        <span class="badge <?= $c['status'] ? 'badge-pass' : 'badge-fail' ?>">
                            <?= $c['status'] ? 'Passed' : 'Attention' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" action="install.php">
                <div class="form-group">
                    <label class="form-label">Store Brand Name</label>
                    <input type="text" name="store_name" class="form-control" value="Royal Media" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Username</label>
                    <input type="text" name="admin_user" class="form-control" value="admin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Password</label>
                    <input type="password" name="admin_pass" class="form-control" value="admin123" required>
                </div>
                <button type="submit" class="btn-submit" <?= !$allPass ? 'disabled' : '' ?>>
                    🚀 Run 2-Minute Installation &rarr;
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
