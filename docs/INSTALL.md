# Bookexa Installation Guide (cPanel & XAMPP)

This guide walks through installing **Bookexa** cleanly on a live **cPanel shared host** or a local **Windows XAMPP** workstation.

---

## System Requirements
- **PHP**: 8.1+ (PHP 8.2 recommended)
- **Extensions**: `pdo_mysql`, `curl`, `gd` (with **FreeType TTF** & **WebP**), `mbstring`, `fileinfo`, `openssl`, `zlib`
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB 10.4+ (`utf8mb4_unicode_ci`)
- **Web Server**: Apache with `mod_rewrite` enabled (`.htaccess` included)
- **PHP Limits**: `memory_limit >= 128M` (recommended `256M`), `upload_max_filesize >= 16M`

---

## Option A — Installing on cPanel (Production)

1. **Create MySQL Database & User**
   - Open cPanel → **MySQL® Databases**.
   - Create a database (e.g., `cpaneluser_bookexa`) and user, and assign **ALL PRIVILEGES**.
2. **Upload Application Files**
   - Upload the Bookexa ZIP archive into `public_html/` (or your addon domain's document root) and extract it.
   - Ensure hidden files (`.htaccess`, `config/.htaccess`, `storage/*/.htaccess`) are present.
   - Confirm `config/config.php` and `config/installed.lock` do **not** exist yet so the guided installer starts automatically.
3. **Check Folder Permissions (`0755`)**
   - Set directories `config/`, `storage/`, `storage/cache/`, `storage/pages/`, `storage/logs/`, `storage/backups/`, `storage/payment_proofs/`, and `uploads/` to writable (`0755`).
4. **Run the Guided `/install` Setup Wizard**
   - Visit `https://yourdomain.com/install`.
   - **Step 1**: Confirm all server extensions, `memory_limit`, `mod_rewrite`, and writable folders show **OK**.
   - **Step 2**: Enter your cPanel DB Host (`localhost`), DB Name, DB User, and password, and click **Test Database**.
   - **Step 3**: Set **Site Name**, select **Environment = Production**, create your **Super Admin** account, and optionally configure CDN settings.
   - **Step 4**: Optionally enter SMTP credentials and click **Send Test Email** to confirm transactional mail works.
   - **Step 5**: Click **Install Bookexa & Show Go-Live Checklist**.
   - Bookexa writes `config/config.php` (chmod `0640`), imports `database/bookexa.sql`, marks all PHP migrations applied, and locks `/install` (`403 Forbidden`) via `config/installed.lock`.
5. **Post-Install Go Live Tasks**
   - Verify HTTPS (cPanel **AutoSSL**) is active.
   - Log in at `https://yourdomain.com/admin`, open **Settings** to configure your bKash/Nagad/Rocket numbers, white-label colors/logo, and Bangla/English legal pages.
   - (Optional CDN Cron): Add a cPanel cron job every 5–10 minutes calling:
     ```bash
     php /home/YOURUSER/public_html/cron/sync_media.php
     ```

---

## Option B — Installing on XAMPP (Local Windows)

1. Start **Apache** and **MySQL** in the **XAMPP Control Panel**.
2. Extract Bookexa into `C:\xampp\htdocs\bookexa`.
3. Visit `http://localhost/bookexa/install`.
4. Under Step 2 (MySQL), use:
   - Host: `127.0.0.1`, Port: `3306`, User: `root`, Password: *(empty on default XAMPP)*, DB Name: `bookexa`.
   - Tick **Create DB if missing** and click **Test Database**.
5. Under Step 3, choose **Environment = Local Development** (optionally tick **Seed sample catalog & demo books** to preview demo content locally) and complete installation.
6. Visit `http://localhost/bookexa/` (Storefront) and `http://localhost/bookexa/admin` (Admin Panel).
