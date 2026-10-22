<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ApkParser.php';
requireAdminLogin();

$pdo = getDbConnection();
$baseUrl = getBaseUrl();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Handle AJAX APK Parsing
if ($action === 'parse_apk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['apk_file']) || $_FILES['apk_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'error' => 'File upload failed.']);
    }

    $tmpPath = $_FILES['apk_file']['tmp_name'];
    $origName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['apk_file']['name']);
    $destFile = UPLOAD_DIR_APKS . '/' . time() . '_' . $origName;

    if (!move_uploaded_file($tmpPath, $destFile)) {
        jsonResponse(['success' => false, 'error' => 'Could not save APK to uploads directory.']);
    }

    $parser = new ApkParser($destFile);
    $result = $parser->parse();
    $result['relative_apk_path'] = 'uploads/apks/' . basename($destFile);
    $result['downloader_code'] = generateDownloaderCode();

    jsonResponse($result);
}

// Handle Delete App
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM apps WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM app_versions WHERE app_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM app_screenshots WHERE app_id = ?")->execute([$id]);
    }
    header('Location: apps.php?msg=deleted');
    exit;
}

// Handle Toggle Featured
if ($action === 'toggle_featured') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE apps SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
    }
    header('Location: apps.php');
    exit;
}

// Handle Save App (Insert or Update)
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['app_id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 1);
    $title = sanitizeInput($_POST['title'] ?? '');
    $packageName = sanitizeInput($_POST['package_name'] ?? '');
    $developer = sanitizeInput($_POST['developer'] ?? '');
    $shortDesc = sanitizeInput($_POST['short_desc'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $changelog = sanitizeInput($_POST['changelog'] ?? '');
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    $versionName = sanitizeInput($_POST['version_name'] ?? 'v1.0');
    $versionCode = (int)($_POST['version_code'] ?? 1);
    $downloaderCode = sanitizeInput($_POST['downloader_code'] ?? generateDownloaderCode());
    $apkPath = sanitizeInput($_POST['apk_path'] ?? '');
    $mirrorMain = sanitizeInput($_POST['mirror_main'] ?? '');
    $mirrorWeb = sanitizeInput($_POST['mirror_web'] ?? '');
    $mirrorBackup = sanitizeInput($_POST['mirror_backup'] ?? '');

    $iconUrl = sanitizeInput($_POST['existing_icon'] ?? '');
    if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['icon_file']['name'], PATHINFO_EXTENSION);
        $iconDest = UPLOAD_DIR_ICONS . '/icon_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['icon_file']['tmp_name'], $iconDest)) {
            $iconUrl = 'uploads/icons/' . basename($iconDest);
        }
    }

    $bannerUrl = sanitizeInput($_POST['existing_banner'] ?? '');
    if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION);
        $bannerDest = UPLOAD_DIR_BANNERS . '/banner_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $bannerDest)) {
            $bannerUrl = 'uploads/banners/' . basename($bannerDest);
        }
    }

    $now = date('Y-m-d H:i:s');

    if ($id > 0) {
        // Update App
        $stmt = $pdo->prepare("UPDATE apps SET category_id = ?, title = ?, package_name = ?, developer = ?, short_desc = ?, description = ?, changelog = ?, icon_url = ?, banner_url = ?, is_featured = ? WHERE id = ?");
        $stmt->execute([$categoryId, $title, $packageName, $developer, $shortDesc, $description, $changelog, $iconUrl, $bannerUrl, $isFeatured, $id]);

        // Update Current Version
        $vStmt = $pdo->prepare("SELECT id FROM app_versions WHERE app_id = ? AND is_current = 1 LIMIT 1");
        $vStmt->execute([$id]);
        $vRow = $vStmt->fetch();

        $fileSize = 0;
        $sha256 = '';
        if ($apkPath && file_exists(APP_ROOT . '/' . ltrim($apkPath, '/'))) {
            $fileSize = filesize(APP_ROOT . '/' . ltrim($apkPath, '/'));
            $sha256 = hash_file('sha256', APP_ROOT . '/' . ltrim($apkPath, '/'));
        }

        if ($vRow) {
            $upSql = "UPDATE app_versions SET version_name = ?, version_code = ?, downloader_code = ?, mirror_main = ?, mirror_web = ?, mirror_backup = ?";
            $params = [$versionName, $versionCode, $downloaderCode, $mirrorMain, $mirrorWeb, $mirrorBackup];
            if ($apkPath) {
                $upSql .= ", file_path = ?, file_size = ?, sha256_hash = ?";
                $params[] = $apkPath;
                $params[] = $fileSize;
                $params[] = $sha256;
            }
            $upSql .= " WHERE id = ?";
            $params[] = $vRow['id'];
            $pdo->prepare($upSql)->execute($params);
        }
    } else {
        // Insert New App
        $stmt = $pdo->prepare("INSERT INTO apps (category_id, title, package_name, developer, short_desc, description, changelog, icon_url, banner_url, is_featured, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$categoryId, $title, $packageName, $developer, $shortDesc, $description, $changelog, $iconUrl, $bannerUrl, $isFeatured, $now]);
        $id = $pdo->lastInsertId();

        $fileSize = 0;
        $sha256 = '';
        if ($apkPath && file_exists(APP_ROOT . '/' . ltrim($apkPath, '/'))) {
            $fileSize = filesize(APP_ROOT . '/' . ltrim($apkPath, '/'));
            $sha256 = hash_file('sha256', APP_ROOT . '/' . ltrim($apkPath, '/'));
        }

        $pdo->prepare("INSERT INTO app_versions (app_id, version_name, version_code, file_path, file_size, sha256_hash, downloader_code, mirror_main, mirror_web, mirror_backup, is_current, downloads_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$id, $versionName, $versionCode, $apkPath, $fileSize, $sha256, $downloaderCode, $mirrorMain, $mirrorWeb, $mirrorBackup, 1, 0, $now]);
    }

    // Handle Screenshot Uploads
    if (isset($_FILES['screenshot_files'])) {
        foreach ($_FILES['screenshot_files']['tmp_name'] as $idx => $tmp) {
            if ($_FILES['screenshot_files']['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['screenshot_files']['name'][$idx], PATHINFO_EXTENSION);
                $sDest = UPLOAD_DIR_SCREENSHOTS . '/screen_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmp, $sDest)) {
                    $pdo->prepare("INSERT INTO app_screenshots (app_id, image_url, sort_order) VALUES (?, ?, ?)")
                        ->execute([$id, 'uploads/screenshots/' . basename($sDest), $idx]);
                }
            }
        }
    }

    header('Location: apps.php?msg=saved');
    exit;
}

$pageTitle = ($action === 'add' || $action === 'edit') ? ($action === 'add' ? 'Upload & Add New App' : 'Edit Application') : 'Manage Apps & APKs';
require_once __DIR__ . '/header.php';
?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <?php
    $app = null;
    $version = null;
    if ($action === 'edit') {
        $editId = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
        $stmt->execute([$editId]);
        $app = $stmt->fetch();

        $vStmt = $pdo->prepare("SELECT * FROM app_versions WHERE app_id = ? AND is_current = 1 LIMIT 1");
        $vStmt->execute([$editId]);
        $version = $vStmt->fetch();
    }
    $categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll();
    ?>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'add' ? '📤 Drag & Drop APK or Add App' : '✏️ Edit App: ' . htmlspecialchars($app['title'] ?? '') ?></h2>
            <a href="apps.php" class="btn btn-secondary btn-sm">&larr; Back to Apps</a>
        </div>
        <div class="card-body">
            <!-- Drag & Drop Zone -->
            <div id="apk-dropzone" class="dropzone-box">
                <input type="file" id="apk-file-input" style="display: none;" accept=".apk">
                <div class="dropzone-icon">📥</div>
                <div class="dropzone-text">Drag & drop your Android APK file here, or click to browse</div>
                <div class="dropzone-sub">APK metadata, package name, version, and icon will be extracted automatically!</div>
            </div>

            <form method="POST" action="apps.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="app_id" value="<?= $app['id'] ?? 0 ?>">
                <input type="hidden" name="apk_path" id="field-apk-path" value="<?= htmlspecialchars($version['file_path'] ?? '') ?>">
                <input type="hidden" name="existing_icon" id="field-existing-icon" value="<?= htmlspecialchars($app['icon_url'] ?? '') ?>">
                <input type="hidden" name="existing_banner" value="<?= htmlspecialchars($app['banner_url'] ?? '') ?>">

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <div>
                        <div class="form-group">
                            <label class="form-label">App Title *</label>
                            <input type="text" name="title" id="field-title" class="form-control" value="<?= htmlspecialchars($app['title'] ?? '') ?>" placeholder="e.g. Wave IPTV" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                            <div class="form-group">
                                <label class="form-label">Package Name (Android ID) *</label>
                                <input type="text" name="package_name" id="field-package" class="form-control" value="<?= htmlspecialchars($app['package_name'] ?? '') ?>" placeholder="e.g. com.waveiptv.player" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Developer / Subtitle</label>
                                <input type="text" name="developer" id="field-dev" class="form-control" value="<?= htmlspecialchars($app['developer'] ?? '') ?>" placeholder="e.g. Wave IPTV">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                            <div class="form-group">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-control" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= (isset($app['category_id']) && $app['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Version Name</label>
                                <input type="text" name="version_name" id="field-version-name" class="form-control" value="<?= htmlspecialchars($version['version_name'] ?? 'v1.0') ?>" placeholder="v2.0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Version Code</label>
                                <input type="number" name="version_code" id="field-version-code" class="form-control" value="<?= htmlspecialchars($version['version_code'] ?? '1') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Short Description</label>
                            <input type="text" name="short_desc" class="form-control" value="<?= htmlspecialchars($app['short_desc'] ?? '') ?>" placeholder="Brief 1-sentence summary">
                        </div>

                        <div class="form-group">
                            <label class="form-label">About App (Full Description)</label>
                            <textarea name="description" class="form-control" placeholder="Full details for the TV app details sheet..."><?= htmlspecialchars($app['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">What's New (Changelog Bullets)</label>
                            <textarea name="changelog" class="form-control" placeholder="🎥 New UI design&#10;📺 Added multiview&#10;⚡ Performance boost"><?= htmlspecialchars($app['changelog'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Side Options -->
                    <div>
                        <div class="card" style="background: rgba(255,255,255,0.02); margin-bottom: 20px;">
                            <div class="card-header"><h3 class="card-title">⚡ Downloader & Mirrors</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Downloader Code (AFTVnews)</label>
                                    <input type="text" name="downloader_code" id="field-downloader-code" class="form-control" value="<?= htmlspecialchars($version['downloader_code'] ?? generateDownloaderCode()) ?>" placeholder="e.g. 5413872">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Main Download Mirror</label>
                                    <input type="text" name="mirror_main" class="form-control" value="<?= htmlspecialchars($version['mirror_main'] ?? '') ?>" placeholder="Direct APK URL or auto-served">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Website Mirror (Opens Browser)</label>
                                    <input type="text" name="mirror_web" class="form-control" value="<?= htmlspecialchars($version['mirror_web'] ?? '') ?>" placeholder="https://yourwebsite.com/download">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Backup Mirror 2</label>
                                    <input type="text" name="mirror_backup" class="form-control" value="<?= htmlspecialchars($version['mirror_backup'] ?? '') ?>" placeholder="http://aftv.news/...">
                                </div>
                            </div>
                        </div>

                        <div class="card" style="background: rgba(255,255,255,0.02); margin-bottom: 20px; border: 1px solid rgba(245, 158, 11, 0.25);">
                            <div class="card-header" style="background: rgba(245, 158, 11, 0.08);"><h3 class="card-title" style="color: #fbbf24;">🎨 App Icon & TV Artwork</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 700;">App Icon (Required Format: 1:1 Square)</label>
                                    
                                    <!-- Specification Guide Card -->
                                    <div style="background: rgba(0,0,0,0.35); border: 1px dashed rgba(245, 158, 11, 0.4); border-radius: 12px; padding: 12px 14px; margin-bottom: 14px;">
                                        <div style="font-size: 12px; font-weight: 700; color: #fbbf24; margin-bottom: 4px;">📐 Icon Dimensions & Format Needed:</div>
                                        <ul style="font-size: 11px; color: var(--text-secondary); margin-left: 18px; line-height: 1.6;">
                                            <li><strong>Optimal Resolution:</strong> <span style="color:#fff;">512 × 512 px</span> (1:1 square ratio)</li>
                                            <li><strong>Minimum Resolution:</strong> 256 × 256 px &bull; <strong>Max:</strong> 1024 × 1024 px</li>
                                            <li><strong>Format:</strong> PNG (recommended with transparent or dark background) or JPG</li>
                                            <li><strong>TV Display:</strong> Scaled automatically to matching Royal Media squircle cards with gold focus glow.</li>
                                        </ul>
                                    </div>

                                    <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 12px;">
                                        <div style="position: relative; flex-shrink: 0;">
                                            <img id="icon-preview" src="<?= !empty($app['icon_url']) ? (preg_match('#^(https?://|data:)#i', $app['icon_url']) ? $app['icon_url'] : $baseUrl . '/' . ltrim($app['icon_url'], '/')) : '../app/assets/store_logo.jpg' ?>" style="width: 76px; height: 76px; border-radius: 18px; object-fit: cover; border: 2px solid #f59e0b; box-shadow: 0 8px 20px rgba(0,0,0,0.6); background: #0b0e14;">
                                        </div>
                                        <div style="flex-grow: 1;">
                                            <input type="file" name="icon_file" id="icon-file-input" accept="image/png, image/jpeg, image/webp" class="form-control" onchange="handleAppIconSelect(this)">
                                            <div id="icon-dimension-badge" style="margin-top: 6px; font-size: 11px; font-weight: 600; color: #94a3b8;">
                                                Store standard: 512 × 512 px (1:1)
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">TV Hero Banner (Wide Artwork)</label>
                                    <small style="color: var(--text-secondary); display: block; margin-bottom: 6px; font-size: 11px;">Recommended: 1920 × 1080 px or 1280 × 720 px (16:9 Landscape for TV carousel).</small>
                                    <input type="file" name="banner_file" accept="image/*" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Upload Screenshots (Multiple)</label>
                                    <input type="file" name="screenshot_files[]" accept="image/*" multiple class="form-control">
                                </div>

                                <div class="form-group" style="margin-top: 10px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="is_featured" value="1" <?= (!empty($app['is_featured'])) ? 'checked' : '' ?>>
                                        <span style="font-weight: 600; color: var(--accent-glow);">⭐ Show in "New arrivals" Hero Carousel</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 15px;">💾 Save Application</button>
                    <a href="apps.php" class="btn btn-secondary" style="padding: 12px 20px;">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initApkDropzone('apk-dropzone', 'apk-file-input', (res) => {
                if (res.app_name && !document.getElementById('field-title').value) {
                    document.getElementById('field-title').value = res.app_name;
                }
                if (res.package_name) {
                    document.getElementById('field-package').value = res.package_name;
                }
                if (res.version_name) {
                    document.getElementById('field-version-name').value = res.version_name;
                }
                if (res.version_code) {
                    document.getElementById('field-version-code').value = res.version_code;
                }
                if (res.relative_apk_path) {
                    document.getElementById('field-apk-path').value = res.relative_apk_path;
                }
                if (res.downloader_code) {
                    document.getElementById('field-downloader-code').value = res.downloader_code;
                }
                if (res.icon_path) {
                    document.getElementById('field-existing-icon').value = res.icon_path;
                    document.getElementById('icon-preview').src = '<?= $baseUrl ?>/' + res.icon_path;
                }
            });
        });

        function previewImg(input, targetId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById(targetId).src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }

        function handleAppIconSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                const badge = document.getElementById('icon-dimension-badge');
                
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        const w = img.naturalWidth;
                        const h = img.naturalHeight;
                        const isSquare = (w === h);
                        const isOptimal = (w >= 480 && w <= 540 && h >= 480 && h <= 540);
                        
                        document.getElementById('icon-preview').src = e.target.result;
                        
                        if (badge) {
                            if (isOptimal) {
                                badge.innerHTML = `<span style="color: #10b981; font-weight:700;">✓ Perfect Store Format: ${w} × ${h} px (1:1 Square)</span>`;
                            } else if (isSquare) {
                                badge.innerHTML = `<span style="color: #fbbf24; font-weight:700;">✓ Valid 1:1 Square: ${w} × ${h} px</span>`;
                            } else {
                                badge.innerHTML = `<span style="color: #f59e0b; font-weight:700;">⚠ Detected: ${w} × ${h} px (Non-square. Fits into squircle automatically)</span>`;
                            }
                        }
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
    </script>

<?php else: ?>
    <!-- Apps Catalog Table View -->
    <?php
    $appsStmt = $pdo->query("
        SELECT a.*, c.name as category_name, v.version_name, v.downloader_code, v.file_size, v.downloads_count
        FROM apps a
        LEFT JOIN categories c ON a.category_id = c.id
        LEFT JOIN app_versions v ON a.id = v.app_id AND v.is_current = 1
        ORDER BY a.sort_order ASC, a.id DESC
    ");
    $allApps = $appsStmt->fetchAll();
    ?>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📱 Installed Store Apps (<?= count($allApps) ?>)</h2>
            <a href="apps.php?action=add" class="btn btn-primary">+ Upload New APK</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Icon & App</th>
                            <th>Category</th>
                            <th>Version</th>
                            <th>Downloader Code</th>
                            <th>Downloads</th>
                            <th>Hero Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allApps)): ?>
                            <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-secondary);">No apps added yet. Click "+ Upload New APK" to get started.</td></tr>
                        <?php else: ?>
                            <?php foreach ($allApps as $app): ?>
                                <tr>
                                    <td style="display: flex; align-items: center; gap: 12px;">
                                        <img src="<?= $baseUrl ?>/<?= ltrim($app['icon_url'], '/') ?>" style="width: 42px; height: 42px; border-radius: 10px; object-fit: cover;" onerror="this.src='../app/assets/store_logo.jpg'">
                                        <div>
                                            <div style="font-weight: 600; font-size: 15px;"><?= htmlspecialchars($app['title']) ?></div>
                                            <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($app['package_name']) ?></div>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($app['category_name'] ?? 'General') ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($app['version_name'] ?? 'v1.0') ?></strong>
                                        <div style="font-size: 11px; color: var(--text-secondary);"><?= formatBytes($app['file_size'] ?? 0) ?></div>
                                    </td>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 700; color: var(--accent-glow);"><?= htmlspecialchars($app['downloader_code'] ?? '—') ?></span>
                                    </td>
                                    <td><strong><?= number_format($app['downloads_count'] ?? 0) ?></strong></td>
                                    <td>
                                        <a href="apps.php?action=toggle_featured&id=<?= $app['id'] ?>" class="badge <?= $app['is_featured'] ? 'badge-success' : 'badge-secondary' ?>" style="text-decoration: none; cursor: pointer;">
                                            <?= $app['is_featured'] ? '⭐ Featured' : 'Normal' ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="../dl.php?app=<?= $app['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="View download page">🔗 DL</a>
                                            <a href="apps.php?action=edit&id=<?= $app['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                            <a href="apps.php?action=delete&id=<?= $app['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this app?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
