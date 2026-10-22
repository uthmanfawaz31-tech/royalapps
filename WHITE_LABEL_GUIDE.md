# Next-Gen TV App Store: Complete White-Label & Free Hosting Guide

This guide gives you step-by-step instructions to:
1. Deploy your PHP Backend & Admin Panel on **100% Free Hosting** in 2 minutes.
2. Rebrand the Android TV & Fire TV App using **MT Manager** or **APKTool** (No Java/Kotlin source code needed).
3. Connect your **Xtream Codes IPTV server** or custom store accounts.
4. Set up **AFTVnews Downloader codes** and QR code installation.

---

## Part 1: Deploying to Free PHP Hosting (2-Minute Setup)

Your backend has been specifically engineered with **zero external dependencies** and uses **SQLite** by default (or MySQL if preferred), making it 100% compatible with free hosts such as:
- **InfinityFree** (Recommended: free unlimited disk & PHP support)
- **000webhost**
- **FreeHosting.com**
- **AwardSpace**
- Any cPanel, DirectAdmin, or shared web hosting.

### Step 1: Upload Files
1. Open your hosting File Manager or FTP client (e.g. FileZilla).
2. Navigate to your website root directory (`public_html` or `htdocs`).
3. Upload all files and folders from `newappstore/`:
   ```
   public_html/
   ├── admin/
   ├── api/
   ├── app/
   ├── data/
   ├── includes/
   ├── uploads/
   ├── config.php
   ├── dl.php
   ├── index.php
   └── install.php
   ```

### Step 2: Set Folder Permissions (If required)
Ensure the following folders are writable (`755` or `777`):
- `data/` (Where your zero-setup SQLite database `store.sqlite` lives)
- `uploads/` (Including `uploads/apks/`, `uploads/icons/`, `uploads/screenshots/`)

> [!NOTE]
> The `data/.htaccess` file is pre-configured to block public web access to `store.sqlite` automatically for maximum database protection.

### Step 3: Run the 2-Minute Web Installer
1. Open your web browser and navigate to:
   ```
   https://yourdomain.com/install.php
   ```
2. The installer will perform pre-flight checks (PHP >= 7.4, PDO SQLite, write permissions).
3. Enter your desired:
   - **Store Brand Name** (e.g. `RTX App Store` or `Royal Media`)
   - **Admin Username** (default: `admin`)
   - **Admin Password** (default: `admin123`)
4. Click **"Run 2-Minute Installation"**.
5. You are finished! The installer automatically creates all tables, sample apps, categories, Kasun demo user, and notices.

---

## Part 2: Admin Panel Features & Daily Operation

Access your Admin Panel at:
```
https://yourdomain.com/admin/
```

### 1. Drag & Drop APK Upload & Auto-Parser (`admin/apps.php`)
- Simply drag any Android `.apk` file into the upload box.
- The built-in pure PHP parser automatically extracts:
  - App Name & Package Name (e.g. `com.waveiptv.player`)
  - Version Name & Version Code (e.g. `v2.0` / `20`)
  - Extracts the high-res app launcher icon PNG
  - Calculates file size and SHA-256 integrity checksum.
- You can toggle **"⭐ Show in New arrivals Hero Carousel"** to feature the app on TV home screens.

### 2. AFTVnews Downloader Codes & Mirrors
- Every app version automatically receives a 6-to-7 digit Downloader code (e.g. `5413872`).
- On Fire TV / Android TV devices:
  1. Customers open the **Downloader** app by AFTVnews.
  2. Type your code (e.g. `5413872`).
  3. Click **Go** — the APK downloads and opens the Android installer immediately!
- You can configure:
  - **Main Server URL**: local stream or direct APK URL.
  - **Website Mirror**: opens external browser if needed.
  - **Backup Mirror 2**: secondary backup download link.

### 3. One Login: Store Users & Xtream Codes IPTV (`admin/users.php`)
- **Store Accounts**: Create accounts with an **Expiry Date** 📅 and **Device Limit** 📱 (e.g., 2 devices). The system tracks active device IDs automatically and prevents unauthorized sharing.
- **Xtream Codes IPTV Auth**:
  1. In `admin/users.php`, enable the checkbox: *"Allow Customers to Log In with IPTV Accounts"*.
  2. Enter your IPTV Server URL (e.g. `http://line.provider.com:8080`).
  3. Now your customers can sign into the store using their existing IPTV username and password!

### 4. Multiple DNS Failover (`admin/dns.php`)
- Add your primary and backup domains (e.g. `https://api.yourdomain.com`, `http://backup.yourdomain.com`).
- The Store App tries each DNS automatically until one works.
- Click **"⚡ Test Ping"** in the panel to measure real-time latency and server status.

### 5. In-App Notices & OneSignal Push (`admin/notices.php`)
- Publish news and announcements with 1 click.
- Optional OneSignal REST API integration to broadcast push notifications to all installed TVs and devices.

---

## Part 3: Rebranding the Android TV & Fire TV App (No Source Code Needed)

You can rebrand the client APK using either **MT Manager** (directly on your Android phone/TV) or **APKTool** on a PC.

### Option A: Using MT Manager (Fastest — 3 minutes on phone/tablet)
1. Install **MT Manager** from [mt2.cn](https://mt2.cn) or your trusted source.
2. Open MT Manager and locate your base Store APK.
3. Tap the APK and select **View**.

#### 1. Changing the App Name:
- Open `resources.arsc` -> select **Arsc Editor**.
- Search for the string: `app_name`.
- Change `RTX App Store` to your store name (e.g. `Royal Media Store`).
- Save and exit.

#### 2. Changing the App Icon & TV Banner:
- In MT Manager, open `res/mipmap-xxhdpi/` or `res/drawable-xhdpi/`.
- Replace `ic_launcher.png` with your square logo (512x512 PNG).
- Replace `banner.png` or `tv_banner.png` with your TV banner (1920x1080 or 320x180 PNG).

#### 3. Updating the Hidden / Encrypted Server URL:
- In MT Manager, open `classes.dex` -> select **Dex Editor Plus**.
- Search for: `https://demo8.rtxrebrands.com` or `DEFAULT_API_URL`.
- Replace it with your hosting domain:
  ```
  https://yourdomain.com/api
  ```
- *Optional Encryption*: In `admin/branding.php`, copy your **Encrypted Panel Token** and paste it into the encryption slot in the smali config.
- Save, re-compile DEX, and sign the APK with MT Manager's built-in one-tap signer.

---

### Option B: Using APKTool (Command Line on PC)

1. Decompile the APK:
   ```bash
   apktool d store_app.apk -o store_app_src
   ```

2. Edit App Name:
   - File: `store_app_src/res/values/strings.xml`
   - Modify:
     ```xml
     <string name="app_name">Your Store Name</string>
     ```

3. Replace Assets:
   - App Icon: `store_app_src/res/mipmap-xxhdpi/ic_launcher.png`
   - TV Banner: `store_app_src/res/drawable-xhdpi/banner.png`

4. Edit API Endpoint:
   - File: `store_app_src/res/values/strings.xml` or search in smali files:
     ```xml
     <string name="api_url">https://yourdomain.com/api</string>
     ```

5. Rebuild and Sign:
   ```bash
   apktool b store_app_src -o your_rebranded_store.apk
   uber-apk-signer -a your_rebranded_store.apk
   ```

---

## Part 4: Remote Control (D-Pad) Key Codes Reference

The store web client (`app/index.html` & `app/app.js`) includes a built-in spatial D-Pad navigation engine for Fire TV and Android TV remotes:

| Button | KeyCode | Action |
|---|---|---|
| **D-Pad UP** | `38` / `19` | Navigates to visually closest element above |
| **D-Pad DOWN** | `40` / `20` | Navigates to visually closest element below |
| **D-Pad LEFT** | `37` / `21` | Navigates to previous tab or left card |
| **D-Pad RIGHT** | `39` / `22` | Navigates to next tab or right card |
| **OK / Center** | `13` / `23` | Selects / Clicks the highlighted glowing button |
| **BACK / Return** | `27` / `4` / `10009` | Closes modals or returns to the Home screen |

---

## Part 5: Testing Your Store Locally Right Now

You can run a local test server using Python:
```powershell
python -m http.server 8080 --directory "c:\Users\OFFICE\Downloads\newappstore"
```
Then visit:
- TV Client: `http://localhost:8080/app/index.html`
- Downloader Test: `http://localhost:8080/dl.php?app=1`
- Admin Panel: `http://localhost:8080/admin/index.php` (on any PHP server)
