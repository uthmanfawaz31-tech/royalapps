<?php
$pageTitle = 'Store Accounts & Client Passwords';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Handle Reset Password to Default (123456)
if ($action === 'reset_password_default') {
    $userId = (int)($_GET['id'] ?? 0);
    if ($userId > 0) {
        $defaultHash = password_hash('123456', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE store_users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$defaultHash, $userId]);
    }
    header('Location: users.php?msg=pwd_reset_default');
    exit;
}

// Handle Custom Password Change
if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $newPassword = trim($_POST['new_password'] ?? '');
    if ($userId > 0 && !empty($newPassword)) {
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE store_users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        header('Location: users.php?msg=pwd_changed');
        exit;
    }
    header('Location: users.php?msg=pwd_error');
    exit;
}

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
    header('Location: users.php?msg=deleted');
    exit;
}

// Handle Save / Update User
if ($action === 'save_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $expiryDate = sanitizeInput($_POST['expiry_date'] ?? '');
    $maxDevices = (int)($_POST['max_devices'] ?? 2);
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
            header('Location: users.php?msg=updated');
            exit;
        } else {
            $passHash = password_hash($password ?: '123456', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO store_users (username, password_hash, expiry_date, max_devices, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $passHash, $expiryDate ?: null, $maxDevices, $status, $notes, date('Y-m-d H:i:s')]);
            header('Location: users.php?msg=created');
            exit;
        }
    }
    header('Location: users.php');
    exit;
}

// Handle Save Xtream Settings
if ($action === 'save_xtream' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    setSetting('xtream_enabled', isset($_POST['xtream_enabled']) ? '1' : '0');
    setSetting('xtream_server_url', sanitizeInput($_POST['xtream_server_url'] ?? ''));
    header('Location: users.php?msg=xtream_saved');
    exit;
}

// Check if Editing a specific user
$editUser = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM store_users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
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
$msg = $_GET['msg'] ?? '';
?>

<!-- Alert Feedback Banners -->
<?php if ($msg === 'pwd_reset_default'): ?>
    <div class="alert-box success">
        <span>✅ Client password has been successfully reset to default: <strong>123456</strong></span>
    </div>
<?php elseif ($msg === 'pwd_changed'): ?>
    <div class="alert-box success">
        <span>✅ Client password has been updated successfully.</span>
    </div>
<?php elseif ($msg === 'created'): ?>
    <div class="alert-box success">
        <span>✅ New client account created successfully.</span>
    </div>
<?php elseif ($msg === 'updated'): ?>
    <div class="alert-box success">
        <span>✅ Client account updated successfully.</span>
    </div>
<?php elseif ($msg === 'deleted'): ?>
    <div class="alert-box success">
        <span>✅ Client account deleted.</span>
    </div>
<?php elseif ($msg === 'devices_reset'): ?>
    <div class="alert-box success">
        <span>✅ Linked devices cleared. Client can now log in on new devices.</span>
    </div>
<?php elseif ($msg === 'xtream_saved'): ?>
    <div class="alert-box success">
        <span>✅ Xtream Codes IPTV settings saved.</span>
    </div>
<?php endif; ?>

<div class="grid-layout-1-2">
    <div>
        <!-- Create or Edit Store User Form -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 class="card-title"><?= $editUser ? '✏️ Edit Client Account' : '+ Create Client Account' ?></h2>
                <?php if ($editUser): ?>
                    <a href="users.php" class="btn btn-secondary btn-sm">✕ Cancel Edit</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="save_user">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">Client Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. client1" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" style="margin-bottom: 0;">
                                <?= $editUser ? 'New Password (Optional)' : 'Client Password *' ?>
                            </label>
                            <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 11px;" onclick="fillDefaultPassword()">
                                Set Default (123456)
                            </button>
                        </div>
                        <input type="password" id="client_pwd_input" name="password" class="form-control" placeholder="<?= $editUser ? 'Leave blank to keep unchanged' : 'Enter client password' ?>" <?= $editUser ? '' : 'required' ?>>
                        <small style="color: var(--text-secondary); font-size: 11px; display: block; margin-top: 4px;">
                            <?= $editUser ? 'Only enter a password if you want to change the existing one.' : 'You can enter any custom password or click "Set Default (123456)".' ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expiry Date 📅</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($editUser['expiry_date'] ?? date('Y-m-d', strtotime('+365 days'))) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Allowed TV Devices 📱</label>
                        <input type="number" name="max_devices" class="form-control" value="<?= (int)($editUser['max_devices'] ?? 2) ?>" min="1" max="20">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= ($editUser['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active (Permitted)</option>
                            <option value="blocked" <?= ($editUser['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocked / Suspended</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Customer name or reference ID" value="<?= htmlspecialchars($editUser['notes'] ?? '') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <?= $editUser ? '💾 Update Client Account' : '🚀 Create Client Account' ?>
                    </button>
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
            <h2 class="card-title">👥 Client Accounts (<?= count($users) ?>)</h2>
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
                            <th style="min-width: 220px;">Password & Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 24px; color: var(--text-secondary);">No store users created yet. Use the form to create your first client.</td></tr>
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
                                        <div class="action-btn-group" style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')" title="Set a new password for this client">
                                                🔑 Set Pass
                                            </button>
                                            <a href="users.php?action=reset_password_default&id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Reset password for \'<?= htmlspecialchars(addslashes($u['username'])) ?>\' to default (123456)?')" title="Reset password to default (123456)">
                                                🔄 Reset Default
                                            </a>
                                            <a href="users.php?edit_id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" title="Edit account details">
                                                ✏️ Edit
                                            </a>
                                            <a href="users.php?action=reset_devices&id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" title="Clear linked devices">
                                                📱 Reset Devs
                                            </a>
                                            <a href="users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete user \'<?= htmlspecialchars(addslashes($u['username'])) ?>\'?')" title="Delete user">
                                                🗑 Delete
                                            </a>
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

<!-- Modal: Change Password Directly -->
<div id="passwordModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-backdrop" onclick="closePasswordModal()"></div>
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3 class="card-title">🔑 Set Password for <span id="modalUsername" style="color: var(--primary);"></span></h3>
            <button type="button" class="btn-close-modal" onclick="closePasswordModal()">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" id="modalUserId" name="user_id" value="">
            
            <div class="admin-modal-body">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" id="modalNewPassword" name="new_password" class="form-control" placeholder="Enter new custom password" required autocomplete="new-password">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="setModalDefaultPassword()">
                        Use Default: 123456
                    </button>
                </div>
            </div>
            
            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function fillDefaultPassword() {
    const input = document.getElementById('client_pwd_input');
    if (input) {
        input.value = '123456';
        input.focus();
    }
}

function openPasswordModal(userId, username) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUsername').textContent = username;
    document.getElementById('modalNewPassword').value = '';
    document.getElementById('passwordModal').style.display = 'flex';
    document.getElementById('modalNewPassword').focus();
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

function setModalDefaultPassword() {
    document.getElementById('modalNewPassword').value = '123456';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
