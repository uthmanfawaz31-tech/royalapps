<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdminLogin();

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$feedback = '';

// Handle OneSignal Push Dispatch
function sendOneSignalPush($title, $message, $appId = null) {
    $oneSignalAppId = getSetting('onesignal_app_id', '');
    $oneSignalApiKey = getSetting('onesignal_api_key', '');

    if (empty($oneSignalAppId) || empty($oneSignalApiKey)) {
        return false;
    }

    $content = [
        "en" => $message
    ];
    $headings = [
        "en" => $title
    ];

    $fields = [
        'app_id' => $oneSignalAppId,
        'included_segments' => ['All'],
        'headings' => $headings,
        'contents' => $content,
        'data' => ['app_id' => $appId]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $oneSignalApiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $response = curl_exec($ch);
    curl_close($ch);
    return true;
}

// Handle Save Notice
if ($action === 'save_notice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    $appId = (int)($_POST['app_id'] ?? 0);
    $sendPush = isset($_POST['send_push']);

    if (!empty($title) && !empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO notices (title, content, app_id, is_active, created_at) VALUES (?, ?, ?, 1, ?)");
        $stmt->execute([$title, $content, $appId > 0 ? $appId : null, date('Y-m-d H:i:s')]);

        if ($sendPush) {
            sendOneSignalPush($title, substr($content, 0, 100), $appId);
            $feedback = 'Notice published and push notification sent!';
        } else {
            $feedback = 'Notice published successfully.';
        }
    }
}

// Handle Save OneSignal Settings
if ($action === 'save_onesignal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    setSetting('onesignal_app_id', sanitizeInput($_POST['onesignal_app_id'] ?? ''));
    setSetting('onesignal_api_key', sanitizeInput($_POST['onesignal_api_key'] ?? ''));
    $feedback = 'OneSignal settings updated.';
}

// Handle Delete Notice
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM notices WHERE id = ?")->execute([$id]);
    }
    header('Location: notices.php');
    exit;
}

$pageTitle = 'Notices & OneSignal Push Notifications';
require_once __DIR__ . '/header.php';

$notices = $pdo->query("
    SELECT n.*, a.title as app_title
    FROM notices n
    LEFT JOIN apps a ON n.app_id = a.id
    ORDER BY n.id DESC
")->fetchAll();

$apps = $pdo->query("SELECT id, title FROM apps ORDER BY title ASC")->fetchAll();
$appIdSetting = getSetting('onesignal_app_id', '');
$apiKeySetting = getSetting('onesignal_api_key', '');
?>

<?php if ($feedback): ?>
    <div style="background: rgba(64, 192, 87, 0.15); border: 1px solid rgba(64, 192, 87, 0.3); color: #51cf66; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;">
        <?= htmlspecialchars($feedback) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <div>
        <!-- Publish Notice Form -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">+ Publish Announcement</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="notices.php">
                    <input type="hidden" name="action" value="save_notice">
                    <div class="form-group">
                        <label class="form-label">Notice Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Wave IPTV 2.0 is available" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Content / Announcement Message *</label>
                        <textarea name="content" class="form-control" placeholder="What's new or what users need to know..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link to App (Optional)</label>
                        <select name="app_id" class="form-control">
                            <option value="0">— None (General News) —</option>
                            <?php foreach ($apps as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: var(--accent-glow);">
                            <input type="checkbox" name="send_push" value="1" <?= !empty($appIdSetting) ? 'checked' : '' ?>>
                            <span>🔔 Broadcast Push via OneSignal</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Publish Notice</button>
                </form>
            </div>
        </div>

        <!-- OneSignal Configuration -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📡 OneSignal Credentials</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="notices.php">
                    <input type="hidden" name="action" value="save_onesignal">
                    <div class="form-group">
                        <label class="form-label">OneSignal App ID</label>
                        <input type="text" name="onesignal_app_id" class="form-control" value="<?= htmlspecialchars($appIdSetting) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">REST API Key</label>
                        <input type="password" name="onesignal_api_key" class="form-control" value="<?= htmlspecialchars($apiKeySetting) ?>" placeholder="Key...">
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">Save Push Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Notices List -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔔 Published In-App Notices (<?= count($notices) ?>)</h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Title & Snippet</th>
                            <th>Linked App</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notices)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 20px; color: var(--text-secondary);">No notices published yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($notices as $n): ?>
                                <tr>
                                    <td style="font-size: 12px; color: var(--text-secondary); white-space: nowrap;">
                                        <?= date('d M Y', strtotime($n['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($n['title']) ?></div>
                                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;"><?= nl2br(htmlspecialchars(substr($n['content'], 0, 120))) ?>...</div>
                                    </td>
                                    <td>
                                        <?php if (!empty($n['app_title'])): ?>
                                            <span class="badge badge-primary"><?= htmlspecialchars($n['app_title']) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">General</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="notices.php?action=delete&id=<?= $n['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this notice?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
