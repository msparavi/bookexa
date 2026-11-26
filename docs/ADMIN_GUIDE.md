# Bookexa Administrator Guide (বাংলা ও English)

Welcome to the **Bookexa Admin Control Panel** (`/admin`). This guide covers day-to-day publishing, e-commerce operations, branding, and security in both **English** and **বাংলা**.

---

## 1. Folders & PDF Publishing | ফোল্ডার ও পিডিএফ প্রকাশনা

- **Folders (`/admin/folders`)**:
  - Build unlimited nested folders (e.g., `Academic → HSC → Physics`). Set Bangla & English names, cover thumbnails, SEO meta fields, and reorder cleanly.
  - *[Screenshot Placeholder: Nested Folder Tree & Reorder Mode in Admin]*
- **PDF Manager (`/admin/pdfs`) & Prepare Protected Pages**:
  - Upload a PDF from your device or paste a shared Google Drive file URL (`Anyone with the link`).
  - For **paid PDFs** (and recommended for all books), click **Prepare Protected Pages** (`/admin/prepare-pages`). Your browser renders crisp raster images + extracts table of contents + search text index, storing protected WebP/JPG pages inside `storage/pages/{item_id}/`. Readers never receive the raw PDF file.
  - Use **Prepare Queue (`/admin/prepare-queue`)** to batch-process multiple unprocessed books automatically, or **CSV Import (`/admin/import-csv`)** for bulk catalog creation.
  - *[Screenshot Placeholder: Prepare Protected Pages Progress & Auto Cover]*

---

## 2. Digital & Physical Shop Management | ডিজিটাল ও প্রিন্টেড শপ ব্যবস্থাপনা

- **PDF Orders (`/admin/orders`)**:
  - Review manual bKash/Nagad/Rocket TrxIDs & optional screenshots, watch for red **Duplicate TrxID** badges, add public notes (reject reasons) or internal staff notes, bulk approve/reject, or export CSV.
- **Physical Book Shop**:
  - **Categories (`/admin/shop-categories`)**: Nested physical book categories with bilingual titles & thumbnails.
  - **Products (`/admin/shop-products`)**: Multi-image galleries (select cover image), SKU, ISBN, weight, low-stock alerts, stock adjustment logs, and CSV import/export.
  - **Shop Orders (`/admin/shop-orders`)**: Manage `pending → confirmed → packed → shipped → delivered`, enter courier name & tracking code, print bilingual **Customer Invoices** and **Packing Slips** (`/admin/shop-packing-slip`), and process customer **Return Requests** (with optional restock).
  - **Delivery Settings (`/admin/delivery-settings`)**: Enable/disable Shop navigation links, toggle **Cash on Delivery (COD)**, set Inside/Outside Dhaka charges, per-district overrides, free-delivery minimum, estimated days, and return window days.
  - *[Screenshot Placeholder: Shop Orders Manager with Courier Tracking & Return Review]*

---

## 3. White-Label Branding & Demo Mode | হোয়াইট-লেবেল ও ডেমো মোড

Open **Admin → Settings (`/admin/settings`)**:
- **Identity & Theme**: Upload Logo/Favicon, set Primary & Accent brand hex colors, choose offline Bengali/English Font Pairing, set Custom Footer text (BN/EN), and add Facebook/YouTube/Telegram/WhatsApp/Instagram links.
- **Modules & Communications**: Toggle Default Language (`bn`/`en`), Shop Module, SMS OTP Login, Manual Payment numbers / Automatic Gateway Driver, SMTP Email (with live test), and all Legal Pages.
- **Demo Mode**: Enable **Demo Mode** to display a safe demo banner and prevent visitors or demo testers from deleting records, changing passwords/2FA, rotating cron keys, or exporting backups/secrets.
- *[Screenshot Placeholder: Admin Settings White-Label & Branding Panel]*

---

## 4. Security, Roles & Backups | নিরাপত্তা, ভূমিকা ও ব্যাকআপ

- **Admin Roles (`/admin/admins`)**:
  - `superadmin` (Full Access), `editor` (Content, PDFs, Shop Categories & Products), `support` (Orders, Shop Orders, Inbox, Read-only Users), `analyst` (Reports & Analytics).
- **Account Security (`/admin/account-security`)**: Enable Google Authenticator TOTP 2FA (encrypted at rest + 8 backup codes), configure optional IP Allowlist, and sign out other sessions.
- **About & Updates (`/admin/updates`)**: Check Bookexa version, PHP/DB environment, create a one-click **Backup Before Update**, and apply idempotent schema migrations safely.
- **Media & Export Package (`/admin/media`)**: Sync public `/uploads/` to CDN (`filesystem`, `ftp`, `http`) or export a clean verified portable ZIP package.
