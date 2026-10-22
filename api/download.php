<?php
/**
 * Protected Download Token Generator API
 */

require_once __DIR__ . '/../includes/helpers.php';

$versionId = (int)($_GET['version_id'] ?? $_POST['version_id'] ?? 0);
$engine = sanitizeInput($_GET['engine'] ?? $_POST['engine'] ?? 'turbo');

if ($versionId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid version ID'], 400);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT v.*, a.title FROM app_versions v JOIN apps a ON v.app_id = a.id WHERE v.id = ?");
$stmt->execute([$versionId]);
$version = $stmt->fetch();

if (!$version) {
    jsonResponse(['success' => false, 'message' => 'App version not found'], 404);
}

// Log download
$logStmt = $pdo->prepare("INSERT INTO download_logs (version_id, app_id, ip_address, engine, created_at) VALUES (?, ?, ?, ?, ?)");
$logStmt->execute([$versionId, $version['app_id'], $_SERVER['REMOTE_ADDR'] ?? '', $engine, date('Y-m-d H:i:s')]);

// Increment download count
$pdo->prepare("UPDATE app_versions SET downloads_count = downloads_count + 1 WHERE id = ?")->execute([$versionId]);

// Generate signed token valid for 2 hours
$token = generateDownloadToken($versionId, 7200);
$baseUrl = getBaseUrl();

jsonResponse([
    'success' => true,
    'token' => $token,
    'download_url' => $baseUrl . '/dl.php?t=' . $token,
    'sha256' => $version['sha256_hash'],
    'file_size' => (int)$version['file_size'],
    'engine' => $engine
]);
