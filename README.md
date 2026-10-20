# Affiliate Pro for WooCommerce

[![tests](https://github.com/sirdadfar/woo-affiliate-pro/actions/workflows/tests.yml/badge.svg)](https://github.com/sirdadfar/woo-affiliate-pro/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-7.4%2B-777bb4)](composer.json)
[![WooCommerce](https://img.shields.io/badge/woocommerce-7.0%2B-96588a)](https://woocommerce.com)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)

An affiliate program for WooCommerce with a Persian dashboard, per-affiliate referral links, per-category commission rates, integer money math, and manual payout management — built for Iranian stores that want to grow through affiliates without spreadsheets and direct messages.

🇮🇷 [توضیحات فارسی](README.fa.md) · [Security](SECURITY.md) · [Changelog](CHANGELOG.md)

---

## What problem this solves

Many WooCommerce stores run affiliate programs with a spreadsheet and direct messages: codes are created by hand, commissions are recalculated manually, and payout history lives in a separate file. That stops scaling once there are more than a handful of affiliates, and a one-rial discrepancy is enough to lose trust.

This plugin keeps attribution, calculation, lifecycle state, and payout requests inside WooCommerce. Money is calculated in integer minor units so floating-point drift cannot accumulate across thousands of orders. Commission records are never deleted; they change state, so the financial history stays auditable.

---

## Features

### Attribution and tracking

- Per-affiliate link with `?ref=CODE` and an 8-character code from the restricted `[A-Z0-9]` alphabet.
- Referral cookie for 1–365 days (default 30), `HttpOnly`, `SameSite=Lax`, and `Secure` on HTTPS.
- **Last click wins**; an invalid code never replaces an existing valid one.
- Attribution is written to the order when the order is created, not when it completes, so delayed payments, bank transfers, and manual approval do not lose the referrer.
- At most one click is recorded per visitor every 60 seconds: refreshes do not inflate the stats, while two different visitors clicking at the same time are both counted (the lock key is affiliate id + a hash of IP and User-Agent).
- Self-referral protection by WordPress user ID and billing email.

### Commission calculation

- Percentage rate per affiliate plus per-category overrides.
- Product and category exclusions.
- Per-order commission cap, stored as an explicit negative adjustment line so the sum of the breakdown always equals the final amount.
- Base is the line total after discount; tax and shipping are opt-in.
- Integer minor-unit arithmetic and half-up rounding; no `float` on the money path.
- Currencies with 0–4 decimal places, driven by `wc_get_price_decimals()`.

### Commission lifecycle

- `pending` when the order is created.
- `approved` when the order reaches `completed` or `processing` (configurable).
- `paid` after the payout is settled.
- `reversed` on cancellation or full refund; the record is kept.
- `rejected` for manual rejection by an administrator.
- Allowed transitions are enforced in the domain layer; jumping from `pending` to `paid` is rejected.

### Affiliate dashboard

- `[wap_dashboard]`: available balance, total approved commission, clicks in the last 30 days, and total clicks.
- Referral link with a copy button and select-on-focus behavior.
- Commission and payout tables that turn into label/value cards below 760 px.
- Payout request form with server-side validation and per-field error messages.
- `[wap_join]`: registration form that preserves submitted values after an error.

### Administration

- Affiliate list with status filters, counts, and pagination.
- Commission list with status filters, totals, and HPOS-safe order links.
- Payout list with payout destination, reference number input, and status transitions.
- Settings for rates, exclusions, caps, minimum payout, and uninstall data deletion.
- POST-Redirect-GET after every mutation, so refreshing the page cannot repeat a payout.

### Infrastructure

- HPOS (WooCommerce custom order tables) compatibility declared; all order access goes through the public order API.
- **Block checkout** (Store API) is supported alongside the classic checkout; attribution is written on the order in both flows.
- REST API for mobile apps and external panels.
- WP-Cron job that prunes old clicks and recovers missed commission approvals.
- A bespoke design system (CSS tokens, glass surfaces, one gold accent, dark mode via `prefers-color-scheme`) with Vazirmatn **bundled in the plugin** — no Google Fonts or CDN request at all.
- No runtime dependencies beyond PHP and WooCommerce, no build step, and no CDN JavaScript.

---

## Requirements

| Item | Version |
|---|---|
| PHP | 7.4 or newer (CI covers up to 8.3) |
| WordPress | 6.0 or newer |
| WooCommerce | 7.0 or newer |
| Composer | Only for development dependencies and running tests |

> The plugin header claims compatibility with WooCommerce up to 9.4. That claim has not been verified on a live WordPress/WooCommerce installation; only public API contracts were reviewed. Run a test order on staging before production use.

---

## Installation and setup

### Install from source

```bash
git clone https://github.com/sirdadfar/woo-affiliate-pro.git
cd woo-affiliate-pro
composer install --no-dev
```

Place the directory at `wp-content/plugins/woo-affiliate-pro` and activate it in WordPress. When installed as a zip without Composer, the plugin falls back to a bundled lightweight autoloader and still works.

### First run

1. **Activate WooCommerce.** Without it, the plugin shows an admin notice and does nothing.
2. **Create two pages.**

   | Page | Shortcode | Purpose |
   |---|---|---|
   | Affiliate dashboard | `[wap_dashboard]` | Affiliate panel |
   | Affiliate signup | `[wap_join]` | Registration form |

3. **Open “Affiliate → Settings”** and set the default rate, minimum payout, and other options.
4. **Create a test account**, register through `[wap_join]`, and open the referral link in a separate browser or private window.
5. **Place a test order** and follow the commission through “Affiliate → Commissions”.

The installer creates these tables using the WordPress table prefix:

| Table | Purpose |
|---|---|
| `{prefix}wap_affiliates` | Affiliates, codes, rates, payout details |
| `{prefix}wap_clicks` | Referral clicks with hashed IPs |
| `{prefix}wap_commissions` | One commission row per order and affiliate |
| `{prefix}wap_payouts` | Payout requests and reference numbers |

---

## Configuration

Settings are stored in a single WordPress option named `wap_settings`. Monetary values (`min_payout`, `max_commission`) are stored in the smallest currency unit; for IRR without decimals, `100000` means 100,000 rials.

| Key | Default | Meaning |
|---|---|---|
| `cookie_days` | `30` | Referral cookie lifetime, 1–365 days |
| `default_rate_bp` | `1000` | Default rate in **basis points**; `1000` means 10% |
| `include_shipping` | `false` | Include shipping in the commission base |
| `include_tax` | `false` | Include tax in the commission base |
| `category_rates` | `[]` | Map of `category_id => basis_points` |
| `exclude_products` | `''` | Comma-separated excluded product IDs |
| `exclude_categories` | `''` | Comma-separated excluded category IDs |
| `max_commission` | `0` | Per-order commission cap; `0` means no cap |
| `min_payout` | `100000` | Minimum payout amount |
| `approve_on` | `completed` | Approve on `completed` or `processing` |
| `auto_approve` | `true` | Activate new affiliates without manual review |
| `delete_data_on_uninstall` | `false` | Drop tables and settings when the plugin is deleted |

### Extensibility filter

| Filter | Default | Purpose |
|---|---|---|
| `wap_click_retention_days` | `180` | Click retention; the final value is clamped to 1–3650 days |

---

## Usage

### Affiliate journey

1. The customer signs in to a WordPress account.
2. On the `[wap_join]` page they enter a name, a mobile number matching `09xxxxxxxxx`, and a payout method.
3. With auto-approval enabled the account becomes active immediately; otherwise it stays `pending` until an administrator approves it.
4. `[wap_dashboard]` shows the referral link, click stats, balance, and history.
5. Once the balance reaches the minimum payout, the payout form becomes available.
6. The request appears in the admin payout list, and its status is reflected back in the affiliate panel.

### Administrator journey

1. **Affiliates:** approve, activate, disable, or reject a request.
2. **Commissions:** review base and status; approve manually or reverse.
3. **Payouts:** check the stored destination against the affiliate record, record a reference number, and move the request to `processing`, `paid`, or `rejected`.
4. **Settings:** change rates and rules. Changes affect future commissions and do not rewrite history.

### REST API

| Route | Method | Access | Purpose |
|---|---|---|---|
| `/wp-json/wap/v1/me` | GET | Logged-in user | Code, status, referral URL, and balance |
| `/wp-json/wap/v1/payouts` | POST | Logged-in user | Create a payout request with `amount` (integer minor units) |
| `/wp-json/wap/v1/affiliates` | GET | `manage_woocommerce` | Paginated affiliate list |

Authentication uses WordPress cookie plus nonce, or an Application Password. The write route has its own rate limit and concurrency lock.

---

## Security

- Every PHP file starts with `defined( 'ABSPATH' ) || exit;`.
- Every query with variables uses `$wpdb->prepare()`, and table names come from `$wpdb->prefix`.
- Every output is escaped with `esc_html`, `esc_attr`, `esc_url`, `esc_textarea`, or `wp_kses_post`.
- Every state-changing admin action checks both `current_user_can` and its own nonce; a failed nonce returns 403.
- Every REST route has a real `permission_callback`; no write route is public.
- Referral codes are strictly validated; `<script>`, `../`, and quote-based payloads are rejected.
- Visitor IPs are stored as HMAC-SHA256 using the WordPress salt, never in plain text.
- Registration and payout requests are rate-limited, and concurrent requests are serialized with an atomic lock.
- Database and calculation failures are logged to the WooCommerce log instead of being shown to the user.
- Financial data is **not** deleted on uninstall by default; deletion requires an explicit setting.

Threat model, supported versions, and the private reporting process are documented in [`SECURITY.md`](SECURITY.md).

---

## Limitations and what is not included

This section is intentionally explicit; missing features are not hidden defects.

- **No live WordPress integration test suite.** Automated tests cover the domain layer and template markup contracts. WooCommerce hooks, HPOS, cookies, and admin screens have not been exercised against a real installation.
- **No automatic payouts.** Settlement is manual. The plugin does not connect to a payment gateway or bank API; the reference number is stored and displayed only.
- **No email or notifications.** No transactional email is sent for signup, approval, or payout.
- **No multi-level marketing.** There is one affiliate level and one rate per affiliate; sub-affiliate commissions are not calculated.
- **No affiliate coupons or phone-order attribution.** Attribution works only through the `?ref=` link and browser cookie.
- **No CSV/Excel export.** Reporting is available through admin tables and the REST API.
- **Most Persian UI strings are hard-coded.** The text domain is loaded, but the whole interface is not wrapped in `__()` for full translation; no official non-Persian translation is provided.
- **Four decimal places maximum.** This is a deliberate limit that keeps integer math and money formatting predictable.
- **WooCommerce 9.4 compatibility is unverified.** The number comes from the plugin header, not from a confirmed live installation.

---

## Development and tests

### Run the suite

```bash
composer install
vendor/bin/phpunit
```

Current result:

```
PHPUnit 9.6.38 by Sebastian Bergmann and contributors.
OK (111 tests, 761 assertions)
```

### Lint and Composer validation

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
composer validate --strict
```

### What is covered

- Money precision: adding 0.1 a hundred times without floating-point drift.
- Basis-point percentages with half-up rounding and overflow protection.
- Rate precedence: product exclusion → category exclusion → category rate → default rate.
- Deterministic rate selection when a product belongs to several categories.
- Tax and shipping only when enabled.
- Commission caps via an adjustment line, keeping the breakdown sum equal to the final amount.
- State machine and rejection of invalid transitions.
- Balances: pending commission is not withdrawable, and open requests reserve funds.
- Referral code validation against injection, and self-referral rejection.
- Markup contracts: field names, nonces, option values, table `data-label` pairs, label/hint/error wiring, and exact money text.

### Adding a feature

1. Put financial logic in `src/Domain/` with no WordPress dependency so it stays testable.
2. Add data access in `src/Repository/`, always with `$wpdb->prepare()`.
3. Keep WordPress/WooCommerce wiring in `src/Integration/`.
4. If markup changes, do not break the contract in `tests/Unit/TemplateMarkupTest.php`; field names, nonces, and tested strings are part of the public contract.
5. Add a new test for every money change; never edit existing tests just to make them pass.

---

## Project structure

```
woo-affiliate-pro.php          Bootstrap, plugin header, HPOS compatibility declaration
uninstall.php                  Controlled data removal
src/
  Domain/                      Pure logic with no WordPress dependency
    Money.php                  Integer money and single formatting helper
    Commission/                Rate policy, calculator, breakdown, states
    Order/                     Order snapshot and lines
    Payout/                    Balance, payout request, states
    Referral/                  Code generation, cookie policy, attribution
  Repository/                  Database access with prepared statements
  Integration/                 WordPress/WooCommerce hooks, shortcodes, REST, cron
    Admin/AdminMenu.php        Admin screens and action handlers
  Install/Installer.php        Schema creation and upgrade via dbDelta
  Support/                     Settings, lock, rate limiter
templates/                     Affiliate panel markup and notices
assets/css/                    Admin and frontend styles
assets/js/frontend.js          Error-summary focus, link copy, field selection
tests/Unit/                    Domain tests and markup contract tests
docs/design-system.md          Tokens, components, and measured accessibility
```

---

## License and commercial use

Released under **GPL-2.0-or-later**, the same license as WordPress. Commercial use, installation on multiple stores, modification, and redistribution under the same license are permitted. The full text is in [`LICENSE`](LICENSE).

If you modify and distribute the plugin, the GPL requires you to make the modified source available under the same license.

---

## Support

- **Bug reports and feature requests:** use [Issues](https://github.com/sirdadfar/woo-affiliate-pro/issues) and include WordPress, WooCommerce, and PHP versions plus reproduction steps.
- **Security vulnerabilities:** do not publish them publicly; the private route is described in [`SECURITY.md`](SECURITY.md).
- **Response expectation:** this is a reviewable portfolio project, not a product with a 24/7 support team. Reports are reviewed when possible, but no response time is guaranteed. For production use, keep a staging environment and backups.
