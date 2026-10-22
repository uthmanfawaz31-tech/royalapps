<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isset($_GET['logout'])) {
    logoutAdmin();
    header('Location: login.php');
    exit;
}

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (loginAdmin($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid admin credentials.';
    }
}

$storeName = getSetting('store_name', 'Royal Media');
$storeLogo = getSetting('store_logo', 'uploads/icons/store_logo.jpg');
$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - <?= htmlspecialchars($storeName) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f59e0b;
            --primary-glow: rgba(245, 158, 11, 0.4);
            --bg-deep: #07090e;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse at 50% 10%, #2a1e05 0%, #0c0f18 50%, #06080c 100%);
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        /* Animated Floating Background Mesh & Light Orbs */
        .ambient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.35;
            pointer-events: none;
            z-index: 0;
            animation: floatOrb 18s ease-in-out infinite alternate;
        }
        .orb-1 {
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, #f59e0b 0%, rgba(217, 119, 6, 0) 70%);
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
            animation-duration: 14s;
        }
        .orb-2 {
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, #0ea5e9 0%, rgba(14, 165, 233, 0) 70%);
            bottom: -100px;
            left: 15%;
            animation-duration: 20s;
        }
        .orb-3 {
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, #8b5cf6 0%, rgba(139, 92, 246, 0) 70%);
            top: 40%;
            right: 10%;
            animation-duration: 16s;
        }
        @keyframes floatOrb {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -25px) scale(1.1); }
            100% { transform: translate(-25px, 35px) scale(0.95); }
        }

        /* Ambient Matrix Grid */
        .grid-matrix {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(245, 158, 11, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(245, 158, 11, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
            z-index: 1;
            pointer-events: none;
        }

        /* Glassmorphic Login Card */
        .login-card-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            padding: 24px;
        }
        .login-card {
            background: rgba(17, 23, 37, 0.75);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 28px;
            padding: 42px 36px;
            box-shadow: 
                0 30px 80px rgba(0, 0, 0, 0.75),
                0 0 50px rgba(245, 158, 11, 0.12),
                inset 0 1px 1px rgba(255, 255, 255, 0.1);
            animation: cardEntrance 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            position: relative;
            overflow: hidden;
        }
        @keyframes cardEntrance {
            0% { opacity: 0; transform: translateY(30px) scale(0.97); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Top Shimmering Gold Line */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #f59e0b, #fbbf24, transparent);
            animation: shimmerLine 3.5s infinite;
        }
        @keyframes shimmerLine {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }

        /* Animated Crown Logo Box */
        .logo-aura-container {
            position: relative;
            width: 86px;
            height: 86px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-aura-ring {
            position: absolute;
            inset: -6px;
            border-radius: 26px;
            background: conic-gradient(from 0deg, #f59e0b, transparent 60%, #0ea5e9, transparent 90%, #f59e0b);
            animation: rotateAura 6s linear infinite;
            filter: blur(6px);
            opacity: 0.8;
        }
        @keyframes rotateAura {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .login-logo {
            position: relative;
            z-index: 2;
            width: 76px;
            height: 76px;
            border-radius: 20px;
            object-fit: cover;
            border: 2px solid rgba(245, 158, 11, 0.6);
            background: #111726;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease;
        }
        .login-logo:hover {
            transform: scale(1.05) rotate(2deg);
        }

        .login-brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .login-title {
            text-align: center;
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .login-sub {
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 28px;
        }

        /* Form Inputs */
        .form-group {
            margin-bottom: 18px;
            position: relative;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        .input-wrap {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #64748b;
            pointer-events: none;
            transition: color 0.2s ease;
        }
        .form-control-custom {
            width: 100%;
            background: rgba(15, 21, 35, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            padding: 13px 14px 13px 44px;
            border-radius: 14px;
            font-size: 14px;
            outline: none;
            transition: all 0.25s ease;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.4);
        }
        .form-control-custom:focus {
            border-color: #f59e0b;
            background: rgba(20, 27, 45, 0.95);
            box-shadow: 
                0 0 0 3px rgba(245, 158, 11, 0.25),
                inset 0 2px 4px rgba(0,0,0,0.4);
        }
        .form-control-custom:focus + .input-icon {
            color: #f59e0b;
        }
        .toggle-pwd-btn {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 15px;
            padding: 4px;
            transition: color 0.2s ease;
        }
        .toggle-pwd-btn:hover { color: #f59e0b; }

        /* Error Alert */
        .error-alert {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.4s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }

        /* Submit Button */
        .btn-submit-login {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #000;
            border: none;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.35);
        }
        .btn-submit-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(245, 158, 11, 0.5);
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        }
        .btn-submit-login:active {
            transform: translateY(1px);
        }

        /* Security Badges */
        .security-badges {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-around;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 500;
        }
        .sec-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sec-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
        }

        .default-hint {
            margin-top: 18px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
        .default-hint strong { color: #fbbf24; }
    </style>
</head>
<body>
    <!-- Ambient Floating Orbs -->
    <div class="ambient-orb orb-1"></div>
    <div class="ambient-orb orb-2"></div>
    <div class="ambient-orb orb-3"></div>
    <div class="grid-matrix"></div>

    <div class="login-card-wrapper">
        <div class="login-card">
            <!-- Animated Crown Logo -->
            <div class="logo-aura-container">
                <div class="logo-aura-ring"></div>
                <img src="<?= $baseUrl ?>/<?= ltrim($storeLogo, '/') ?>" class="login-logo" alt="Royal Media Logo" onerror="this.src='../app/assets/store_logo.jpg'">
            </div>

            <div style="text-align: center;">
                <span class="login-brand-badge">👑 Royal Media Control Panel</span>
                <h1 class="login-title"><?= htmlspecialchars($storeName) ?></h1>
                <p class="login-sub">Secure authentication for TV Store administration</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-alert">
                    <span>⚠</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label class="form-label">Administrator Username</label>
                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" class="form-control-custom" placeholder="admin" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="admin_pwd_field" name="password" class="form-control-custom" placeholder="••••••••" required>
                        <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility()" title="Toggle visibility">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn-submit-login">
                    <span>Sign In to Panel</span>
                    <span>&rarr;</span>
                </button>
            </form>

            <div class="default-hint">
                Default Access: <strong>admin</strong> &bull; <strong>admin123</strong>
            </div>

            <div class="security-badges">
                <div class="sec-item"><span class="sec-dot"></span><span>256-Bit SSL</span></div>
                <div class="sec-item"><span>⚡</span><span>Instant Sync</span></div>
                <div class="sec-item"><span>📺</span><span>Fire TV Ready</span></div>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const field = document.getElementById('admin_pwd_field');
            if (field.type === 'password') {
                field.type = 'text';
            } else {
                field.type = 'password';
            }
        }
    </script>
</body>
</html>
