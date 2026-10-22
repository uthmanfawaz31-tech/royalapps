<?php
/**
 * One Login Authentication API
 * Authenticates either Store Accounts or Xtream Codes IPTV Accounts
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/xtream.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST method required.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');
$deviceId = trim($input['device_id'] ?? 'device_' . substr(md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16));
$deviceModel = trim($input['device_model'] ?? 'Android TV');

if (empty($username) || empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Username and password are required.'], 400);
}

$pdo = getDbConnection();

// 1. Try Store Account
$stmt = $pdo->prepare("SELECT * FROM store_users WHERE username = ?");
$stmt->execute([$username]);
$storeUser = $stmt->fetch();

if ($storeUser) {
    if (!password_verify($password, $storeUser['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Incorrect password for store account.'], 401);
    }

    if ($storeUser['status'] === 'blocked') {
        jsonResponse(['success' => false, 'message' => 'Your store account has been blocked by administrator.'], 403);
    }

    // Check expiry
    $now = date('Y-m-d');
    if (!empty($storeUser['expiry_date']) && $storeUser['expiry_date'] < $now) {
        jsonResponse(['success' => false, 'message' => 'Your store account expired on ' . $storeUser['expiry_date']], 403);
    }

    // Calculate days left
    $daysLeft = 365;
    if (!empty($storeUser['expiry_date'])) {
        $daysLeft = max(0, ceil((strtotime($storeUser['expiry_date']) - time()) / 86400));
    }

    // Device Limit Check
    $devStmt = $pdo->prepare("SELECT * FROM user_devices WHERE user_id = ?");
    $devStmt->execute([$storeUser['id']]);
    $existingDevices = $devStmt->fetchAll();

    $deviceFound = false;
    foreach ($existingDevices as $dev) {
        if ($dev['device_id'] === $deviceId) {
            $deviceFound = true;
            // Update last seen
            $pdo->prepare("UPDATE user_devices SET last_seen = ?, ip_address = ?, device_model = ? WHERE id = ?")
                ->execute([date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'], $deviceModel, $dev['id']]);
            break;
        }
    }

    if (!$deviceFound) {
        if (count($existingDevices) >= (int)$storeUser['max_devices']) {
            jsonResponse([
                'success' => false,
                'message' => 'Device limit reached (' . count($existingDevices) . '/' . $storeUser['max_devices'] . '). Contact admin to reset devices.'
            ], 403);
        }

        // Register new device
        $pdo->prepare("INSERT INTO user_devices (user_id, device_id, device_model, ip_address, last_seen) VALUES (?, ?, ?, ?, ?)")
            ->execute([$storeUser['id'], $deviceId, $deviceModel, $_SERVER['REMOTE_ADDR'], date('Y-m-d H:i:s')]);
    }

    $token = hash_hmac('sha256', $storeUser['id'] . '|' . $username . '|' . time(), APP_SECRET_KEY);

    jsonResponse([
        'success' => true,
        'message' => 'Sign in successful',
        'user' => [
            'username' => $storeUser['username'],
            'account_type' => 'store',
            'status' => 'active',
            'expiry_date' => $storeUser['expiry_date'] ?: 'Never',
            'days_left' => $daysLeft,
            'max_devices' => (int)$storeUser['max_devices'],
            'token' => $token
        ]
    ]);
}

// 2. Try Xtream Codes IPTV Account
$xtreamEnabled = (bool)getSetting('xtream_enabled', 1);
if ($xtreamEnabled) {
    $xcResult = XtreamAuth::authenticate($username, $password);
    if ($xcResult['success']) {
        $token = hash_hmac('sha256', 'xc|' . $username . '|' . time(), APP_SECRET_KEY);
        jsonResponse([
            'success' => true,
            'message' => 'Sign in successful with IPTV account',
            'user' => [
                'username' => $xcResult['username'],
                'account_type' => 'xtream',
                'status' => 'active',
                'expiry_date' => $xcResult['expiry_date'],
                'days_left' => $xcResult['days_left'],
                'max_devices' => $xcResult['max_devices'],
                'token' => $token
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => $xcResult['message']], 401);
    }
}

jsonResponse(['success' => false, 'message' => 'Account not found or invalid credentials.'], 401);
