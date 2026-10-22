<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdminLogin();

$pdo = getDbConnection();
$feedback = '';
$baseUrl = getBaseUrl();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = sanitizeInput($_POST['store_name'] ?? 'Royal Media');
    $storeSubtitle = sanitizeInput($_POST['store_subtitle'] ?? 'All your Royal streaming apps in one place');
    $accentColor = sanitizeInput($_POST['theme_accent_color'] ?? '#f59e0b');
    $glowColor = sanitizeInput($_POST['theme_glow_color'] ?? '#00d2d3');

    setSetting('store_name', $storeName);
    setSetting('store_subtitle', $storeSubtitle);
    setSetting('theme_accent_color', $accentColor);
    setSetting('theme_glow_color', $glowColor);

    // Handle Logo Upload
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
        $dest = UPLOAD_DIR_ICONS . '/store_logo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
            setSetting('store_logo', 'uploads/icons/' . basename($dest));
        }
    }

    // Handle TV Banner Upload
    if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION);
        $dest = UPLOAD_DIR_BANNERS . '/store_banner_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['banner_file']['tmp_name'], $dest)) {
            setSetting('store_banner', 'uploads/banners/' . basename($dest));
        }
    }

    $feedback = 'Branding & Store Theme updated successfully!';
}

$pageTitle = 'Brand, Logo & White-Label Settings';
require_once __DIR__ . '/header.php';

$storeName = getSetting('store_name', 'Royal Media');
$storeSubtitle = getSetting('store_subtitle', 'All your Royal streaming apps in one place');
$storeLogo = getSetting('store_logo', 'uploads/icons/store_logo.jpg');
$storeBanner = getSetting('store_banner', '');
$accentColor = getSetting('theme_accent_color', '#f59e0b');
$glowColor = getSetting('theme_glow_color', '#00d2d3');

// Encrypted Panel Link for White-Label APK
$rawPanelUrl = $baseUrl . '/api';
$encryptedPanelLink = base64_encode(openssl_encrypt($rawPanelUrl, 'AES-128-ECB', substr(APP_SECRET_KEY, 0, 16)));
?>

<?php if ($feedback): ?>
    <div style="background: rgba(64, 192, 87, 0.15); border: 1px solid rgba(64, 192, 87, 0.3); color: #51cf66; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;">
        <?= htmlspecialchars($feedback) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Branding Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🎨 Brand & Theme Customization</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="branding.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Store Brand Name *</label>
                    <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($storeName) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Store Subtitle / Tagline</label>
                    <input type="text" name="store_subtitle" class="form-control" value="<?= htmlspecialchars($storeSubtitle) ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Brand Primary Color</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="color" name="theme_accent_color" value="<?= htmlspecialchars($accentColor) ?>" style="width: 48px; height: 38px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($accentColor) ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Neon Glow Accent Color</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="color" name="theme_glow_color" value="<?= htmlspecialchars($glowColor) ?>" style="width: 48px; height: 38px; border: none; border-radius: 8px; cursor: pointer;">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($glowColor) ?>" readonly>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px;">
                    <div class="form-group">
                        <label class="form-label">Store Logo (Squircle Icon)</label>
                        <div style="margin-bottom: 8px;">
                            <img src="<?= $baseUrl ?>/<?= ltrim($storeLogo, '/') ?>" style="width: 60px; height: 60px; border-radius: 14px; object-fit: cover;" onerror="this.src='../app/assets/store_logo.jpg'">
                        </div>
                        <input type="file" name="logo_file" accept="image/*" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">TV Banner Image (Optional)</label>
                        <?php if (!empty($storeBanner)): ?>
                            <div style="margin-bottom: 8px;">
                                <img src="<?= $baseUrl ?>/<?= ltrim($storeBanner, '/') ?>" style="height: 60px; border-radius: 10px; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_file" accept="image/*" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 14px; padding: 12px 28px;">💾 Save Brand Settings</button>
            </form>
        </div>
    </div>

    <!-- White-Label & Encryption Info -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🏷️ White-Label Ready</h2>
        </div>
        <div class="card-body">
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                You can easily rebrand the Android app without touching Java source code. Use <strong>MT Manager</strong> or <strong>APKTool</strong> to replace the store name, logo, and embed your encrypted panel URL.
            </p>

            <div class="form-group">
                <label class="form-label">Your Store API Endpoint</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($rawPanelUrl) ?>" readonly onclick="this.select()">
            </div>

            <div class="form-group">
                <label class="form-label">🔒 Encrypted Panel Token (for APK)</label>
                <textarea class="form-control" style="font-family: monospace; font-size: 11px; min-height: 70px;" readonly onclick="this.select()"><?= htmlspecialchars($encryptedPanelLink) ?></textarea>
                <small style="color: var(--text-secondary); font-size: 11px; margin-top: 4px; display: block;">Paste this token in your APK's `strings.xml` or smali config to keep your server hidden.</small>
            </div>

            <div style="margin-top: 18px; padding: 14px; background: rgba(92, 124, 250, 0.08); border-radius: 12px; border: 1px solid rgba(92, 124, 250, 0.2);">
                <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">📖 White Label Guide</div>
                <div style="font-size: 12px; color: var(--text-secondary);">Check <code>WHITE_LABEL_GUIDE.md</code> in your project root for full MT Manager & APKTool step-by-step instructions.</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
