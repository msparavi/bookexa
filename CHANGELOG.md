# Changelog

All notable changes to **Bookexa** are documented in this file.

## [2.4.0] - 2026-11-26
### Added
- **Productization & Commercial Readiness**:
  - Plain-PHP automated test suite (`php tests/run.php`) + GitHub Actions CI with MySQL 8.0 and full `php -l` syntax checks.
  - Clean modular architecture: `routes/` (`public.php`, `user.php`, `reader.php`, `admin.php`, `api.php`) and `includes/services/` (`ReaderTokens`, `DriveFetcher`, `Watermarker`, `PageServer`, `OrderService`, `CouponService`, `SearchService`).
  - Full Admin **White-Label Branding** controls: primary/accent colors, offline font pairs, footer text (BN/EN), social links, logo/favicon, legal pages, default language, Shop & SMS toggles, and **Demo Mode**.
  - Guided 5-step `/install` Setup Wizard with environment checks, database auto-creation/connection test, admin setup, optional SMTP test, optional CDN setup, and Go Live checklist.
  - Admin **About & Updates** screen (`/admin/updates`) showing app/runtime version info, pending database migrations, and one-click Backup Before Update.
  - Complete `docs/` manual (`INSTALL.md`, `ADMIN_GUIDE.md`, `SECURITY.md`, `PRIVACY.md`, `FAQ.md`), `CHANGELOG.md`, commercial single-domain `LICENSE`, and strict ZIP packager allowlist verification.

## [2.3.0] - 2026-11-25
### Added
- Complete **Physical Book Shopping Module** (`/shop`, `/shop/{slug}`, `/cart`, mini-cart drawer, `/shop/checkout`, `/shop/track`, printable QR `/shop/orders/{code}/invoice`, returns, verified buyer reviews, delivery zone & per-district overrides, stock audit logs, CSV import/export, packing slips).

## [2.2.0] - 2026-11-22
### Added
- Idempotent PHP database migration runner (`003`–`011`) and `/admin/updates`.
- Hardened open-redirect protection (`safe_internal_path`), escaped `LIKE` queries (`escape_like`), strict production `app_secret()` enforcement, AES-256-GCM encrypted Admin TOTP secrets, OTP budgets, and dynamic `robots.txt` / `X-Robots-Tag`.

## [2.1.0] - 2026-11-18
### Added
- Bilingual URL structure (`/en/...`) with `hreflang` alternates, child sitemaps, JSON-LD structured data, watermark overlay caching (`storage/cache/wm/`), WebP output, and bulk admin productivity tools.

## [2.0.0] - 2026-11-12
### Added
- Initial Bookexa Digital Library, manual bKash/Nagad/Rocket order workflow, protected page preparation, and in-browser canvas reader.
