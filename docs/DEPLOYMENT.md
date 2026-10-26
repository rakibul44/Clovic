# Hostinger deployment and operations

## Verify the actual hosting account first

The application was tested locally on PHP 8.4.27 and MySQL 8.4.13. No Hostinger account was supplied. A hosting plan is compatible only if it can meet these requirements:

- PHP 8.4+ for this lockfile, with the same compatible version for web PHP and CLI
- ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO/pdo_mysql, session, tokenizer and XML; GD for development image tests, ZIP for normal Composer package extraction
- MySQL 8.0+ using InnoDB, utf8mb4 and transaction/row-lock support
- Composer 2 or the ability to upload a locally built compatible vendor directory
- writable `storage` and `bootstrap/cache`, a supported public-storage link, URL rewriting
- HTTPS and a document root mapped to Laravel's `public/` only
- adequate PHP memory (256 MB recommended), upload/post limits for 4 MB images, and sufficient disk/database quota
- SSH and cron are recommended for deployment and maintenance; verify which features your exact plan includes

Check `php -v`, `php -m`, `composer --version`, `composer check-platform-reqs --no-dev`, and `SELECT VERSION()` on the target. hPanel web PHP may differ from SSH's default PHP. Use the matching versioned CLI binary if necessary. Do not infer account compatibility from a generic hosting plan name.

Official references: [Laravel deployment](https://laravel.com/docs/13.x/deployment), [Hostinger Laravel support](https://support.hostinger.com/en/articles/1583301-which-laravel-versions-are-supported-at-hostinger), [Hostinger SSH](https://support.hostinger.com/en/articles/1583645-how-to-enable-ssh-access), [Hostinger Composer](https://www.hostinger.com/support/8727597-how-to-install-composer-locally-at-hostinger/). Hostinger's older Laravel article lists older PHP requirements; use this application's lockfile requirements for the actual deployment.

## Build and release

1. Build on a compatible machine: `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`. Retain `composer.lock`, `package-lock.json` and `public/build` in the release package. No Node runtime is needed on the server.
2. Back up the existing database and uploads before an update. Upload source/vendor/build files to a private application directory outside any served directory. Exclude `.tools`, `.git`, tests, development database files and `node_modules`.
3. Configure the site's document root using a Hostinger-supported configuration to point at `<application>/public`. If the account cannot safely map this directory, stop and choose a compatible configuration/plan. Do not expose the whole project under `public_html`, and do not rely on denial rules to make an exposed `.env` safe.
4. Copy `.env.example` to private `.env`. Set the actual HTTPS `APP_URL`, MySQL connection, real store mail sender if available, and `APP_ENV=production`, `APP_DEBUG=false`. Set `SESSION_SECURE_COOKIE=true`. Use a least-privilege database user. Currency is BDT.
5. Generate `APP_KEY` once with `php artisan key:generate`. Retain it securely across releases. Do not rotate it casually; encrypted sessions/data depend on it.
6. Run `php artisan migrate --force`, `php artisan db:seed --force` for initial store settings/categories/pages, and `php artisan storage:link`. Never run the demo seeder on the live store. The base policy pages are drafts; approve and replace them before opening sales.
7. Run `php artisan clovic:owner your-real-email` interactively to establish the owner. It prompts for the password. Do not pass credentials in command-line arguments or publish default admin credentials.
8. Grant the web user write access to `storage` and `bootstrap/cache` using least privilege (typically directories 755/775, depending on host ownership). Never use 777. Verify image uploads through the admin and ensure uploaded content cannot execute as PHP.
9. Run `php artisan optimize`. Check `/up`, storefront, login, uploads, guest COD checkout, admin, tracking privacy, sitemap and robots over the actual HTTPS domain. Confirm direct requests for `.env`, `composer.json`, `storage/logs` and source files are inaccessible.
10. See `LAUNCH.md` before accepting real orders. On release updates, run `php artisan down` during migrations if necessary, apply the release, clear/rebuild caches, restart bounded queue jobs if used, and `php artisan up`.

The included Apache/LiteSpeed public `.htaccess` routes requests to Laravel and disables directory listing when supported. Never place private uploads on the public disk. Configure host-level denial of script execution under the public storage path as defense in depth.

## Mail and scheduled tasks

Set actual SMTP values, `MAIL_MAILER=smtp`, and `STORE_MAIL_ENABLED=true` only after sender verification and a successful real send/recovery test. Keep it false when using the log mailer. Password recovery explicitly reports when mail is not configured. Order confirmation remains available in the originating session/account even without mail.

The current order notifier runs after checkout commits; it does not need a daemon. Laravel queue tables are present for future jobs. If adding queued work, shared hosting can use a bounded cron invocation of `php artisan queue:work --stop-when-empty --max-time=50 --tries=3`; protect it against overlapping runs. Cron may run `php artisan schedule:run` every minute with the correct CLI PHP and private application directory. Failed-job and expired-reset cleanup are scheduled. Confirm time zone Asia/Dhaka and cron availability on the actual account.

## Backup and restore

Back up the MySQL database, `storage/app/public`, private configuration and `APP_KEY`. Keep encrypted copies outside the web root and preferably outside the hosting account. Do not put dumps in `public/` or Git. Choose a retention schedule approved by the business and test restores regularly.

Example on a compatible shell (enter password when prompted):

```sh
mysqldump --single-transaction --routines --triggers --no-tablespaces -h HOST -u USER -p DATABASE > /private/backup/clovic.sql
```

Use hPanel database export if CLI tools are unavailable. Pause order/admin writes while coordinating database and uploaded-file snapshots. `--single-transaction` gives a consistent InnoDB database snapshot; uploads are a separate backup.

To restore, enter maintenance mode, preserve a fresh backup of current data, import into a clean isolated database first (`mysql -h HOST -u USER -p DATABASE < /private/backup/clovic.sql`), restore the matching uploads and environment key, verify order totals and stock, then point the app at the restored database and rebuild caches. Avoid restoring database data over a live store accepting orders. Do not run `migrate:fresh` on production. The MySQL concurrency harness intentionally refuses databases without its test-only suffix.

## Deployment is not yet verified

A real domain, hosting login, production database and approved business content have not been supplied. No live site was published. Local success does not establish real SMTP delivery, Hostinger account limits, production HTTPS/cookie behavior or Google rich-result eligibility.
