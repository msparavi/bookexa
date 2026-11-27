# Changelog

All notable changes to **Bookexa** are documented in this file.

## [2.4.1] - 2026-11-27
### Fixed
- **Fresh-Install SQL (`database/bookexa.sql`)**: Removed duplicate `who_for_bn`, `who_for_en`, and `faqs_json` column definitions inside the `items` `CREATE TABLE` statement so fresh imports into empty MySQL 8 and MariaDB 10.6 databases succeed cleanly without `#1060 Duplicate column name`. Added `tests/schema_check.php` to validate every `CREATE TABLE` block against duplicate column names and non-InnoDB engines.
- **Migration `005_security_reliability_fixes` FK Safety (`errno 150`)**: Ensured parent tables (`users`, `folders`, `items`) exist with `ENGINE=InnoDB` before creating `reading_daily_usage`, inspected exact parent `COLUMN_TYPE` definitions (`mig_column_type()`) so `user_id` and `item_id` match referenced signedness/width cleanly, and separated FK creation (`mig_add_foreign_key_if_missing()`) with `information_schema` existence checks so `005` cannot leave a half-applied state.
- **Root-Cause Explanations for Fixed Test Failures**:
  1. **`catalog.json` / `guides.json` structured feeds test**: `database/bookexa.sql` ships zero sample books (`items`) and zero demo `guides` for production safety, while `tests/run.php` asserted `!empty($catFeed['items']) && !empty($gdFeed['guides'])` assuming seeded rows already existed. Updated the test in `tests/run.php` to seed temporary active folder/book/guide fixture rows (and clean them up afterward) so feed validation is self-contained on empty CI databases as well as populated sites.
  2. **IndexNow hosted `.txt` verification key URL (`growth_indexnow_key()` / `site_setting()`)**: **Code bug** — `site_setting()` cached all `settings` rows in a per-request static array without invalidation on write. On a fresh database where `indexnow_key` was not yet stored, `growth_indexnow_key()` generated and inserted a key on its first call, then `growth_indexnow_key_url()` called `growth_indexnow_key()`, read an empty `''` from the stale `site_setting()` cache, and generated a second random key. Also fixed CLI `BASE_PATH` resolution in `includes/bootstrap.php` when `base_url`/`base_path` are empty so `BASE_URL` never incorporates OS test runner directory paths. Added write-through cache invalidation (`site_setting_set()` / `site_setting_store_ref()`) and per-request memoization in `growth_indexnow_key()`.
- **In-Book Reader Search (`/api/reader-search`)**: Standardized minimum query length to `2` characters uniformly for both Bangla and English (`PageServer::searchPages()`) and added regression coverage confirming `%` and `_` LIKE wildcards are escaped.

### Added
- Automated CI jobs for empty-database import and twice-run migration idempotency verification on both **MySQL 8.0** and **MariaDB 10.6** (`.github/workflows/ci.yml`).

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
