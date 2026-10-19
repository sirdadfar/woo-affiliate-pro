# Affiliate Pro for WooCommerce

[![tests](https://github.com/sirdadfar/woo-affiliate-pro/actions/workflows/tests.yml/badge.svg)](https://github.com/sirdadfar/woo-affiliate-pro/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-7.4%2B-777bb4)](composer.json)
[![WooCommerce](https://img.shields.io/badge/woocommerce-7.0%2B-96588a)](https://woocommerce.com)

A complete affiliate/referral program for WooCommerce, built for the Iranian market: Persian dashboard, IRR/toman amounts, per-category commission rates, manual and automated payouts, and money math that never drifts.

🇮🇷 [توضیحات فارسی](README.fa.md)

## Features

- Referral links (`?ref=CODE`) with a configurable 30–365 day cookie
- **Last-click attribution** — the most recent referral wins
- **Attribution captured at order creation**, not at completion, so delayed/bank payments don't lose the referrer
- Percentage commission with per-category overrides, product/category exclusions and a per-order cap
- Commission lifecycle `pending → approved → paid`, plus `reversed` on refund/cancel (records are never deleted)
- Affiliate dashboard via shortcodes: clicks, balance, commission history, payout requests
- Admin screens for affiliates, commissions, payouts and settings
- Self-referral protection by user ID and email
- HPOS (custom order tables) compatible
- REST API for mobile apps
- Integer money math — no floating point, ever

## Install

```bash
git clone https://github.com/sirdadfar/woo-affiliate-pro.git
cd woo-affiliate-pro
composer install --no-dev
```

Copy into `wp-content/plugins/` and activate. Without composer the plugin falls back to a bundled PSR-4 autoloader.

Create two pages: `[wap_dashboard]` (affiliate panel) and `[wap_join]` (signup form).

## Commission model

```
order placed ──► pending ──► approved ──► paid
                    │            │
                    └────────────┴──► reversed / rejected
```

The base is the line total after discount. Tax and shipping are opt-in. Fixed commissions apply once per order. A cap is applied as an explicit negative adjustment line so that the sum of the breakdown always equals the final amount.

## Tests

```bash
composer install && vendor/bin/phpunit
```

```
OK (70 tests, 373 assertions)
```

The **domain layer** (money, commission calculation, attribution rules, balances, payout validation) is fully unit-tested with no WordPress dependency. The WordPress/WooCommerce integration layer is not covered by automated tests — it requires a real WordPress install. All WooCommerce access goes through the public order API so the plugin works on both legacy posts and HPOS.

## Security

Prepared statements, escaped output, nonce + capability checks on every admin action, strict referral-code charset, HMAC-hashed IPs, HttpOnly cookies, and financial data is **not** deleted on uninstall by default.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
