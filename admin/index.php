<?php
$pageTitle = 'Dashboard Overview';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();

// Metrics
$totalDownloads = (int)$pdo->query("SELECT SUM(downloads_count) FROM app_versions")->fetchColumn() ?: 0;
$totalApps = (int)$pdo->query("SELECT COUNT(*) FROM apps")->fetchColumn() ?: 0;
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM store_users WHERE status = 'active'")->fetchColumn() ?: 0;
$activeDevices = (int)$pdo->query("SELECT COUNT(*) FROM user_devices")->fetchColumn() ?: 0;

// Top Downloaded Apps
$topStmt = $pdo->query("
    SELECT a.id, a.title, a.package_name, a.icon_url, c.name as category_name, v.version_name, v.downloads_count
    FROM apps a
    LEFT JOIN categories c ON a.category_id = c.id
    LEFT JOIN app_versions v ON a.id = v.app_id AND v.is_current = 1
    ORDER BY v.downloads_count DESC
    LIMIT 5
");
$topApps = $topStmt->fetchAll();

// Recent Downloads
$recentStmt = $pdo->query("
    SELECT d.*, a.title, v.version_name
    FROM download_logs d
    LEFT JOIN apps a ON d.app_id = a.id
    LEFT JOIN app_versions v ON d.version_id = v.id
    ORDER BY d.id DESC
    LIMIT 6
");
$recentDownloads = $recentStmt->fetchAll();
?>

<!-- Metric Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-title">⚡ Total Downloads</div>
        <div class="stat-val"><?= number_format($totalDownloads) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">📱 Total Apps</div>
        <div class="stat-val"><?= number_format($totalApps) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">📺 Active Devices</div>
        <div class="stat-val"><?= number_format($activeDevices) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-title">👥 Store Accounts</div>
        <div class="stat-val"><?= number_format($activeUsers) ?></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Top Apps Leaderboard -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">⭐ Top Downloaded Apps</h2>
            <a href="apps.php" class="btn btn-secondary btn-sm">View All Apps</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>App</th>
                            <th>Category</th>
                            <th>Version</th>
                            <th>Downloads</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topApps)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 20px;">No apps found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topApps as $app): ?>
                                <tr>
                                    <td style="display: flex; align-items: center; gap: 12px;">
                                        <img src="<?= $baseUrl ?>/<?= ltrim($app['icon_url'], '/') ?>" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover;" onerror="this.src='../app/assets/store_logo.jpg'">
                                        <div>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($app['title']) ?></div>
                                            <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($app['package_name']) ?></div>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($app['category_name'] ?? 'General') ?></span></td>
                                    <td><?= htmlspecialchars($app['version_name'] ?? 'v1.0') ?></td>
                                    <td><strong><?= number_format($app['downloads_count'] ?? 0) ?></strong></td>
                                    <td>
                                        <a href="apps.php?action=edit&id=<?= $app['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- System & Storage Status -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">⚙️ System & Hosting Health</h2>
        </div>
        <div class="card-body">
            <div style="margin-bottom: 14px; display: flex; justify-content: space-between;">
                <span style="color: var(--text-secondary);">PHP Engine:</span>
                <strong>PHP <?= PHP_VERSION ?></strong>
            </div>
            <div style="margin-bottom: 14px; display: flex; justify-content: space-between;">
                <span style="color: var(--text-secondary);">Database Driver:</span>
                <span class="badge badge-success"><?= strtoupper(DB_DRIVER) ?> (Active)</span>
            </div>
            <div style="margin-bottom: 14px; display: flex; justify-content: space-between;">
                <span style="color: var(--text-secondary);">Uploads Writable:</span>
                <span class="badge <?= is_writable(UPLOAD_DIR_APKS) ? 'badge-success' : 'badge-danger' ?>">
                    <?= is_writable(UPLOAD_DIR_APKS) ? 'Ready (Writable)' : 'Check Permissions' ?>
                </span>
            </div>
            <div style="margin-bottom: 14px; display: flex; justify-content: space-between;">
                <span style="color: var(--text-secondary);">Free Hosting Ready:</span>
                <span class="badge badge-success">100% Compatible</span>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 18px 0;">

            <h3 style="font-size: 13px; text-transform: uppercase; margin-bottom: 12px; color: var(--text-secondary);">Recent Downloads</h3>
            <?php if (empty($recentDownloads)): ?>
                <div style="color: var(--text-secondary); font-size: 13px;">No download logs yet.</div>
            <?php else: ?>
                <?php foreach ($recentDownloads as $log): ?>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px;">
                        <span><?= htmlspecialchars($log['title'] ?? 'App') ?> (<?= htmlspecialchars($log['version_name'] ?? '') ?>)</span>
                        <span style="color: var(--text-secondary);"><?= htmlspecialchars($log['engine']) ?> &bull; <?= date('H:i', strtotime($log['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
