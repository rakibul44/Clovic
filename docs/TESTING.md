# Test and verification record

Verified on 27 October 2026 (Asia/Dhaka).

## Automated application suite

`php vendor/phpunit/phpunit/phpunit` / `php artisan test` runs the Laravel PHPUnit suite. The project uses an in-memory SQLite database by default; development/production databases are not reset by this default test configuration.

Current result: **27 tests, 166 assertions, all passed**.

Coverage includes public/admin page rendering, database schema, customer access restrictions, server-price recalculation, both delivery areas, coupon preview and limits, guest checkout, variant stock, order snapshots, duplicate submission, subsequent orders, rollback on injected item-write failure, status transitions, once-only restocking, separate COD payment status, payment-update rollback, tracking privacy, private confirmations, search/filters/counts, unpublished records, metadata/JSON-LD parsing, sitemap, robots, 404 responses, registration/wishlist/profile restrictions, password broker reset/replay prevention, product/category writes, valid image storage, executable upload rejection, inventory adjustments, CSV import/export, bulk status and navigation validation.

The whole feature suite is also run against a disposable MySQL 8.4.13 database by setting DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD before invoking PHPUnit. Use only an isolated test database: RefreshDatabase applies migrations and transactions. Do not point test processes at production.

## Actual simultaneous MySQL transactions

The opt-in harness `scripts/mysql-concurrency.php` starts two separate PHP processes per scenario. It resets only a database whose configured name ends in `_concurrency_test` and refuses other names. Create that isolated database first and supply its connection through environment variables.

```sh
DB_CONNECTION=mysql DB_DATABASE=clovic_concurrency_test DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=TEST_USER php scripts/mysql-concurrency.php
```

Supply a password securely through your environment or private test environment file; do not paste a real production password into shell history. The example uses POSIX environment syntax; set the equivalent process environment in PowerShell on Windows.

All four scenarios passed on MySQL 8.4.13:

1. Two checkouts for the last unit: one order succeeds, one rejects stock, stock ends at zero.
2. Two submissions of one token: both return the same reference, only one order and stock decrement occur.
3. Two cancellation requests: stock restores exactly once.
4. Two checkouts using a coupon with one remaining use: one succeeds, one rejects the coupon, usage remains one.

This checks actual competing transactions rather than inferring row-lock behavior from SQLite.

## Build and source checks

Vite production compilation succeeds; assets are emitted in `public/build`. PHP syntax checks, Blade compilation, Laravel route registration, Pint formatting, Composer metadata validation, and production-style optimization/cache generation are checked. Composer and npm reported no known dependency vulnerabilities during installation; this is a point-in-time report, not a security certification.

## Verification limits

The browser permission gate declined the localhost preview. No screenshot-based desktop/mobile inspection, keyboard interaction audit, browser end-to-end test, Lighthouse score, visual accessibility certification, Google Rich Results result or production Hostinger test is claimed. Responsive breakpoints, semantic labels, focus states, reduced motion and dialog focus handling are implemented, but require browser QA before launch.

Real SMTP/password-reset delivery, courier integration, electronic payments and third-party analytics were not tested with external credentials. Electronic payments and advertising remain inactive. No production domain or hosting credentials were provided.
