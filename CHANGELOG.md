# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] — 2026-10-21

### Security

- Fixed the settings form handler, which verified the wrong nonce action and could reject or accept the wrong request.
- Added `defined( 'ABSPATH' ) || exit;` to every PHP file under `src/`.
- Replaced `extract()` in the shortcode renderer with explicit, typed template variables.
- Validated scalar request input before sanitizing, so array-shaped `$_GET`/`$_POST` values cannot trigger a fatal type error.
- Added capability and nonce checks to all admin mutations and real permission callbacks to REST routes.
- Added atomic locks and transient-based rate limits to registration and payout creation (web and REST).
- Hardened the uninstall routine: cron events, transients, and stale locks are always removed; financial tables are dropped only when explicitly requested.
- Wrapped order status and refund handling in failure-tolerant paths, and stopped leaking internal errors to users.

### Added

- Bespoke admin and affiliate-panel design system (`assets/css/admin.css`, `assets/css/frontend.css`): CSS tokens, glass surfaces, one gold accent, dark mode via `prefers-color-scheme`, `data-label` table cards below 760px, visible focus rings and 44px targets. Documented in `docs/design-system.md`.
- Vazirmatn (variable) is bundled in `assets/fonts` under SIL OFL 1.1. No Google Fonts or CDN request is made; the previous preconnect filter is gone.
- Persian digits and separators for every number, amount and date in the affiliate panel and the admin screens (`Support\PersianNumber`); form input values stay Latin.
- Block checkout (Store API) support: `woocommerce_store_api_checkout_update_order_from_request` writes the same attribution metadata as the classic checkout.
- Per-visitor click deduplication (affiliate id + hash of IP and User-Agent, 60 s) instead of one global click per 30 s.
- Pagination and status filters on affiliate, commission, and payout admin lists.
- Aggregate commission and payout stats, plus batch affiliate-name lookup to avoid N+1 queries.
- Payout destination display and reference-number capture in the admin payout list.
- `count()`/`stats()` repository helpers and `find()` lookups for commissions and payouts.
- `wap_click_retention_days` filter, clamped to 1–3650 days.
- `CHANGELOG.md`, `SECURITY.md`, and a rewritten Persian and English README.
- Tests for the template markup contract, including field names, nonces, table labels, error wiring, and exact money text.

### Changed

- Commission approval now supports the `processing` setting in the daily recovery job and processes the full pending backlog in bounded batches.
- Money formatting is decimals-aware in the dashboard, REST responses, and admin tables.
- The dashboard and registration templates preserve submitted values, format money through `Money`, and avoid WordPress-only helpers that are unavailable in isolated rendering.
- The commission cap is calculated in the order currency and decimals.
- Refund detection compares integer minor units instead of floats.
- Admin order links use the HPOS-safe edit URL when available.
- Database version bumped to `2`; the clicks table gained a `created_at` index.
- Settings defaults are merged non-destructively on install and upgrade.
- The README no longer claims multi-level commissions.

### Fixed

- Duplicate payout creation from double submits or page refreshes.
- Lost commissions when a payout insert failed or an order was cancelled after payment.
- Incorrect settings nonce action.
- Incorrect fallback when an affiliate record was missing during payout rendering.
- Silent insert failures in click, commission, and payout repositories.

## [1.0.0]

### Added

- Initial public release: referral links and cookies, last-click attribution, per-category commission rates, exclusions, per-order cap, commission lifecycle, affiliate dashboard, admin screens, manual payouts, REST API, HPOS compatibility, and integer money math.
