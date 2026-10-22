<?php
/**
 * Real-time Download Tracker API
 * Called when an app is downloaded or installed from the TV store, Firestick, or web client.
 */

require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$pdo = getDbConnection();
$appId = (int)($_GET['app_id'] ?? $_POST['app_id'] ?? 0);
$engine = sanitizeInput($_GET['engine'] ?? $_POST['engine'] ?? 'turbo');
$deviceModel = sanitizeInput($_GET['device'] ?? $_POST['device'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Android TV'));
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if ($appId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid app ID']);
    exit;
}

try {
    // Fetch latest version of this app
    $stmt = $pdo->prepare("SELECT id, app_id, downloads_count FROM app_versions WHERE app_id = ? ORDER BY is_current DESC, id DESC LIMIT 1");
    $stmt->execute([$appId]);
    $version = $stmt->fetch();

    if ($version) {
        $versionId = (int)$version['id'];
        // Increment count
        $pdo->prepare("UPDATE app_versions SET downloads_count = downloads_count + 1 WHERE id = ?")->execute([$versionId]);
        
        // Log to download_logs
        $pdo->prepare("INSERT INTO download_logs (version_id, app_id, ip_address, engine, created_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$versionId, $appId, $ip, $engine, date('Y-m-d H:i:s')]);

        // Register device if not exists
        $deviceId = md5($ip . $deviceModel);
        $dStmt = $pdo->prepare("SELECT id FROM user_devices WHERE device_id = ?");
        $dStmt->execute([$deviceId]);
        if (!$dStmt->fetch()) {
            $pdo->prepare("INSERT INTO user_devices (user_id, device_id, device_model, ip_address, last_seen) VALUES (?, ?, ?, ?, ?)")
                ->execute([1, $deviceId, substr($deviceModel, 0, 95), $ip, date('Y-m-d H:i:s')]);
        }

        $newCount = (int)$version['downloads_count'] + 1;

        echo json_encode([
            'success' => true,
            'app_id' => $appId,
            'new_downloads_count' => $newCount,
            'message' => 'Download logged successfully'
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'App version not found']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
