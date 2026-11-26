# Privacy & Data Handling Notes

This document summarizes personal data stored by Bookexa and built-in retention controls to help site operators comply with privacy policies.

## Data Stored
- **Accounts (`users`, `user_devices`)**: Name, email, optional phone number, bcrypt password hash, device labels, IP addresses, and active session tokens solely for login security and single-session enforcement.
- **Digital & Physical Orders (`orders`, `shop_orders`)**: Billing/shipping details, masked/verified TrxIDs, optional payment proof screenshots stored outside public webroot in `storage/payment_proofs/`, courier/tracking numbers, and invoices.
- **Reading Activity & Watermarking (`reading_logs`, `reading_progress`)**: Last page read, page access logs, and reader watermark overlays displaying the reader's own name/contact + user ID + timestamp to deter unauthorized redistribution.
- **Communications & Search Telemetry (`notifications`, `contact_messages`, `search_queries`, `search_misses`)**: Support inquiries, notification feed items, and search frequency keywords (no passwords or payment proofs).

## Retention & Cleanup
- Automated hourly lazy maintenance (`includes/maintenance.php`) prunes:
  - Expired/used `reader_tokens` and `password_resets` older than 24 hours.
  - `reading_logs` and `security_logs` older than 90 days.
  - Read `notifications` older than 60 days.
  - Temporary cached PDF binaries (`*.bin`), watermark PNGs, and rate-limit JSON files cleanly.
- Account owners can view & download a JSON export of their data from `/profile` and revoke active devices from `/devices`.
- Site operators should customize the bilingual Privacy Policy in **Admin → Settings → Legal Pages** to match their organization's legal entity and contact details.
