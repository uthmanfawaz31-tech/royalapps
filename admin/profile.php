<?php
$pageTitle = 'Admin Credentials & Security';
require_once __DIR__ . '/header.php';

$pdo = getDbConnection();
$feedback = '';
$error = '';

$adminId = $_SESSION['admin_id'] ?? 1;

// Fetch current admin
$stmt = $pdo->prepare("SELECT username, created_at FROM admins WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['username'] ?? '');
    $currentPass = trim($_POST['current_password'] ?? '');
    $newPass = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if (empty($newUsername)) {
        $error = 'Username cannot be empty.';
    } else {
        // Verify current password first
        $vStmt = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
        $vStmt->execute([$adminId]);
        $currentHash = $vStmt->fetchColumn();

        if (!password_verify($currentPass, $currentHash)) {
            $error = 'Current password is incorrect.';
        } else {
            // Update username
            $pdo->prepare("UPDATE admins SET username = ? WHERE id = ?")->execute([$newUsername, $adminId]);
            $_SESSION['admin_username'] = $newUsername;

            // If password update requested
            if (!empty($newPass)) {
                if (strlen($newPass) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } elseif ($newPass !== $confirmPass) {
                    $error = 'New passwords do not match.';
                } else {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$newHash, $adminId]);
                    $feedback = 'Admin credentials and password updated successfully!';
                }
            } else {
                $feedback = 'Admin username updated successfully!';
            }

            // Refresh admin info
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();
        }
    }
}
?>

<?php if ($feedback): ?>
    <div style="background: rgba(64, 192, 87, 0.15); border: 1px solid rgba(64, 192, 87, 0.3); color: #51cf66; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">
        ✓ <?= htmlspecialchars($feedback) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">
        ⚠ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div style="max-width: 600px;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔐 Update Admin Login Credentials</h2>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                Change the username or password used to log into the Royal Media Admin Panel.
            </p>

            <form method="POST" action="profile.php">
                <div class="form-group">
                    <label class="form-label">Admin Username *</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($admin['username'] ?? 'admin') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Current Password (Required for any changes) *</label>
                    <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 22px 0;">

                <div class="form-group">
                    <label class="form-label">New Password (Leave blank to keep current password)</label>
                    <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                    <small style="color: var(--text-secondary); font-size: 11px;">Must be at least 6 characters.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="background: #f59e0b; color: #000; font-weight: 700; padding: 12px 28px; width: 100%; justify-content: center; margin-top: 10px;">
                    💾 Save New Credentials
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
