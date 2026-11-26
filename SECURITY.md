# Security Policy & Protections

## Reporting a Vulnerability

If you discover a security vulnerability in Bookexa, please report it responsibly by emailing the site security contact (configured as `contact_email` in Settings, default `support@bookexa.com`) with:

- A clear description of the vulnerability and affected route or component.
- Steps to reproduce or a minimal proof of concept.
- Potential security impact.

Please allow reasonable time to investigate and release a fix before any public disclosure.

---

## Implemented Security Protections

1. **Fail-Closed Secret Enforcement (`app_secret()`)**
   - Cryptographic operations require a non-placeholder `security.app_secret` of at least 32 characters in `config/config.php` (generated with `random_bytes()` at install time).
   - Missing, weak, or default `CHANGE_ME_ON_INSTALL` secrets fail closed with an error log entry and HTTP 500 response—zero hardcoded fallback secrets exist in the codebase.

2. **Content Security Policy, CSRF & Session Hardening**
   - Strict Content Security Policy (`script-src 'self'` without `unsafe-inline`), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, HttpOnly/SameSite cookies, and strict session mode.
   - Constant-time CSRF token validation on all state-changing routes and admin endpoints.

3. **Open Redirect Protection (`safe_internal_path()`)**
   - All user-supplied redirect parameters (`next`, `return`, `redirect`, `_return`, login, register, logout, and language switch) are normalized, multi-pass URL-decoded, and strictly constrained to single-leading-slash relative internal paths without backslashes, schemes, hosts, or control/whitespace characters.

4. **Admin Authentication & 2FA Hardening**
   - Passwords hashed with bcrypt (`PASSWORD_BCRYPT`).
   - Optional Google Authenticator TOTP 2FA secrets (`admins.totp_secret`) are encrypted at rest with **AES-256-GCM** using a key derived via HKDF-SHA256 from `app_secret()`.
   - TOTP replay protection records the last accepted time step (`admins.totp_last_step`) and rejects reused codes.
   - 2FA challenges are rate-limited to **5 wrong attempts per 10 minutes per admin account**, alongside optional IP allowlisting, session revocation (`Sign Out All`), role-based access control (`superadmin`, `editor`, `support`, `analyst`), and immutable audit logging.

5. **Protected PDF Reader & Search Abuse Controls**
   - Protected page images are rendered outside web-accessible paths (`storage/`), staged atomically before replacing live pages, and served only via short-lived HMAC-signed, session-bound tokens (`/api/page`).
   - Forensic dynamic user watermarks are cached per user/variant and refreshed hourly.
   - Single active reading session per account, concurrent device cap, and automated abuse detection (fast sequential burst, out-of-range probing, multi-session anomaly, and excessive daily search abuse).
   - In-book search escapes SQL `LIKE` wildcards (`ESCAPE '\\'`), enforces minimum query length (>= 3 chars; >= 2 for Bangla), caps snippets at 60 characters and 10 results, and enforces 15/min + 100/day per-book quotas.

6. **OTP / SMS Abuse & Enumeration Protection**
   - Login OTP requests enforce per-phone caps (3/hour, 5/day), per-IP caps (5/10 min, 15/day), a 60-second per-phone resend cooldown (server-side + UI timer), and a configurable global daily SMS budget (`sms_daily_budget`) with security logging and admin email alerts.
   - Identical neutral responses are returned for known and unknown phone numbers to prevent account enumeration.

7. **Database Safety & Idempotent Migrations**
   - All SQL queries use PDO prepared statements with `ATTR_EMULATE_PREPARES = false`, and all `LIKE` queries escape `%`, `_`, and `\`.
   - PHP schema migrations under `database/migrations/` inspect `information_schema` before altering tables, columns, indexes, or default settings (`schema_migrations` tracking + `/admin/updates`).
