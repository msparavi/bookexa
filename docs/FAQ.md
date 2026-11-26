# Bookexa Troubleshooting & FAQ

### 1. Google Drive returns "Quota Exceeded" or "Virus Scan Warning" on large PDFs
- Ensure the Drive file sharing permission is set to **Anyone with the link (Viewer)**.
- For files over ~100 MB or heavy traffic, Drive sometimes throttles anonymous web downloads. Go to **Admin → Settings → Google Drive API Key**, enter a read-only Google Cloud Drive v3 API key, and Bookexa will stream cleanly via the official Drive v3 API (`supportsAllDrives=true`).
- Recommended best practice: run **Admin → Prepare Protected Pages** once per book so readers load cached protected page images directly from your server without ever hitting Drive per reader.

### 2. Watermarks show fallback pixel font or WebP images fail to generate
- `/api/page` uses PHP's **GD extension** with **FreeType (`imagettftext`)** and **WebP (`imagewebp`)**.
- Check in **cPanel → Select PHP Version → Extensions** that **`gd`** is enabled (along with FreeType & WebP libraries). Bookexa places a bundled Bangla/Latin TTF font inside `assets/fonts/` and safely falls back to JPG / GD bitmap fonts if a server lacks WebP or FreeType, though enabling full `gd` produces cleaner anti-aliased watermarks and smaller files.

### 3. Emails (Order Status, Password Reset, Support Replies) are not sending
- Open **Admin → Settings → SMTP Email Settings**.
- Do not rely on PHP `mail()`; configure a real SMTP host (e.g., cPanel mail host `mail.yourdomain.com` on port `465` with **SSL** or port `587` with **STARTTLS**), full mailbox username, password, and matching From Address.
- Click **Send test email** to see exact SMTP handshake diagnostics. Some budget shared hosts block outbound port `587` to external Gmail/Mailgun servers — if so, use localhost/cPanel's own SMTP server or port `465`/`2525` as allowed by your host.

### 4. Installer or Browser PDF Preparation hits "Allowed memory size exhausted"
- Increase `memory_limit` to `256M` (or `512M` temporarily) and `max_execution_time` to `120` in cPanel **MultiPHP INI Editor**.
- During browser page preparation, PDF.js renders pages in your own computer's browser and uploads 1 page at a time to `/admin/api/upload-page`, keeping origin server RAM low even for 400+ page textbooks.

### 5. Subpages like `/pdf`, `/shop`, or `/admin` return HTTP 404 on Apache / XAMPP
- Confirm Apache **`mod_rewrite`** is enabled and `.htaccess` exists in the Bookexa root directory.
- On XAMPP or custom Apache vhosts, check `AllowOverride All` is set in `httpd.conf` so Apache honors Bookexa's `.htaccess` rewrite rules.
