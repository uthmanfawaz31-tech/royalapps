"""
Royal Media TV App Store - Local Development & Interactive Preview Server
Features:
- Admin Login Page (/admin/login.php) with authentication
- Admin Credentials & Password Update (/admin/profile.php)
- App Icon Upload (/admin/apps.php)
- Real-time sync with Customer TV App (/app/index.html)
"""

import http.server
import socketserver
import os
import json
import urllib.parse
import base64
import time

PORT = 8080
ROOT_DIR = os.path.dirname(os.path.abspath(__file__))

# Admin credentials state
ADMIN_CREDENTIALS = {
    "username": "admin",
    "password": "admin123"
}

ADMIN_SESSIONS = set()

ROYAL_MEDIA_APPS = [
    {
        "id": 1,
        "title": "RoyalTvXciptv.apk",
        "package_name": "com.royaltv.xciptv",
        "developer": "Royal Media",
        "category_name": "IPTV Players",
        "icon_url": "assets/royaltv_xc.jpg",
        "banner_url": "assets/screenshot_preview1.jpg",
        "is_featured": True,
        "description": "RoyalTvXciptv official IPTV client. Optimized for high speed streaming on Android TV, Fire TV, and mobile devices. Supports live TV, movies, series, EPG TV guide, and multi-screen view.",
        "changelog": "⚡ Multi-connection streaming support\n📺 Full remote D-Pad control\n📱 Downloader Code: 925742",
        "version": {
            "version_name": "v1.0",
            "version_code": 1,
            "file_size": 48200000,
            "file_size_formatted": "48.2 MB",
            "downloads_count": 0,
            "downloader_code": "925742",
            "download_url": "http://aftv.news/925742",
            "mirrors": [
                {"name": "Download #1 (aftv.news/925742)", "type": "in_app", "badge": "In-app download", "url": "http://aftv.news/925742"},
                {"name": "Download #2 (Direct Link)", "type": "browser", "badge": "Direct mirror", "url": "http://aftv.news/925742"}
            ]
        },
        "screenshots": [
            "assets/screenshot_preview1.jpg",
            "assets/screenshot_preview2.jpg",
            "assets/screenshot_preview3.jpg"
        ]
    },
    {
        "id": 2,
        "title": "Royal Media IBO",
        "package_name": "com.royalmedia.ibo",
        "developer": "Royal Media",
        "category_name": "IPTV Players",
        "icon_url": "assets/royal_ibo.jpg",
        "banner_url": "assets/screenshot_preview2.jpg",
        "is_featured": True,
        "description": "Royal Media IBO player with next-gen interface, fast channel switching, 4K/UHD video playback pipeline, and easy playlist loading.",
        "changelog": "🎥 Fast playlist loading and caching\n📺 Subtitle and audio track selection\n📱 Downloader Code: 5451030",
        "version": {
            "version_name": "v1.0",
            "version_code": 1,
            "file_size": 52600000,
            "file_size_formatted": "52.6 MB",
            "downloads_count": 0,
            "downloader_code": "5451030",
            "download_url": "http://aftv.news/5451030",
            "mirrors": [
                {"name": "Download #1 (aftv.news/5451030)", "type": "in_app", "badge": "In-app download", "url": "http://aftv.news/5451030"},
                {"name": "Download #2 (Direct Link)", "type": "browser", "badge": "Direct mirror", "url": "http://aftv.news/5451030"}
            ]
        },
        "screenshots": [
            "assets/screenshot_preview2.jpg",
            "assets/screenshot_preview1.jpg",
            "assets/screenshot_preview3.jpg"
        ]
    },
    {
        "id": 3,
        "title": "Royal Media TiVi",
        "package_name": "com.royalmedia.tivi",
        "developer": "Royal Media",
        "category_name": "IPTV Players",
        "icon_url": "assets/royal_tivi.jpg",
        "banner_url": "assets/screenshot_preview3.jpg",
        "is_featured": True,
        "description": "Royal Media TiVi companion app for high performance video and audio playback, advanced favorites management, and TV remote integration.",
        "changelog": "⚡ Ultra-smooth remote navigation\n📺 Custom grid and list layout\n📱 Downloader Code: 8542493",
        "version": {
            "version_name": "v1.0",
            "version_code": 1,
            "file_size": 46100000,
            "file_size_formatted": "46.1 MB",
            "downloads_count": 0,
            "downloader_code": "8542493",
            "download_url": "http://aftv.news/8542493",
            "mirrors": [
                {"name": "Download #1 (aftv.news/8542493)", "type": "in_app", "badge": "In-app download", "url": "http://aftv.news/8542493"},
                {"name": "Download #2 (Direct Link)", "type": "browser", "badge": "Direct mirror", "url": "http://aftv.news/8542493"}
            ]
        },
        "screenshots": [
            "assets/screenshot_preview3.jpg",
            "assets/screenshot_preview1.jpg",
            "assets/screenshot_preview2.jpg"
        ]
    },
    {
        "id": 4,
        "title": "Royal Media Smarters",
        "package_name": "com.royalmedia.smarters",
        "developer": "Royal Media",
        "category_name": "IPTV Players",
        "icon_url": "assets/royal_smarters.jpg",
        "banner_url": "assets/screenshot_preview1.jpg",
        "is_featured": False,
        "description": "Royal Media Smarters app with full Xtream Codes support, multi-user login, parental controls, and catch-up TV.",
        "changelog": "🔐 Multi-account support\n📺 Catchup and EPG guide\n📱 Downloader Code: 6595514",
        "version": {
            "version_name": "v1.0",
            "version_code": 1,
            "file_size": 55800000,
            "file_size_formatted": "55.8 MB",
            "downloads_count": 0,
            "downloader_code": "6595514",
            "download_url": "http://aftv.news/6595514",
            "mirrors": [
                {"name": "Download #1 (aftv.news/6595514)", "type": "in_app", "badge": "In-app download", "url": "http://aftv.news/6595514"},
                {"name": "Download #2 (Direct Link)", "type": "browser", "badge": "Direct mirror", "url": "http://aftv.news/6595514"}
            ]
        },
        "screenshots": [
            "assets/screenshot_preview1.jpg",
            "assets/screenshot_preview2.jpg"
        ]
    },
    {
        "id": 5,
        "title": "ROYAL VPN",
        "package_name": "com.royalmedia.vpn",
        "developer": "Royal Media",
        "category_name": "Utilities & Tools",
        "icon_url": "assets/royal_vpn.jpg",
        "banner_url": "assets/screenshot_preview2.jpg",
        "is_featured": True,
        "description": "Official ROYAL VPN application. Protect your online streaming, prevent ISP throttling, and unblock global servers on Android TV, Fire TV, and mobile.",
        "changelog": "🛡️ High-speed streaming VPN servers\n⚡ Zero log policy and kill switch\n📱 Downloader Code: 6808531",
        "version": {
            "version_name": "v1.0",
            "version_code": 1,
            "file_size": 28400000,
            "file_size_formatted": "28.4 MB",
            "downloads_count": 0,
            "downloader_code": "6808531",
            "download_url": "http://aftv.news/6808531",
            "mirrors": [
                {"name": "Download #1 (aftv.news/6808531)", "type": "in_app", "badge": "In-app download", "url": "http://aftv.news/6808531"},
                {"name": "Download #2 (Direct Link)", "type": "browser", "badge": "Direct mirror", "url": "http://aftv.news/6808531"}
            ]
        },
        "screenshots": [
            "assets/screenshot_preview2.jpg",
            "assets/screenshot_preview1.jpg"
        ]
    }
]

# Real-time Download Logs & Device Tracking (All reset to 0)
REAL_DOWNLOAD_LOGS = []
REGISTERED_DEVICES = set()

ROYAL_MEDIA_NOTICES = [
    {
        "id": 1,
        "title": "RoyalTvXciptv.apk is ready for download",
        "content": "⚡ RoyalTvXciptv.apk is available now.\n- Downloader Code: 925742\n- Direct Link: http://aftv.news/925742\n- Full remote D-Pad control and multi-screen support.",
        "app_id": 1,
        "date_formatted": "Royal Media Notice"
    },
    {
        "id": 2,
        "title": "Royal Media IBO Player & TiVi online",
        "content": "🎥 Royal Media IBO (Code: 5451030) and Royal Media TiVi (Code: 8542493) are online with enhanced high definition streaming pipelines.",
        "app_id": 2,
        "date_formatted": "Royal Media Notice"
    },
    {
        "id": 3,
        "title": "ROYAL VPN for IPTV protection",
        "content": "🛡️ Download ROYAL VPN (Code: 6808531) to protect your streaming and unblock unrestricted speeds.",
        "app_id": 5,
        "date_formatted": "Royal Media Notice"
    }
]

def render_admin_shell(page_title, content_html, active_menu="dashboard"):
    username = ADMIN_CREDENTIALS["username"]
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{page_title} - Royal Media Admin Panel</title>
    <link rel="stylesheet" href="/admin/assets/style.css">
    <style>
        :root {{
            --primary: #f59e0b;
            --primary-hover: #d97706;
        }}
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="/app/assets/store_logo.jpg" class="sidebar-logo" alt="Logo">
            <div>
                <div class="brand-name">Royal Media</div>
                <span class="brand-badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">ADMIN v2.0</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="/admin/index.php" class="menu-link {'active' if active_menu=='dashboard' else ''}"><span class="menu-icon">📊</span><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="/admin/apps.php" class="menu-link {'active' if active_menu=='apps' else ''}"><span class="menu-icon">📱</span><span>Apps & APKs</span></a></li>
            <li class="menu-item"><a href="/admin/notices.php" class="menu-link {'active' if active_menu=='notices' else ''}"><span class="menu-icon">🔔</span><span>Notices & Push</span></a></li>
            <li class="menu-item"><a href="/admin/profile.php" class="menu-link {'active' if active_menu=='profile' else ''}"><span class="menu-icon">🔐</span><span>Admin Credentials</span></a></li>
            <li class="menu-item" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                <a href="/app/index.html" target="_blank" class="menu-link" style="color: #f59e0b;"><span class="menu-icon">📺</span><span>Open TV Store</span></a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <button class="btn btn-secondary btn-sm" onclick="toggleTheme()">🌓 Theme</button>
            <a href="/admin/login.php?logout=1" class="btn btn-danger btn-sm">🚪 Logout</a>
        </div>
    </aside>
    <div class="main-wrapper">
        <header class="top-nav">
            <h1 class="nav-title">{page_title}</h1>
            <div class="nav-actions">
                <span style="color: var(--text-secondary); font-size: 13px;">Logged in as <strong style="color: #f59e0b;">{username}</strong></span>
                <a href="/admin/apps.php?action=add" class="btn btn-primary btn-sm" style="background: #f59e0b; color: #000; font-weight: 700;">+ Upload / Add App</a>
            </div>
        </header>
        <main class="content-body">
            {content_html}
        </main>
    </div>
    <script src="/admin/assets/admin.js"></script>
</body>
</html>"""

class StoreRequestHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT_DIR, **kwargs)

    def redirect(self, location, cookie=None):
        self.send_response(302)
        self.send_header('Location', location)
        self.send_header('Content-Length', '0')
        self.send_header('Connection', 'close')
        if cookie:
            self.send_header('Set-Cookie', cookie)
        self.end_headers()

    def is_admin_logged_in(self):
        cookies = self.headers.get('Cookie', '')
        return 'admin_session=active' in cookies

    def do_GET(self):
        url_parts = urllib.parse.urlparse(self.path)
        path = url_parts.path
        query = urllib.parse.parse_qs(url_parts.query)

        if path == '/' or path == '/index.html' or path == '/index.php':
            self.redirect('/app/index.html')
            return

        # ==============================================================
        # Admin Login & Logout Handler
        # ==============================================================
        if path == '/admin/login.php':
            if query.get('logout'):
                self.redirect('/admin/login.php', cookie='admin_session=; Path=/; Max-Age=0')
                return

            login_html = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Royal Media</title>
    <link rel="stylesheet" href="/admin/assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {{ box-sizing: border-box; margin: 0; padding: 0; }}
        body {{
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse at 50% 10%, #2a1e05 0%, #0c0f18 50%, #06080c 100%);
            color: #fff;
            overflow: hidden;
            position: relative;
        }}
        .ambient-orb {{
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.35;
            pointer-events: none;
            animation: floatOrb 18s ease-in-out infinite alternate;
        }}
        .orb-1 {{
            width: 450px; height: 450px;
            background: radial-gradient(circle, #f59e0b 0%, transparent 70%);
            top: -120px; left: 50%; transform: translateX(-50%);
            animation-duration: 14s;
        }}
        .orb-2 {{
            width: 380px; height: 380px;
            background: radial-gradient(circle, #0ea5e9 0%, transparent 70%);
            bottom: -100px; left: 15%;
            animation-duration: 20s;
        }}
        .orb-3 {{
            width: 320px; height: 320px;
            background: radial-gradient(circle, #8b5cf6 0%, transparent 70%);
            top: 40%; right: 10%;
            animation-duration: 16s;
        }}
        @keyframes floatOrb {{
            0% {{ transform: translate(0, 0) scale(1); }}
            50% {{ transform: translate(30px, -25px) scale(1.1); }}
            100% {{ transform: translate(-25px, 35px) scale(0.95); }}
        }}
        .grid-matrix {{
            position: absolute; inset: 0;
            background-image: 
                linear-gradient(rgba(245, 158, 11, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(245, 158, 11, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
            pointer-events: none;
        }}
        .login-card-wrapper {{
            position: relative; z-index: 10;
            width: 100%; max-width: 440px; padding: 24px;
        }}
        .login-card {{
            background: rgba(17, 23, 37, 0.78);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1px solid rgba(245, 158, 11, 0.35);
            border-radius: 28px;
            padding: 42px 36px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.75), 0 0 50px rgba(245, 158, 11, 0.12);
            animation: cardEntrance 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            position: relative; overflow: hidden;
        }}
        @keyframes cardEntrance {{
            0% {{ opacity: 0; transform: translateY(30px) scale(0.97); }}
            100% {{ opacity: 1; transform: translateY(0) scale(1); }}
        }}
        .login-card::before {{
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 3px;
            background: linear-gradient(90deg, transparent, #f59e0b, #fbbf24, transparent);
            animation: shimmerLine 3.5s infinite;
        }}
        @keyframes shimmerLine {{
            0% {{ left: -100%; }}
            50%, 100% {{ left: 100%; }}
        }}
        .logo-aura-container {{
            position: relative; width: 86px; height: 86px;
            margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;
        }}
        .logo-aura-ring {{
            position: absolute; inset: -6px; border-radius: 26px;
            background: conic-gradient(from 0deg, #f59e0b, transparent 60%, #0ea5e9, transparent 90%, #f59e0b);
            animation: rotateAura 6s linear infinite;
            filter: blur(6px); opacity: 0.8;
        }}
        @keyframes rotateAura {{
            0% {{ transform: rotate(0deg); }}
            100% {{ transform: rotate(360deg); }}
        }}
        .login-logo {{
            position: relative; z-index: 2; width: 76px; height: 76px;
            border-radius: 20px; object-fit: cover;
            border: 2px solid rgba(245, 158, 11, 0.6);
            background: #111726; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease;
        }}
        .login-logo:hover {{ transform: scale(1.05) rotate(2deg); }}
        .login-brand-badge {{
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24; padding: 4px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.5px;
            text-transform: uppercase; margin-bottom: 12px;
        }}
        .login-title {{
            text-align: center; font-size: 26px; font-weight: 800;
            color: #fff; letter-spacing: -0.5px; margin-bottom: 6px;
        }}
        .login-sub {{
            text-align: center; font-size: 13px; color: #94a3b8; margin-bottom: 28px;
        }}
        .form-group {{ margin-bottom: 18px; }}
        .form-label {{
            display: block; font-size: 13px; font-weight: 600;
            color: #cbd5e1; margin-bottom: 8px;
        }}
        .input-wrap {{ position: relative; }}
        .input-icon {{
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            font-size: 16px; color: #64748b; pointer-events: none;
        }}
        .form-control-custom {{
            width: 100%; background: rgba(15, 21, 35, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff; padding: 13px 14px 13px 44px;
            border-radius: 14px; font-size: 14px; outline: none;
            transition: all 0.25s ease;
        }}
        .form-control-custom:focus {{
            border-color: #f59e0b; background: rgba(20, 27, 45, 0.95);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
        }}
        .btn-submit-login {{
            width: 100%; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #000; border: none; padding: 14px 20px; border-radius: 14px;
            font-size: 15px; font-weight: 800; cursor: pointer;
            transition: all 0.3s ease; display: flex; align-items: center;
            justify-content: center; gap: 8px; margin-top: 10px;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.35);
        }}
        .btn-submit-login:hover {{
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(245, 158, 11, 0.5);
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        }}
        .default-hint {{
            margin-top: 18px; padding: 10px 14px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px; font-size: 12px; color: #94a3b8; text-align: center;
        }}
        .default-hint strong {{ color: #fbbf24; }}
        .security-badges {{
            margin-top: 24px; padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex; align-items: center; justify-content: space-around;
            color: #94a3b8; font-size: 11px; font-weight: 500;
        }}
        .sec-dot {{
            width: 6px; height: 6px; border-radius: 50%;
            background: #10b981; box-shadow: 0 0 8px #10b981; display: inline-block; margin-right: 4px;
        }}
    </style>
</head>
<body>
    <div class="ambient-orb orb-1"></div>
    <div class="ambient-orb orb-2"></div>
    <div class="ambient-orb orb-3"></div>
    <div class="grid-matrix"></div>

    <div class="login-card-wrapper">
        <div class="login-card">
            <div class="logo-aura-container">
                <div class="logo-aura-ring"></div>
                <img src="/app/assets/store_logo.jpg" class="login-logo" alt="Logo">
            </div>

            <div style="text-align: center;">
                <span class="login-brand-badge">👑 Royal Media Control Panel</span>
                <h1 class="login-title">Royal Media</h1>
                <p class="login-sub">Secure authentication for TV Store administration</p>
            </div>

            <form method="POST" action="/admin/login.php">
                <div class="form-group">
                    <label class="form-label">Administrator Username</label>
                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" class="form-control-custom" value="{ADMIN_CREDENTIALS['username']}" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" class="form-control-custom" value="{ADMIN_CREDENTIALS['password']}" required>
                    </div>
                </div>
                <button type="submit" class="btn-submit-login">
                    <span>Sign In to Panel</span>
                    <span>&rarr;</span>
                </button>
            </form>

            <div class="default-hint">
                Current Access: <strong>{ADMIN_CREDENTIALS['username']}</strong> &bull; <strong>{ADMIN_CREDENTIALS['password']}</strong>
            </div>

            <div class="security-badges">
                <div><span class="sec-dot"></span>256-Bit SSL</div>
                <div>⚡ Instant Sync</div>
                <div>📺 Fire TV Ready</div>
            </div>
        </div>
    </div>
</body>
</html>"""
            self.send_html(login_html)
            return

        # Guard Admin Panel routes
        if path.startswith('/admin'):
            if not self.is_admin_logged_in():
                self.redirect('/admin/login.php')
                return

        # ==============================================================
        # Admin Dashboard
        # ==============================================================
        if path == '/admin' or path == '/admin/' or path == '/admin/index.php':
            total_dl = sum(a['version']['downloads_count'] for a in ROYAL_MEDIA_APPS)
            apps_rows = ""
            for a in ROYAL_MEDIA_APPS:
                icon_src = a['icon_url'] if a['icon_url'].startswith('data:') else f"/app/{a['icon_url']}"
                apps_rows += f"""<tr>
                    <td style="display: flex; align-items: center; gap: 12px;">
                        <img src="{icon_src}" style="width: 38px; height: 38px; border-radius: 8px; object-fit: cover; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <div>
                            <strong>{a['title']}</strong>
                            <div style="font-size: 11px; color: var(--text-secondary);">{a['package_name']}</div>
                        </div>
                    </td>
                    <td><span class="badge badge-primary" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">{a['category_name']}</span></td>
                    <td><strong style="color: #f59e0b; font-family: monospace;">{a['version']['downloader_code']}</strong></td>
                    <td>{a['version']['downloads_count']}</td>
                    <td><a href="/admin/apps.php" class="btn btn-secondary btn-sm">Edit</a></td>
                </tr>"""

            active_devs = len(REGISTERED_DEVICES)
            recent_logs_rows = ""
            if not REAL_DOWNLOAD_LOGS:
                recent_logs_rows = '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 24px; font-size: 13px;">No downloads yet. All counters and devices are reset to 0 for real tracking.<br>When an app is downloaded from Fire TV, Android TV, or phone, real-time logs will show here immediately.</td></tr>'
            else:
                for log in reversed(REAL_DOWNLOAD_LOGS[-15:]):
                    recent_logs_rows += f"""<tr>
                        <td style="display: flex; align-items: center; gap: 10px;">
                            <img src="{log['icon_url']}" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover; border: 1px solid rgba(245, 158, 11, 0.4);">
                            <strong>{log['app_title']}</strong>
                        </td>
                        <td><span style="font-family: monospace; color: #f59e0b; font-weight: 700;">{log['code']}</span></td>
                        <td>{log['time']}</td>
                        <td><span class="badge badge-success" style="background: rgba(16, 185, 129, 0.2); color: #10b981;">✓ Downloaded</span></td>
                        <td><span style="font-size: 11px; color: var(--text-secondary);">{log['device'][:35]}</span></td>
                    </tr>"""

            content = f"""
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">⚡ Total Real Downloads</div>
                    <div class="stat-val" style="color: #f59e0b;">{total_dl}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">📱 Royal Media Apps</div>
                    <div class="stat-val">{len(ROYAL_MEDIA_APPS)}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">📺 Active Customer Devices</div>
                    <div class="stat-val" style="color: #0ea5e9;">{active_devs}</div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2 class="card-title">👑 Active Royal Media Catalog</h2>
                    <div style="display: flex; gap: 10px;">
                        <a href="/admin/profile.php" class="btn btn-secondary btn-sm">🔐 Change Credentials</a>
                        <a href="/admin/apps.php?action=add" class="btn btn-primary btn-sm" style="background: #f59e0b; color: #000; font-weight: 700;">+ Upload New App</a>
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>App</th><th>Category</th><th>Downloader Code</th><th>Real Downloads</th><th>Action</th></tr>
                        </thead>
                        <tbody>{apps_rows}</tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🔴 Live Real-Time Download Activity</h2>
                    <span style="font-size: 12px; color: var(--text-secondary);">Updates instantly on customer downloads</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>App Downloaded</th><th>Downloader Code</th><th>Time</th><th>Status</th><th>Device / Source</th></tr>
                        </thead>
                        <tbody>{recent_logs_rows}</tbody>
                    </table>
                </div>
            </div>
            """
            self.send_html(render_admin_shell("Royal Media Dashboard", content, "dashboard"))
            return

        # ==============================================================
        # Admin Profile / Credential Update
        # ==============================================================
        if path == '/admin/profile.php':
            msg = query.get('msg', [''])[0]
            feedback_html = ""
            if msg == 'updated':
                feedback_html = '<div style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #10b981; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 700;">✓ Admin credentials updated successfully! You can now log in with your new credentials.</div>'

            profile_html = f"""
            {feedback_html}
            <div style="max-width: 600px;">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🔐 Change Admin Login Credentials</h2>
                    </div>
                    <div class="card-body">
                        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
                            Update the username or password used to access your Royal Media Admin Control Panel.
                        </p>
                        <form method="POST" action="/admin/profile.php">
                            <div class="form-group">
                                <label class="form-label">Admin Username *</label>
                                <input type="text" name="username" class="form-control" value="{ADMIN_CREDENTIALS['username']}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Current Password *</label>
                                <input type="password" name="current_password" class="form-control" placeholder="Enter current password to authorize changes" required>
                            </div>
                            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
                            <div class="form-group">
                                <label class="form-label">New Password (Leave blank to keep unchanged)</label>
                                <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                            </div>
                            <button type="submit" class="btn btn-primary" style="background: #f59e0b; color: #000; font-weight: 700; width: 100%; justify-content: center; padding: 12px; font-size: 15px; margin-top: 10px;">
                                💾 Update Admin Credentials
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            """
            self.send_html(render_admin_shell("Admin Credentials", profile_html, "profile"))
            return

        # ==============================================================
        # Admin Apps & APKs (with Icon Upload)
        # ==============================================================
        if path == '/admin/apps.php':
            action = query.get('action', ['list'])[0]
            if action == 'add':
                add_html = """
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">+ Upload New App (One-Click Customer Broadcast)</h2>
                        <a href="/admin/apps.php" class="btn btn-secondary btn-sm">&larr; Back to Apps</a>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/apps.php">
                            <input type="hidden" name="icon_data" id="icon_data_field" value="">

                            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                                <div>
                                    <div class="form-group">
                                        <label class="form-label">App Title *</label>
                                        <input type="text" name="title" class="form-control" placeholder="e.g. Royal Cinema VIP" required>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                                        <div class="form-group">
                                            <label class="form-label">Downloader Code (AFTVnews) *</label>
                                            <input type="text" name="downloader_code" class="form-control" placeholder="e.g. 748192" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Package Name (Android ID)</label>
                                            <input type="text" name="package_name" class="form-control" placeholder="com.royalmedia.cinema">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Direct Download Link *</label>
                                        <input type="text" name="download_url" class="form-control" placeholder="http://aftv.news/748192" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Category</label>
                                        <select name="category" class="form-control">
                                            <option value="IPTV Players">IPTV Players</option>
                                            <option value="Utilities & Tools">Utilities & Tools</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Description (Shows on TV Details Sheet)</label>
                                        <textarea name="description" class="form-control" placeholder="Application features and details..."></textarea>
                                    </div>
                                </div>

                                <!-- Icon Upload Section -->
                                <div>
                                    <div class="card" style="background: rgba(255, 255, 255, 0.02); margin-bottom: 20px; border: 1px solid rgba(245, 158, 11, 0.3);">
                                        <div class="card-header" style="background: rgba(245, 158, 11, 0.08);"><h3 class="card-title" style="color: #fbbf24;">🎨 App Icon & TV Artwork</h3></div>
                                        <div class="card-body">
                                            <!-- Dimension Format Guide -->
                                            <div style="background: rgba(0,0,0,0.35); border: 1px dashed rgba(245, 158, 11, 0.4); border-radius: 12px; padding: 12px; margin-bottom: 14px;">
                                                <div style="font-size: 12px; font-weight: 700; color: #fbbf24; margin-bottom: 4px;">📐 Icon Format Needed:</div>
                                                <ul style="font-size: 11px; color: var(--text-secondary); margin-left: 16px; line-height: 1.5;">
                                                    <li><strong>Resolution:</strong> <span style="color:#fff;">512 × 512 px</span> (1:1 Square)</li>
                                                    <li><strong>Minimum:</strong> 256 × 256 px &bull; <strong>Max:</strong> 1024 × 1024 px</li>
                                                    <li><strong>Formats:</strong> PNG (transparent/dark) or JPG</li>
                                                    <li><strong>TV Scale:</strong> Rendered in Royal Media squircle with gold focus halo.</li>
                                                </ul>
                                            </div>

                                            <div style="text-align: center; margin-bottom: 14px;">
                                                <img id="icon-preview-box" src="/app/assets/store_logo.jpg" style="width: 86px; height: 86px; border-radius: 20px; object-fit: cover; border: 2px solid #f59e0b; box-shadow: 0 8px 25px rgba(0,0,0,0.6); background: #0b0e14;">
                                                <div id="icon-size-badge" style="margin-top: 8px; font-size: 11px; font-weight: 700; color: #94a3b8;">
                                                    Required: 512 × 512 px (1:1 Square)
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label" style="text-align: left;">Choose Icon Image</label>
                                                <input type="file" id="file_icon_picker" accept="image/png, image/jpeg, image/webp" class="form-control" onchange="handleIconFileSelect(this)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="background: #f59e0b; color: #000; font-weight: 800; padding: 14px 32px; font-size: 15px; margin-top: 10px;">
                                💾 Save & Broadcast App to All TVs
                            </button>
                        </form>
                    </div>
                </div>

                <script>
                    function handleIconFileSelect(input) {
                        if (input.files && input.files[0]) {
                            const file = input.files[0];
                            const reader = new FileReader();
                            const badge = document.getElementById('icon-size-badge');
                            reader.onload = function(e) {
                                const img = new Image();
                                img.onload = function() {
                                    const w = img.naturalWidth;
                                    const h = img.naturalHeight;
                                    const isSquare = (w === h);
                                    const isOptimal = (w >= 480 && w <= 540 && h >= 480 && h <= 540);
                                    document.getElementById('icon-preview-box').src = e.target.result;
                                    document.getElementById('icon_data_field').value = e.target.result;
                                    if (badge) {
                                        if (isOptimal) {
                                            badge.innerHTML = `<span style="color: #10b981;">✓ Perfect Match: ${w} × ${h} px (1:1 Square)</span>`;
                                        } else if (isSquare) {
                                            badge.innerHTML = `<span style="color: #fbbf24;">✓ Valid 1:1 Square: ${w} × ${h} px</span>`;
                                        } else {
                                            badge.innerHTML = `<span style="color: #f59e0b;">⚠ Detected: ${w} × ${h} px (Non-square. Fits into squircle automatically)</span>`;
                                        }
                                    }
                                };
                                img.src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                </script>
                """
                self.send_html(render_admin_shell("Add New App", add_html, "apps"))
                return

            # Apps Table
            rows = ""
            for a in ROYAL_MEDIA_APPS:
                icon_src = a['icon_url'] if a['icon_url'].startswith('data:') else f"/app/{a['icon_url']}"
                rows += f"""<tr>
                    <td style="display: flex; align-items: center; gap: 12px;">
                        <img src="{icon_src}" style="width: 44px; height: 44px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <div>
                            <strong style="font-size: 15px;">{a['title']}</strong>
                            <div style="font-size: 11px; color: var(--text-secondary);">{a['package_name']}</div>
                        </div>
                    </td>
                    <td><span class="badge badge-primary" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">{a['category_name']}</span></td>
                    <td><strong style="color: #f59e0b; font-family: monospace; font-size: 16px;">{a['version']['downloader_code']}</strong></td>
                    <td><a href="{a['version']['download_url']}" target="_blank" style="color: #74c0fc; text-decoration: none;">{a['version']['download_url']}</a></td>
                    <td><strong>{a['version']['downloads_count']}</strong></td>
                </tr>"""

            table_html = f"""
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📱 Royal Media Catalog ({len(ROYAL_MEDIA_APPS)})</h2>
                    <a href="/admin/apps.php?action=add" class="btn btn-primary btn-sm" style="background: #f59e0b; color: #000; font-weight: 700;">+ Upload New App</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>App & Icon</th><th>Category</th><th>Downloader Code</th><th>Download URL</th><th>Downloads</th></tr>
                        </thead>
                        <tbody>{rows}</tbody>
                    </table>
                </div>
            </div>
            """
            self.send_html(render_admin_shell("Manage Apps & APKs", table_html, "apps"))
            return

        # ==============================================================
        # Admin Notices
        # ==============================================================
        if path == '/admin/notices.php':
            notice_rows = ""
            for n in ROYAL_MEDIA_NOTICES:
                notice_rows += f"""<tr>
                    <td><strong>{n['title']}</strong></td>
                    <td style="font-size: 12px; color: var(--text-secondary);">{n['content'].replace(chr(10), '<br>')}</td>
                    <td>{n.get('date_formatted', 'Recent')}</td>
                </tr>"""

            notices_html = f"""
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">+ Broadcast New Notice</h2></div>
                    <div class="card-body">
                        <form method="POST" action="/admin/notices.php">
                            <div class="form-group">
                                <label class="form-label">Notice Title *</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Royal Media Maintenance Complete" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Announcement Content *</label>
                                <textarea name="content" class="form-control" placeholder="What customers will see inside their TV app..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" style="background: #f59e0b; color: #000; font-weight: 700; width: 100%;">🔔 Broadcast Notice to All TVs</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2 class="card-title">🔔 Published Notices</h2></div>
                    <div class="card-body" style="padding: 0;">
                        <table class="admin-table">
                            <thead><tr><th>Title</th><th>Content</th><th>Date</th></tr></thead>
                            <tbody>{notice_rows}</tbody>
                        </table>
                    </div>
                </div>
            </div>
            """
            self.send_html(render_admin_shell("Notices & Broadcasts", notices_html, "notices"))
            return

        # ==============================================================
        # Public APIs
        # ==============================================================
        if path.endswith('/api/config.php'):
            self.send_json({
                "success": True,
                "config": {
                    "store_name": "Royal Media",
                    "store_subtitle": "All your Royal streaming apps in one place",
                    "store_logo": "assets/store_logo.jpg",
                    "theme_accent_color": "#f59e0b",
                    "theme_glow_color": "#00d2d3",
                    "xtream_enabled": True,
                    "dns_servers": []
                }
            })
            return

        if path.endswith('/api/apps.php'):
            self.send_json({
                "success": True,
                "categories": [
                    {"id": 1, "name": "IPTV Players", "slug": "iptv-players", "sort_order": 1},
                    {"id": 2, "name": "Utilities & Tools", "slug": "utilities", "sort_order": 2}
                ],
                "featured": ROYAL_MEDIA_APPS[:2],
                "apps": ROYAL_MEDIA_APPS
            })
            return

        if path.endswith('/api/notices.php'):
            self.send_json({"success": True, "notices": ROYAL_MEDIA_NOTICES})
            return

        if path.endswith('/api/download_track.php') or path == '/api/download_track.php':
            app_id = int(query.get('app_id', [0])[0])
            engine = query.get('engine', ['turbo'])[0]
            device = query.get('device', [self.headers.get('User-Agent', 'Android TV')])[0]

            matched = None
            for a in ROYAL_MEDIA_APPS:
                if a['id'] == app_id:
                    matched = a
                    break

            if matched:
                matched['version']['downloads_count'] += 1
                client_ip = self.client_address[0] if hasattr(self, 'client_address') else '127.0.0.1'
                REGISTERED_DEVICES.add(f"{client_ip}_{device}")
                import datetime
                REAL_DOWNLOAD_LOGS.append({
                    'app_id': matched['id'],
                    'app_title': matched['title'],
                    'code': matched['version']['downloader_code'],
                    'ip': client_ip,
                    'device': device,
                    'time': datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    'icon_url': matched['icon_url'] if matched['icon_url'].startswith('data:') else f"/app/{matched['icon_url']}"
                })
                self.send_json({'success': True, 'app_id': app_id, 'new_downloads_count': matched['version']['downloads_count']})
            else:
                self.send_json({'success': False, 'error': 'App not found'}, status=404)
            return

        return super().do_GET()

    def do_POST(self):
        url_parts = urllib.parse.urlparse(self.path)
        path = url_parts.path

        # Handle Admin Login POST
        if path == '/admin/login.php':
            content_len = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(content_len).decode('utf-8', errors='ignore')
            data = urllib.parse.parse_qs(body)

            user = data.get('username', [''])[0]
            pwd = data.get('password', [''])[0]

            if user == ADMIN_CREDENTIALS['username'] and pwd == ADMIN_CREDENTIALS['password']:
                self.redirect('/admin/index.php', cookie='admin_session=active; Path=/; Max-Age=86400')
            else:
                self.redirect('/admin/login.php?error=invalid')
            return

        # Handle Profile Credential Update POST
        if path == '/admin/profile.php':
            content_len = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(content_len).decode('utf-8', errors='ignore')
            data = urllib.parse.parse_qs(body)

            new_username = data.get('username', [''])[0].strip()
            curr_pass = data.get('current_password', [''])[0]
            new_pass = data.get('new_password', [''])[0]

            if curr_pass == ADMIN_CREDENTIALS['password']:
                if new_username:
                    ADMIN_CREDENTIALS['username'] = new_username
                if new_pass:
                    ADMIN_CREDENTIALS['password'] = new_pass
                self.redirect('/admin/profile.php?msg=updated')
            else:
                self.redirect('/admin/profile.php?msg=error')
            return

        # Handle Admin Add App Form
        if path == '/admin/apps.php':
            content_len = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(content_len).decode('utf-8', errors='ignore')
            data = urllib.parse.parse_qs(body)

            title = data.get('title', ['New App'])[0]
            code = data.get('downloader_code', ['123456'])[0]
            link = data.get('download_url', [f'http://aftv.news/{code}'])[0]
            package = data.get('package_name', ['com.royalmedia.app'])[0]
            category = data.get('category', ['IPTV Players'])[0]
            icon_data = data.get('icon_data', [''])[0]
            desc = data.get('description', ['Royal Media new application.'])[0]

            # If user uploaded a new icon file, use it directly!
            icon_url = icon_data if icon_data else 'assets/store_logo.jpg'

            new_id = len(ROYAL_MEDIA_APPS) + 1
            ROYAL_MEDIA_APPS.append({
                "id": new_id,
                "title": title,
                "package_name": package,
                "developer": "Royal Media",
                "category_name": category,
                "icon_url": icon_url,
                "banner_url": "assets/screenshot_preview1.jpg",
                "is_featured": True,
                "description": desc,
                "changelog": f"⚡ Downloader Code: {code}\n📱 Full remote support",
                "version": {
                    "version_name": "v1.0",
                    "version_code": 1,
                    "file_size": 45000000,
                    "file_size_formatted": "45.0 MB",
                    "downloads_count": 0,
                    "downloader_code": code,
                    "download_url": link,
                    "mirrors": [
                        {"name": f"Download #1 ({link})", "type": "in_app", "badge": "In-app download", "url": link},
                        {"name": "Download #2 (Direct Link)", "type": "browser", "badge": "Direct mirror", "url": link}
                    ]
                },
                "screenshots": [
                    "assets/screenshot_preview1.jpg",
                    "assets/screenshot_preview2.jpg"
                ]
            })

            self.redirect('/admin/apps.php')
            return

        # Handle Admin Notice Form
        if path == '/admin/notices.php':
            content_len = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(content_len).decode('utf-8', errors='ignore')
            data = urllib.parse.parse_qs(body)

            title = data.get('title', ['Notice'])[0]
            content = data.get('content', [''])[0]

            new_id = len(ROYAL_MEDIA_NOTICES) + 1
            ROYAL_MEDIA_NOTICES.insert(0, {
                "id": new_id,
                "title": title,
                "content": content,
                "date_formatted": "Just Now"
            })

            self.redirect('/admin/notices.php')
            return

        # Handle Client Auth
        if path.endswith('/api/auth.php'):
            content_len = int(self.headers.get('Content-Length', 0))
            post_body = self.rfile.read(content_len).decode('utf-8', errors='ignore')
            try:
                data = json.loads(post_body)
            except Exception:
                data = {}
            username = data.get('username', 'kasun')
            self.send_json({
                "success": True,
                "message": "Sign in successful",
                "user": {
                    "username": username,
                    "account_type": "store",
                    "status": "active",
                    "expiry_date": "2027-10-22",
                    "days_left": 365,
                    "max_devices": 3,
                    "token": "royal_vip_token"
                }
            })
            return

        if path.endswith('/api/download_track.php') or path == '/api/download_track.php':
            content_len = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(content_len).decode('utf-8', errors='ignore') if content_len > 0 else ''
            data = urllib.parse.parse_qs(body)
            query = urllib.parse.parse_qs(url_parts.query)

            app_id = int(data.get('app_id', query.get('app_id', [0]))[0])
            engine = data.get('engine', query.get('engine', ['turbo']))[0]
            device = data.get('device', query.get('device', [self.headers.get('User-Agent', 'Android TV')]))[0]

            matched = None
            for a in ROYAL_MEDIA_APPS:
                if a['id'] == app_id:
                    matched = a
                    break

            if matched:
                matched['version']['downloads_count'] += 1
                client_ip = self.client_address[0] if hasattr(self, 'client_address') else '127.0.0.1'
                REGISTERED_DEVICES.add(f"{client_ip}_{device}")
                import datetime
                REAL_DOWNLOAD_LOGS.append({
                    'app_id': matched['id'],
                    'app_title': matched['title'],
                    'code': matched['version']['downloader_code'],
                    'ip': client_ip,
                    'device': device,
                    'time': datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    'icon_url': matched['icon_url'] if matched['icon_url'].startswith('data:') else f"/app/{matched['icon_url']}"
                })
                self.send_json({'success': True, 'app_id': app_id, 'new_downloads_count': matched['version']['downloads_count']})
            else:
                self.send_json({'success': False, 'error': 'App not found'}, status=404)
            return

        if path.endswith('/api/updates.php'):
            self.send_json({"success": True, "has_updates": False, "updates_count": 0, "updates": []})
            return

        return super().do_POST()

    def send_json(self, data, status=200):
        body = json.dumps(data).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Connection', 'close')
        self.end_headers()
        self.wfile.write(body)

    def send_html(self, html_str):
        body = html_str.encode('utf-8')
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.send_header('Connection', 'close')
        self.end_headers()
        self.wfile.write(body)

class ThreadingServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True
    daemon_threads = True

def run():
    with ThreadingServer(("", PORT), StoreRequestHandler) as httpd:
        print(f"Royal Media server running at http://localhost:{PORT}")
        httpd.serve_forever()

if __name__ == '__main__':
    run()
