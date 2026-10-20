# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 1.x | Yes |

This is a portfolio project with a single maintained branch. Security fixes are applied to the current release line; older snapshots are not backported.

## Reporting a vulnerability

Please **do not open a public issue** for a security problem.

Use GitHub's private vulnerability reporting:

1. Open the repository's **Security** tab.
2. Choose **Report a vulnerability** (GitHub Security Advisories).
3. Include the affected version, the impact, reproduction steps, and any proof-of-concept you can share safely.

This route keeps the report private until a fix is available. There is no dedicated security email address, and none is invented here.

### What to expect

- Acknowledgement when the report is reviewed.
- A fix or a documented decision as soon as is practical for a single-maintainer project.
- No guaranteed response time, SLA, or bounty. If you need contractual response times, treat this as unmaintained and arrange your own review.

## Scope

In scope:

- Authentication and authorization bypasses in admin actions or REST routes.
- CSRF on state-changing requests.
- SQL injection, cross-site scripting, and unsafe output encoding.
- Privilege escalation, including an affiliate acting on another affiliate's records.
- Payment-integrity issues: commission manipulation, duplicate payouts, negative or over-limit payouts.
- Sensitive-data exposure in logs, exports, or API responses.
- Remote code execution, path traversal, and unsafe file handling.
- Rate-limit or lock bypasses that allow repeated financial writes.

Out of scope:

- Vulnerabilities in WordPress, WooCommerce, PHP, or the web server. Report those upstream.
- Issues that require administrator-level access to the WordPress database or filesystem.
- Social engineering, spam, and denial of service from a single trusted administrator account.
- Missing security headers controlled by the hosting environment or a separate plugin.
- The known limitations listed in the README, such as the absence of automatic payouts and email notifications.

## Security design

### Authorization model

- Admin screens and actions require `manage_woocommerce`; the settings screen requires `manage_options`.
- Each state-changing admin action has its own nonce action and uses `check_admin_referer`, which terminates with 403 on failure.
- REST routes declare a real `permission_callback`: `is_user_logged_in()` for the affiliate endpoints and `current_user_can( 'manage_woocommerce' )` for the affiliate list.
- Payout creation and registration resolve the affiliate from the authenticated user, never from a client-supplied affiliate ID, so one account cannot act for another.
- Admin list filters and pagination are validated against an allowlist and clamped to a minimum page of 1.

### Input and output

- Every externally supplied value is treated as untrusted: scalar type checks, length limits, digit normalization, and allowlists for status values and payout methods.
- Referral codes are validated against a restricted alphabet before normalization; path traversal and quote-based payloads are rejected.
- Every database query with variables uses `$wpdb->prepare()`; table names are built from `$wpdb->prefix`.
- Output is escaped by context. SVG icons are generated internally, never from user input.
- No `extract()`, `eval`, `base64_decode` of payloads, or dynamic include from request data.

### Money and concurrency

- Money is stored and calculated as integers in the smallest currency unit. Floating-point values are converted once, at the boundary, with explicit decimals.
- Allowed commission and payout transitions are enforced in the domain layer, not only in the UI.
- Payout creation uses a short atomic lock backed by `add_option`, plus a transient-based rate limit. Registration has the same two layers.
- Inserts are checked for failure; a failed financial write is reported generically and logged with context.

### Secrets and logging

- The plugin stores no API keys, passwords, or tokens. No secret belongs in this repository.
- No third-party requests are made: the admin font (Vazirmatn) is bundled in `assets/fonts` under SIL OFL 1.1, there is no telemetry, licence check, or CDN call.
- Visitor IP addresses are stored as HMAC-SHA256 using the WordPress authentication salt.
- Error details are written to the WooCommerce log with the source `woo-affiliate-pro`. User-facing messages stay generic.
- Referral cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` when the site uses HTTPS.

### Data retention

- Clicks older than 180 days are removed by the daily maintenance job. The retention period can be changed with the `wap_click_retention_days` filter and is clamped to 1–3650 days.
- Commission and payout records are retained indefinitely.
- On uninstall, cron events, transients, and stale locks are always removed. Tables and settings are removed only when the administrator has enabled `delete_data_on_uninstall`.

## Hardening recommendations for production

- Serve the site over HTTPS so the referral cookie is marked `Secure`.
- Keep WordPress, WooCommerce, PHP, and this plugin updated.
- Restrict `manage_woocommerce` and `manage_options` to trusted accounts.
- Use a real object cache and a server cron runner; WP-Cron depends on traffic.
- Take database backups before changing commission rules or uninstalling the plugin.
- Review the payout destination manually before marking a request as paid; the plugin records the reference number but does not transfer funds.
