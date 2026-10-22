<?php
$pageTitle = 'Store Accounts & Device Limits';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Handle Reset Devices
if ($action === 'reset_devices') {
    $userId = (int)($_GET['id'] ?? 0);
    if ($userId > 0) {
        $pdo->prepare("DELETE FROM user_devices WHERE user_id = ?")->execute([$userId]);
    }
    header('Location: users.php?msg=devices_reset');
    exit;
}

// Handle Delete User
if ($action === 'delete') {
    $userId = (int)($_GET['id'] ?? 0);
    if ($userId > 0) {
        $pdo->prepare("DELETE FROM store_users WHERE id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM user_devices WHERE user_id = ?")->execute([$userId]);
    }
    header('Location: users.php');
    exit;
}

// Handle Save User
if ($action === 'save_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $expiryDate = sanitizeInput($_POST['expiry_date'] ?? '');
    $maxDevices = (int)($_POST['max_devices'] ?? 1);
    $status = sanitizeInput($_POST['status'] ?? 'active');
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if (!empty($username)) {
        if ($userId > 0) {
            $sql = "UPDATE store_users SET username = ?, expiry_date = ?, max_devices = ?, status = ?, notes = ?";
            $params = [$username, $expiryDate ?: null, $maxDevices, $status, $notes];
            if (!empty($password)) {
                $sql .= ", password_hash = ?";
                $params[] = password_hash($password, PASSWORD_BCRYPT);
            }
            $sql .= " WHERE id = ?";
            $params[] = $userId;
            $pdo->prepare($sql)->execute($params);
        } else {
            $passHash = password_hash($password ?: '123456', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO store_users (username, password_hash, expiry_date, max_devices, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $passHash, $expiryDate ?: null, $maxDevices, $status, $notes, date('Y-m-d H:i:s')]);
        }
    }
    header('Location: users.php?msg=saved');
    exit;
}

// Handle Save Xtream Settings
if ($action === 'save_xtream' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    setSetting('xtream_enabled', isset($_POST['xtream_enabled']) ? '1' : '0');
    setSetting('xtream_server_url', sanitizeInput($_POST['xtream_server_url'] ?? ''));
    header('Location: users.php?msg=xtream_saved');
    exit;
}

// Fetch Users with Device Counts
$users = $pdo->query("
    SELECT u.*, COUNT(d.id) as device_count
    FROM store_users u
    LEFT JOIN user_devices d ON u.id = d.user_id
    GROUP BY u.id
    ORDER BY u.id DESC
")->fetchAll();

$xtreamEnabled = (bool)getSetting('xtream_enabled', 1);
$xtreamServerUrl = getSetting('xtream_server_url', 'http://demo.xtream-codes.com:8080');
?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
    <div>
        <!-- Create Store User -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">+ Create Store Account</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="save_user">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. kasun" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Set user password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date 📅</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+365 days')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Device Limit 📱</label>
                        <input type="number" name="max_devices" class="form-control" value="2" min="1" max="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active (Permitted)</option>
                            <option value="blocked">Blocked / Suspended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Customer name or reference ID">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Create Account</button>
                </form>
            </div>
        </div>

        <!-- Xtream Codes IPTV Provider Settings -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">🔐 Xtream Codes IPTV Sign-In</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="save_xtream">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" name="xtream_enabled" value="1" <?= $xtreamEnabled ? 'checked' : '' ?>>
                            <span>Allow Customers to Log In with IPTV Accounts</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">IPTV Server URL / DNS</label>
                        <input type="text" name="xtream_server_url" class="form-control" value="<?= htmlspecialchars($xtreamServerUrl) ?>" placeholder="http://provider.dns:8080">
                        <small style="color: var(--text-secondary); font-size: 11px;">Credentials are checked directly against your Xtream Codes player API.</small>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;">Save Xtream Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">👥 Store Accounts (<?= count($users) ?>)</h2>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th>Devices</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">No store users created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                                        <?php if (!empty($u['notes'])): ?>
                                            <div style="font-size: 11px; color: var(--text-secondary);"><?= htmlspecialchars($u['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                            <?= ucfirst($u['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $u['expiry_date'] ? date('d M Y', strtotime($u['expiry_date'])) : 'Never' ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= $u['device_count'] ?> / <?= $u['max_devices'] ?></span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <a href="users.php?action=reset_devices&id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" title="Clear linked devices">Reset Devs</a>
                                            <a href="users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user account?')">Delete</a>
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
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
