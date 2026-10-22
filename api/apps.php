<?php
/**
 * Apps & Catalog REST API
 */

require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDbConnection();
$baseUrl = getBaseUrl();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$appId = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;

// Helper to format app record
function formatAppRow($app, $pdo, $baseUrl) {
    // Current Version
    $vStmt = $pdo->prepare("SELECT * FROM app_versions WHERE app_id = ? AND is_current = 1 ORDER BY id DESC LIMIT 1");
    $vStmt->execute([$app['id']]);
    $version = $vStmt->fetch();

    if (!$version) {
        // Fallback to latest
        $vStmt = $pdo->prepare("SELECT * FROM app_versions WHERE app_id = ? ORDER BY id DESC LIMIT 1");
        $vStmt->execute([$app['id']]);
        $version = $vStmt->fetch();
    }

    // Screenshots
    $sStmt = $pdo->prepare("SELECT image_url FROM app_screenshots WHERE app_id = ? ORDER BY sort_order ASC");
    $sStmt->execute([$app['id']]);
    $screenshots = [];
    while ($sRow = $sStmt->fetch()) {
        $screenshots[] = $baseUrl . '/' . ltrim($sRow['image_url'], '/');
    }

    // Default screenshot placeholders if none uploaded
    if (empty($screenshots)) {
        $screenshots = [
            $baseUrl . '/app/assets/screenshot_preview1.jpg',
            $baseUrl . '/app/assets/screenshot_preview2.jpg',
            $baseUrl . '/app/assets/screenshot_preview3.jpg'
        ];
    }

    $iconUrl = !empty($app['icon_url']) ? (preg_match('#^(https?://|data:)#i', $app['icon_url']) ? $app['icon_url'] : $baseUrl . '/' . ltrim($app['icon_url'], '/')) : $baseUrl . '/app/assets/store_logo.jpg';
    $bannerUrl = !empty($app['banner_url']) ? (preg_match('#^(https?://|data:)#i', $app['banner_url']) ? $app['banner_url'] : $baseUrl . '/' . ltrim($app['banner_url'], '/')) : '';

    $versionId = $version ? (int)$version['id'] : 0;
    $downloadToken = $versionId ? generateDownloadToken($versionId) : '';
    $directDownloadUrl = $versionId ? $baseUrl . '/dl.php?t=' . $downloadToken : '';

    $downloaderCode = $version['downloader_code'] ?? '5413872';

    return [
        'id' => (int)$app['id'],
        'category_id' => (int)$app['category_id'],
        'category_name' => $app['category_name'] ?? 'Best of week',
        'title' => $app['title'],
        'package_name' => $app['package_name'],
        'developer' => $app['developer'] ?: $app['title'],
        'short_desc' => $app['short_desc'] ?: '',
        'description' => $app['description'] ?: '',
        'changelog' => $app['changelog'] ?: '',
        'icon_url' => $iconUrl,
        'banner_url' => $bannerUrl,
        'is_featured' => (bool)$app['is_featured'],
        'version' => [
            'id' => $versionId,
            'version_name' => $version['version_name'] ?? 'v1.0',
            'version_code' => (int)($version['version_code'] ?? 1),
            'file_size' => (int)($version['file_size'] ?? 0),
            'file_size_formatted' => formatBytes($version['file_size'] ?? 0),
            'sha256_hash' => $version['sha256_hash'] ?? '',
            'downloader_code' => $downloaderCode,
            'downloads_count' => (int)($version['downloads_count'] ?? 0),
            'download_url' => $directDownloadUrl,
            'mirrors' => [
                [
                    'name' => 'Main server',
                    'type' => 'in_app',
                    'url' => $directDownloadUrl,
                    'badge' => 'In-app download'
                ],
                [
                    'name' => 'Website',
                    'type' => 'browser',
                    'url' => !empty($version['mirror_web']) ? $version['mirror_web'] : $baseUrl . '/dl.php?app=' . $app['id'],
                    'badge' => 'Opens browser'
                ],
                [
                    'name' => 'Mirror 2',
                    'type' => 'in_app',
                    'url' => !empty($version['mirror_backup']) ? $version['mirror_backup'] : 'http://aftv.news/' . $downloaderCode,
                    'badge' => 'In-app download'
                ]
            ]
        ],
        'screenshots' => $screenshots
    ];
}

if ($appId > 0) {
    $stmt = $pdo->prepare("SELECT a.*, c.name as category_name FROM apps a LEFT JOIN categories c ON a.category_id = c.id WHERE a.id = ?");
    $stmt->execute([$appId]);
    $app = $stmt->fetch();
    if (!$app) {
        jsonResponse(['success' => false, 'message' => 'App not found'], 404);
    }
    jsonResponse(['success' => true, 'app' => formatAppRow($app, $pdo, $baseUrl)]);
}

// Fetch Categories
$catStmt = $pdo->query("SELECT id, name, slug, icon, sort_order FROM categories ORDER BY sort_order ASC");
$categories = $catStmt->fetchAll();

// Build Apps Query
$sql = "SELECT a.*, c.name as category_name FROM apps a LEFT JOIN categories c ON a.category_id = c.id WHERE 1=1";
$params = [];

if ($categoryId > 0) {
    $sql .= " AND a.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($search)) {
    $sql .= " AND (a.title LIKE ? OR a.package_name LIKE ? OR a.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY a.sort_order ASC, a.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawApps = $stmt->fetchAll();

$apps = [];
$featured = [];

foreach ($rawApps as $raw) {
    $formatted = formatAppRow($raw, $pdo, $baseUrl);
    $apps[] = $formatted;
    if ($formatted['is_featured']) {
        $featured[] = $formatted;
    }
}

// If no apps marked featured, take the newest 3
if (empty($featured) && !empty($apps)) {
    $featured = array_slice($apps, 0, 3);
}

jsonResponse([
    'success' => true,
    'categories' => $categories,
    'featured' => $featured,
    'apps' => $apps
]);
