<?php
/**
 * Update Alerts API
 * Compares client installed versions against store catalog
 */

require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDbConnection();
$baseUrl = getBaseUrl();

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$installed = $input['installed'] ?? [];

$updates = [];

if (is_array($installed) && !empty($installed)) {
    foreach ($installed as $item) {
        $package = trim($item['package'] ?? '');
        $clientVersionCode = (int)($item['version_code'] ?? 0);

        if (empty($package)) continue;

        $stmt = $pdo->prepare("
            SELECT a.id, a.title, a.package_name, a.icon_url, a.changelog,
                   v.id as version_id, v.version_name, v.version_code, v.file_size, v.sha256_hash
            FROM apps a
            JOIN app_versions v ON a.id = v.app_id AND v.is_current = 1
            WHERE a.package_name = ? AND v.version_code > ?
            LIMIT 1
        ");
        $stmt->execute([$package, $clientVersionCode]);
        $update = $stmt->fetch();

        if ($update) {
            $token = generateDownloadToken((int)$update['version_id']);
            $updates[] = [
                'app_id' => (int)$update['id'],
                'package_name' => $update['package_name'],
                'title' => $update['title'],
                'icon_url' => !empty($update['icon_url']) ? $baseUrl . '/' . ltrim($update['icon_url'], '/') : '',
                'latest_version' => $update['version_name'],
                'latest_code' => (int)$update['version_code'],
                'file_size' => (int)$update['file_size'],
                'file_size_formatted' => formatBytes($update['file_size']),
                'sha256_hash' => $update['sha256_hash'],
                'changelog' => $update['changelog'],
                'download_url' => $baseUrl . '/dl.php?t=' . $token
            ];
        }
    }
}

jsonResponse([
    'success' => true,
    'has_updates' => !empty($updates),
    'updates_count' => count($updates),
    'updates' => $updates
]);
