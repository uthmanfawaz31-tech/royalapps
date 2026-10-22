<?php
/**
 * Helper Utilities & Security Functions
 */

require_once __DIR__ . '/db.php';

function getSetting($key, $default = null) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (`key`, `value`) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}

function getAllSettings() {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
    return $settings;
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function generateDownloadToken($versionId, $expirySeconds = 3600) {
    $expiry = time() + $expirySeconds;
    $random = bin2hex(random_bytes(6));
    $payload = "$versionId|$expiry|$random";
    $signature = hash_hmac('sha256', $payload, APP_SECRET_KEY);
    $token = base64_encode("$payload|$signature");
    return str_replace(['+', '/', '='], ['-', '_', ''], $token);
}

function verifyDownloadToken($safeToken) {
    $b64 = str_replace(['-', '_'], ['+', '/'], $safeToken);
    $padLen = 4 - (strlen($b64) % 4);
    if ($padLen < 4) {
        $b64 .= str_repeat('=', $padLen);
    }
    $decoded = base64_decode($b64);
    if (!$decoded) return false;

    $parts = explode('|', $decoded);
    if (count($parts) !== 4) return false;

    list($versionId, $expiry, $random, $signature) = $parts;
    $payload = "$versionId|$expiry|$random";
    $expected = hash_hmac('sha256', $payload, APP_SECRET_KEY);

    if (!hash_equals($expected, $signature)) {
        return false;
    }
    if (time() > (int)$expiry) {
        return false;
    }
    return (int)$versionId;
}

function generateDownloaderCode() {
    return (string)mt_rand(1000000, 9999999);
}

function formatBytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function calculateFileSha256($filePath) {
    if (file_exists($filePath)) {
        return hash_file('sha256', $filePath);
    }
    return '';
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return trim(htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8'));
}
