# Clovic

Laravel 13 clothing commerce for Bangladesh, using MySQL, Blade, Tailwind 4, Alpine.js and Vite. The server requires PHP **8.4+** for the locked dependencies. No Node process is required in production.

## Start locally

Prerequisites: PHP 8.4 with the extensions listed in `docs/DEPLOYMENT.md`, Composer 2, Node 22.12+ and npm, plus MySQL 8.0+. Install from the committed lockfiles:

```sh
composer install
cp .env.example .env
# Edit .env: APP_ENV=local, APP_URL=http://localhost:8000,
# SESSION_SECURE_COOKIE=false; configure your local MySQL database.
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm ci
npm run build
php artisan clovic:owner owner@example.test
php artisan serve --host=127.0.0.1 --port=8000
```

The owner command prompts for a password and never creates a default login. Sign in at `/login`, then open `/admin`.

For SQLite development, create `database/database.sqlite`, set `DB_CONNECTION=sqlite`, remove `DB_DATABASE` from your local `.env`, and enable `pdo_sqlite`. Production uses MySQL; SQLite tests do not establish row-locking behavior.

This workspace includes ignored portable PHP/Composer/npm tools in `.tools` used during development. For example, `.tools/php/php.exe artisan serve` and `node .tools/package/bin/npm-cli.js run build`. These tools are not deployment artifacts. A local SQLite database has been seeded with the illustrative demo catalog; no default owner exists.

## Demonstration catalog

```sh
php artisan db:seed --class=DemoCatalogSeeder
```

Sample products are labeled, excluded from the sitemap, and blocked from checkout unless `ALLOW_DEMO_CHECKOUT=true` is explicitly set in a local test environment. No fabricated orders or reviews are seeded. Replace illustrations with authentic product photographs and supplied specifications before accepting real orders. Archive demo items in the admin before launch.

## Application

- Storefront: `/`, `/shop`, `/category/{slug}`, `/product/{slug}`, `/blog`
- Bag and guest COD checkout: `/cart`, `/checkout`
- Private order confirmation and reference/phone tracking: `/order/{reference}`, `/track-order`
- Accounts, profile, history and wishlist: `/login`, `/register`, `/forgot-password`, `/account`, `/wishlist`
- Admin: catalog, variants, image uploads/alt text, inventory movements, CSV import/export, bulk publishing, orders, payment collection, courier references, customers, content, coupons, settings and audit trail
- Search, category/price/size/color/availability filters, sorting, pagination, recently viewed and related products
- Editable homepage hero, navigation, announcement, brand text, featured/offers visibility, content and delivery fees
- Server-rendered metadata, canonical links, JSON-LD, published-content sitemap and robots directives

All monetary storage and business arithmetic use integer paisa (৳1 = 100). Admin price fields and CSV imports use paisa. Delivery defaults: ৳80 inside Dhaka and ৳150 outside.

## Verify

```sh
php artisan test
npm run build
```

See [testing](docs/TESTING.md), [architecture](docs/ARCHITECTURE.md), [deployment and backups](docs/DEPLOYMENT.md), and [launch checklist](docs/LAUNCH.md).

Cash on Delivery is operational application logic. bKash/Nagad, advertising analytics, automated marketing, courier APIs and real mail delivery are not activated. Email requires verified SMTP settings and `STORE_MAIL_ENABLED=true`. The draft business/legal policies require owner approval. Hostinger account capabilities, the actual domain, real catalog data and external delivery services cannot be verified without access supplied by the owner.
