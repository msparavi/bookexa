# Bookexa Security Architecture

Bookexa is engineered for shared hosting and commercial deployments without third-party dependencies.

## Key Security Controls
- **Protected Content Delivery**: Original Drive URLs stay strictly server-side. Paid and prepared PDFs are served page-by-page via `/api/page` after verifying signed HMAC-SHA256 reader tokens (`ReaderTokens`) bound to `user_id`, `item_id`, `session_id`, and single-session `active_session_token`, overlaid with cached per-user forensic GD watermarks.
- **Access Control & Secrets**:
  - Passwords hashed with PHP `PASSWORD_BCRYPT`.
  - Admin TOTP 2FA secrets encrypted at rest with `AES-256-GCM` derived via HKDF/app secret + replay-step protection (`totp_last_step`).
  - Role-based authorization enforced on both navigation and POST/API handlers (`ADMIN_ROLE_PERMISSIONS`).
  - Production boot rejects missing or placeholder `app_secret` values immediately (`HTTP 500`).
- **Injection & Header Protections**:
  - 100% PDO native prepared statements (`ATTR_EMULATE_PREPARES => false`) plus `escape_like()` on wildcard `LIKE` searches.
  - Output escaping (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`) on all views.
  - Strict Content-Security-Policy (`script-src 'self'`, `frame-ancestors 'none'`), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and CSRF tokens across all state-changing forms/APIs.
  - Safe redirect helper (`safe_internal_path()`) rejecting external hosts, `//`, `/\`, encoded slashes, and control characters.
- **Concurrency & Anti-Abuse**:
  - Transactional `SELECT ... FOR UPDATE` locks + atomic SQL guards prevent negative stock or coupon over-redemption.
  - Unique composite index on `(payment_method, transaction_id)` stops duplicate manual TrxIDs.
  - Rate limiting on login, OTP SMS budgets, guest checkout (IP + phone), API routes, and reader page burst/session anomaly detection.
- **Clean Packaging**: `packager_verify_zip_allowlist()` blocks exporting `config/config.php`, `config/installed.lock`, `.git`, logs, or raw `/storage/` content.
