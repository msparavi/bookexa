# Bookexa — Digital Books & Protected PDF Platform

Plain **PHP 8 + MySQL (PDO) + HTML5 + CSS3 + Vanilla JS** platform with unlimited nested folders, bilingual Bangla/English UI, manual bKash/Nagad/Rocket verification, and a hardened server-streamed PDF.js canvas reader.

## Local Setup (XAMPP on Windows)
1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. In **phpMyAdmin** (`http://localhost/phpmyadmin/`), create database `bookexa` (`utf8mb4_unicode_ci`) and import `database/bookexa.sql` (plus `database/demo_seed.sql` ONLY if you want local sample data).
3. Open **`http://localhost/bookexa/`** (Admin Panel: **`http://localhost/bookexa/admin`**).

---

## Production cPanel Deployment Checklist
1. **PHP Requirements:**
   - **PHP 8.1+** (recommended **PHP 8.2**)
   - Required PHP extensions (verify in cPanel *Select PHP Version*): **`pdo_mysql`**, **`curl`**, **`gd`** (with WebP + FreeType TTF support), **`mbstring`**, **`fileinfo`**, **`openssl`**, **`json`**.
2. **Upload Files:**
   - Upload all application files to `public_html/` (or your addon subdomain document root), ensuring `.htaccess`, `config/.htaccess`, `storage/pages/.htaccess`, `storage/cache/.htaccess`, `storage/logs/.htaccess`, `robots.txt`, and `manifest.webmanifest` are uploaded.
   - Omit `database/demo_seed.sql`, `config/config.php`, and `config/installed.lock` when uploading for a fresh first-run setup.
3. **Database & First-Run Installer (`/install`):**
   - In cPanel → *MySQL Databases*, create a UTF-8 (`utf8mb4_unicode_ci`) database and user with `ALL PRIVILEGES`.
   - Set folder permissions before running setup:
     - Directories: **`0755`** (writable `config/`, `storage/`, `storage/cache/`, `storage/pages/`, `storage/logs/`, `uploads/`).
     - Files: **`0644`** (after install, `config/config.php` can be **`0640`** or **`0440`**).
   - Visit **`https://yourdomain.com/install`** to test environment extensions, import `database/bookexa.sql` (clean schema without demo users), generate a fresh cryptographic `app_secret`, create your Super Admin account, write `config/config.php`, and create `config/installed.lock`. `/install` automatically returns `403 Forbidden` once locked.
   - *(Manual SQL import alternative: import `database/bookexa.sql` via phpMyAdmin, set `'app_env' => 'production'`, update `db` credentials and `security.app_secret` in `config/config.php`, and create an empty `config/installed.lock`.)*
4. **HTTPS, Canonical Host & Security Headers:**
   - `.htaccess` enforces HTTPS + canonical non-`www` host (outside `localhost`/`127.0.0.1`), sends `Content-Security-Policy` (`script-src 'self'`, `frame-ancestors 'none'`), `X-Frame-Options: DENY`, `Permissions-Policy`, and `Strict-Transport-Security` over HTTPS, plus Gzip (`mod_deflate`) and asset browser caching (`mod_expires`).
   - `/demo` and `/styleguide` are automatically blocked (`403`) in production mode.
   - New admin accounts created with *"Require password change on next login"* are redirected to `/admin/account-security` until they set a unique password.
5. **Email (Built-in SMTP Client) & Legal Notices:**
   - Sign in to `/admin/settings` and configure **SMTP Email Settings** (host, port, encryption `STARTTLS`/`SSL`, username, password, from name/address) and verify using **Send test email**. Bookexa sends password resets, order confirmations, and order approvals/rejections through the built-in socket SMTP client (no `mail()` or Composer needed).
   - Review & customize bilingual **Legal Pages** (`Terms`, `Privacy Policy`, `Refund Policy`, `Copyright & Watermark Notice`) in `/admin/settings`.
6. **Automatic CDN Image Storage & Sync (`/admin/media`):**
   - Bookexa keeps every public image (`uploads/{folders|covers|banners|products|branding}/...`) locally in `/uploads` AND can automatically sync and serve them from a separate CDN origin (`https://cdn.yourdomain.com/bookexa/uploads/...`). Relative paths (`uploads/...`) are stored in the database so the entire site remains portable.
   - **cPanel Subdomain Setup (`filesystem` driver — fastest on same host):**
     1. In cPanel → *Domains*, create subdomain `cdn.yourdomain.com` pointing to `/home/USER/cdn.yourdomain.com`.
     2. In `config/config.php`, set:
        - `'cdn' => ['enabled' => true, 'driver' => 'filesystem', 'base_url' => 'https://cdn.yourdomain.com/bookexa', 'path' => '/home/USER/cdn.yourdomain.com', 'sync_batch' => 10]`
     3. Open `/admin/media`, click **Test CDN Connection** (writes `/home/USER/cdn.yourdomain.com/bookexa/uploads/.htaccess` automatically), then click **Sync All Pending Now**.
   - **Local XAMPP Setup (`filesystem` driver):**
     1. Create `C:/xampp/htdocs/bookexa-cdn`.
     2. In `config/config.php`, set `'enabled' => true, 'driver' => 'filesystem', 'base_url' => 'http://localhost/bookexa-cdn/bookexa', 'path' => 'C:/xampp/htdocs/bookexa-cdn'`.
   - **Remote CDN Server (`ftp` or `http` driver):**
     - **FTP/FTPS:** Set `'driver' => 'ftp'` with `'ftp' => ['host' => ..., 'port' => 21, 'user' => ..., 'pass' => ..., 'root' => '/public_html', 'ssl' => true]`.
     - **Signed HTTP Push (`cdn-receiver/receive.php`):** Upload `cdn-receiver/receive.php` to your CDN server alongside an Apache/Nginx root, configure `BOOKEXA_CDN_SECRET` (matching `config.php`'s `cdn.http.secret`), and set `'driver' => 'http'`, `'http' => ['endpoint' => 'https://cdn.yourdomain.com/receive.php', 'secret' => '...']`.
   - **Cron Background Sync (Optional):** Add a cPanel cron job hitting `php /home/USER/public_html/cron/sync_media.php` (CLI) or `https://yourdomain.com/cron/sync_media.php?key=<HMAC>` (where `<HMAC>` is `hash_hmac('sha256', 'cron_sync_media', $config['security']['app_secret'])`) every 5–10 minutes to catch any queued uploads automatically. Even without cron, admin browsing triggers lazy batch sync (`sync_batch`).
   - **Disabling CDN or Changing CDN Domain:** Set `'cdn' => ['enabled' => false]` (or update `'base_url'`) in `config/config.php`. All images immediately fall back to local `/uploads/...` because only relative paths are stored in the database. Before deactivating an old external CDN, use **Admin → Media & CDN → Pull CDN → Local** to ensure `/uploads/` is 100% up to date.
   - **Same-Server Subdomain vs Cloudflare / Real Edge Caching:** Hosting `cdn.yourdomain.com` on the same cPanel server separates static cookies and paths cleanly, while putting Cloudflare (or BunnyCDN / CloudFront pull-zone) in front of `cdn.yourdomain.com` adds real global edge caching (`Cache-Control: public, max-age=31536000, immutable` is already sent by `uploads/.htaccess`).
   - **Never Stored on CDN:** Protected rendered book pages (`storage/pages/`), dynamic GD watermarks (`/api/page`), cache, logs, `config/config.php`, and `config/installed.lock` stay strictly private on the origin server.
7. **Maintenance, Backups & Site Portability Packager:**
   - Bookexa runs lazy hourly cleanup (`reader_tokens`, `password_resets`, `rate_limits`, >90d logs, expired cache bins) automatically during web traffic.
   - Use **Admin → Settings → Backup database (.sql)** or **Admin → Media & CDN → Export Site Package (.zip)** (Super Admin) to download a portable ZIP archive (`uploads/`, code, `database/bookexa.sql` Clean or Full Snapshot DB dump, and `config/config.sample.php`—excluding `storage/` and secrets).

---

## Security Notes (What Is Protected & Known Limits)

### What Is Protected
- **Zero Drive Link Exposure:** Google Drive URLs (`items.drive_link`, `items.sample_drive_link`) stay on the server and never appear in HTML, JS, network responses, cache filenames, or error messages.
- **Zero Full-PDF Delivery (Protected Page Mode):** For prepared books (mandatory for all **paid** items), the original PDF never reaches the reader's browser. Admin prepares pages once via **Admin → Prepare Protected Pages** (rendered locally in the admin browser via PDF.js at ~1400px wide WebP and stored under `storage/pages/{item_id}/`, blocked by `.htaccess`). Readers receive individual pages from `/api/page?item=ID&page=N`.
- **Dynamic Server-Side GD Watermark:** `/api/page` uses PHP **GD** to burn the logged-in reader's name, phone/email, user ID, and timestamp diagonally across every page image on the fly before returning it with `no-store` headers and zero public caching.
- **Signed Tokens, Rate Limits & Anomaly Detection:** Reader APIs enforce authentication, ownership/free/sample eligibility, HMAC-SHA256 tokens bound to user, PHP session, and active session token (`409 SESSION_KICKED` on concurrent logins), `X-Bookexa-Reader` + same-origin headers, 60 pages/min rate limiting, server-side sample page bounds (`page <= sample_pages` → `403 PAGE_OUT_OF_RANGE`), fast sequential burst detection (`fast_sequential_burst`), and rapid multi-session switching alerts (`many_sessions_anomaly`) in `reading_logs` so Admin can block abusive accounts.
- **Free Item Fallback & Smart Deterrence:** An optional `free_pdfjs_fallback` setting allows un-prepared **free** items only to fall back to single-use token PDF.js streaming (`slice_pdf_first_pages()` sample enforcement). Blocks right-click/text-select/copy/print shortcuts, hides under `@media print`, delays desktop blur shield cleanly, and avoids false mobile blur triggers from notification shades/address bars.

### Known Limits (Important)
- **Screenshots & Phone Cameras:** Screenshots, screen recording tools, and external phone/camera photos of a screen can **never** be fully blocked by any website or browser. The per-user diagonal watermark burned into every image pixel exists for forensic traceability if a copy leaks.
- **Server Requirement:** Dynamic page watermarking requires the PHP **GD extension** (with WebP/JPEG and FreeType/TTF support).
- **Compressed PDF 1.5+ `/XRef` Streams in Legacy Fallback:** If using the legacy free PDF.js fallback with `?sample=1`, `slice_pdf_first_pages()` trims standard `xref` PDFs; for PDF 1.5+ `/Type /XRef` files, either prepare protected pages in Admin or provide a separate sample Drive link.
