<?php
/**
 * Downloader Shortcode Resolver & Tokenized APK Streamer
 * Handles direct streaming, token verification, and Downloader App integration.
 */

require_once __DIR__ . '/includes/helpers.php';

$pdo = getDbConnection();
$token = $_GET['t'] ?? '';
$code = $_GET['code'] ?? '';
$appId = (int)($_GET['app'] ?? 0);

$version = null;

// Case 1: Tokenized Download
if (!empty($token)) {
    $versionId = verifyDownloadToken($token);
    if (!$versionId) {
        http_response_code(403);
        die("<h3>403 Forbidden: Download link has expired or is invalid. Please get a fresh link from the store.</h3>");
    }

    $stmt = $pdo->prepare("SELECT v.*, a.title, a.package_name FROM app_versions v JOIN apps a ON v.app_id = a.id WHERE v.id = ?");
    $stmt->execute([$versionId]);
    $version = $stmt->fetch();
}

// Case 2: Downloader Code
elseif (!empty($code)) {
    $stmt = $pdo->prepare("SELECT v.*, a.title, a.package_name, a.icon_url, a.description FROM app_versions v JOIN apps a ON v.app_id = a.id WHERE v.downloader_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $version = $stmt->fetch();
}

// Case 3: App ID lookup
elseif ($appId > 0) {
    $stmt = $pdo->prepare("SELECT v.*, a.title, a.package_name, a.icon_url, a.description FROM app_versions v JOIN apps a ON v.app_id = a.id WHERE a.id = ? AND v.is_current = 1 LIMIT 1");
    $stmt->execute([$appId]);
    $version = $stmt->fetch();
}

if (!$version) {
    http_response_code(404);
    die("<h3>404 Not Found: Requested application was not found.</h3>");
}

// Detect if user agent is Downloader App or direct APK downloader
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isDownloaderApp = (stripos($userAgent, 'Downloader') !== false || stripos($userAgent, 'Dalvik') !== false || stripos($userAgent, 'AFTV') !== false || !empty($token));

// Resolve Local File Path
$localPath = '';
if (!empty($version['file_path'])) {
    if (file_exists(APP_ROOT . '/' . ltrim($version['file_path'], '/'))) {
        $localPath = APP_ROOT . '/' . ltrim($version['file_path'], '/');
    } elseif (file_exists($version['file_path'])) {
        $localPath = $version['file_path'];
    }
}

// If Downloader App or direct token, stream or redirect
if ($isDownloaderApp || isset($_GET['direct'])) {
    // Increment download count and record log
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $pdo->prepare("UPDATE app_versions SET downloads_count = downloads_count + 1 WHERE id = ?")->execute([$version['id']]);
        $pdo->prepare("INSERT INTO download_logs (version_id, app_id, ip_address, engine, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$version['id'], $version['app_id'], $ip, $isDownloaderApp ? 'downloader_app' : 'direct', date('Y-m-d H:i:s')]);
    } catch (Exception $e) {
        // Continue download even if logging has issue
    }

    // If local APK exists, stream with HTTP Range support for resume
    if ($localPath && file_exists($localPath)) {
        streamApkFile($localPath, $version['title'] . '_' . $version['version_name'] . '.apk');
        exit;
    }

    // Otherwise redirect to main mirror or backup mirror
    if (!empty($version['mirror_main']) && filter_var($version['mirror_main'], FILTER_VALIDATE_URL)) {
        header("Location: " . $version['mirror_main']);
        exit;
    }

    if (!empty($version['mirror_backup']) && filter_var($version['mirror_backup'], FILTER_VALIDATE_URL)) {
        header("Location: " . $version['mirror_backup']);
        exit;
    }
}

// Render Browser Landing Page (with Downloader code, QR, and Mirrors)
$baseUrl = getBaseUrl();
$cleanTitle = htmlspecialchars($version['title']);
$cleanVersion = htmlspecialchars($version['version_name']);
$cleanCode = htmlspecialchars($version['downloader_code']);
$cleanSize = formatBytes($version['file_size']);
$directUrl = $baseUrl . '/dl.php?code=' . urlencode($cleanCode) . '&direct=1';
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" . urlencode($directUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download <?= $cleanTitle ?> - <?= getSetting('store_name', 'Royal Media') ?></title>
    <style>
        :root {
            --bg-dark: #0e1118;
            --card-bg: rgba(22, 27, 39, 0.85);
            --border-glow: #5c7cfa;
            --cyan: #00d2d3;
            --text-main: #f8f9fa;
            --text-muted: #868e96;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-dark);
            background-image: radial-gradient(circle at 50% 20%, #1e263d 0%, #0e1118 80%);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 520px;
            width: 100%;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 32px 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6), 0 0 40px rgba(92, 124, 250, 0.15);
            backdrop-filter: blur(20px);
            text-align: center;
        }
        .app-title { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .app-sub { color: var(--text-muted); font-size: 14px; margin-bottom: 24px; }
        .downloader-banner {
            background: linear-gradient(135deg, #1b2438, #161c2e);
            border: 1px solid rgba(92, 124, 250, 0.4);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .banner-label { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: var(--cyan); margin-bottom: 8px; font-weight: 600; }
        .code-display { font-size: 42px; font-weight: 800; letter-spacing: 4px; color: #ffffff; font-family: monospace; }
        .banner-help { font-size: 13px; color: var(--text-muted); margin-top: 6px; }
        .qr-section { margin-bottom: 24px; }
        .qr-box {
            background: #ffffff;
            padding: 12px;
            border-radius: 16px;
            display: inline-block;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        .qr-box img { width: 170px; height: 170px; display: block; }
        .qr-label { font-size: 13px; color: var(--text-muted); margin-top: 8px; }
        .btn-download {
            display: block;
            width: 100%;
            background: linear-gradient(90deg, #6c5ce7, #00d2d3);
            color: #ffffff;
            font-size: 16px;
            font-weight: 600;
            padding: 15px;
            border-radius: 14px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(0, 210, 211, 0.35);
            transition: all 0.2s ease;
            margin-bottom: 16px;
        }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(0, 210, 211, 0.5); }
        .sources-title { font-size: 14px; color: var(--text-muted); text-align: left; margin-bottom: 10px; font-weight: 600; }
        .source-item {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            text-decoration: none;
            color: var(--text-main);
            transition: background 0.2s;
        }
        .source-item:hover { background: rgba(255, 255, 255, 0.08); }
        .source-badge {
            font-size: 11px;
            background: rgba(92, 124, 250, 0.2);
            color: #74c0fc;
            padding: 4px 10px;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="app-title"><?= $cleanTitle ?></h1>
        <p class="app-sub"><?= $cleanVersion ?> &bull; <?= $cleanSize ?></p>

        <?php if (!empty($cleanCode)): ?>
        <div class="downloader-banner">
            <div class="banner-label">⚡ Downloader Code</div>
            <div class="code-display"><?= $cleanCode ?></div>
            <div class="banner-help">Open the "Downloader" app (by AFTVnews) &rarr; type this code &rarr; Go</div>
        </div>
        <?php endif; ?>

        <div class="qr-section">
            <div class="qr-box">
                <img src="<?= $qrUrl ?>" alt="QR Code">
            </div>
            <div class="qr-label">Scan with your phone to download</div>
        </div>

        <a href="<?= $directUrl ?>" class="btn-download">&darr; Download APK Directly</a>

        <div class="sources-title">All sources</div>
        <a href="<?= $directUrl ?>" class="source-item">
            <span>&darr; Main server</span>
            <span class="source-badge">In-app download</span>
        </a>
        <?php if (!empty($version['mirror_web'])): ?>
        <a href="<?= htmlspecialchars($version['mirror_web']) ?>" target="_blank" class="source-item">
            <span>&#127760; Website</span>
            <span class="source-badge">Opens browser</span>
        </a>
        <?php endif; ?>
        <?php if (!empty($version['mirror_backup'])): ?>
        <a href="<?= htmlspecialchars($version['mirror_backup']) ?>" target="_blank" class="source-item">
            <span>&darr; Mirror 2</span>
            <span class="source-badge">In-app download</span>
        </a>
        <?php endif; ?>
    </div>
</body>
</html>
<?php

function streamApkFile($filePath, $filename) {
    if (!file_exists($filePath)) {
        http_response_code(404);
        die("File does not exist on server.");
    }

    $size = filesize($filePath);
    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        http_response_code(500);
        die("Could not open file.");
    }

    $start = 0;
    $end = $size - 1;

    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Pragma: public');

    // Handle Range Requests (multi-connection & pause/resume)
    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;

        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        if ($range === '-') {
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
        }

        $c_end = ($c_end > $end) ? $end : $c_end;
        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;
        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$size");
        header("Content-Length: $length");
    } else {
        header("Content-Length: $size");
    }

    while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
        if ($pos + 1048576 > $end) {
            echo fread($fp, $end - $pos + 1);
        } else {
            echo fread($fp, 1048576);
        }
        flush();
    }
    fclose($fp);
    exit;
}
