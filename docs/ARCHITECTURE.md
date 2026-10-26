# Architecture and business rules

## Runtime

PHP 8.4+, Laravel 13, Blade, Tailwind 4, Alpine.js, Vite 8. Production is an ordinary PHP application with MySQL/InnoDB. Node compiles assets only. No Docker, Redis, VPS or permanent worker is required. Database sessions/cache/queue tables are included.

Controllers live under `app/Http/Controllers`; checkout validation uses `CheckoutRequest`; the services `Cart`, `Checkout`, `OrderWorkflow`, `OrderNotifier` and `Settings` hold commerce logic. Eloquent models and migrations define the store. Admin routes share authenticated role middleware; owner-only access grants are checked on the server. `OrderPolicy` governs authenticated confirmation access. Public registration/profile writes cannot set roles or active status.

## Prices and checkout

Amounts are unsigned integer paisa in BDT. A product has a regular price and optional sale price. Each unique product/size/color variant has its own unique SKU and stock. The browser submits variant IDs and quantities, never trusted prices. Account carts are database records; guest carts are encrypted Laravel sessions. Guest carts merge into account carts at login, capped by availability.

Checkout inserts/locks its idempotency token, checks token ownership, returns any existing matching order, locks variants in ID order, verifies publication and stock, recalculates prices, locks/validates a coupon, reads delivery settings, and creates order/item/payment/history records. An atomic stock decrement has an additional `stock >= quantity` predicate. All writes commit together. Deadlocks retry up to five times.

Order items retain product name, SKU, size, color, quantity, unit price and allocated line discount. Coupon allocation uses integer arithmetic; the final line absorbs rounding remainder. Order totals include subtotal + delivery − coupon discount. Notification attempts happen after the transaction returns. Tokens are scoped to a private session owner, and repeats return the existing reference. Starting another checkout creates a new token. Old successful tokens remain valid for bounded session retries.

Stock is reserved immediately when an order is placed. It stays reserved through fulfillment; pending orders do not auto-expire. Staff must cancel abandoned/unconfirmed orders when appropriate. Cancellation or an accepted return restores inventory once if `restore_stock=1`; `restocked_at` prevents repeat restoration, including simultaneous requests. `restore_stock=0` requires an explicit audited manual stock adjustment. Review physical returned goods before accepting a return. No negative stock or backorders are supported.

## Order workflow

- pending → confirmed or cancelled
- confirmed → processing or cancelled
- processing → shipped or cancelled
- shipped → delivered or returned
- delivered → return_requested
- return_requested → returned or delivered (request declined)
- returned → refunded, only after payment status is refunded
- cancelled/refunded are terminal

Payment is separate: unpaid → paid → refunded. Paid cannot be reset to unpaid; unpaid cannot be refunded. A COD delivery never marks payment collected. Only record paid/refunded after verifying actual collection/refund. Staff can add courier/tracking references and private notes. Public tracking shows the reference, current status and public status history, without delivery address, full name, phone, payment detail or internal notes.

Revenue is the sum of totals for orders both delivered and paid, including delivery and after discounts. Cancelled/returned/refunded orders are excluded. Popularity and homepage favourites count quantities on delivered, paid orders only. No synthetic sales data is used.

## Content and SEO

Content is escaped plain text with line breaks; arbitrary HTML is not accepted. Uploads accept validated JPEG/PNG/WebP with generated storage names. The public disk contains product/brand/content images only. Private application files remain outside the document root.

Published products require a published category. Missing/unpublished records return real 404s. Changed product/category/blog slugs have bounded, cycle-checked permanent redirects to published targets. Product prices/availability in JSON-LD are generated from the same models as visible content. There are no fabricated ratings, GTINs, promises or reviews. Category and product slugs are unique per resource.

Filters/search/sorting use `noindex,follow` and canonicalize to the unfiltered listing. Pagination uses crawlable links; listing canonical consolidates to its collection root. Private pages have no-store and noindex headers. The sitemap excludes private routes, demos, noindex products and unpublished content. Canonicals and sitemap origin use `APP_URL`; configure the real verified domain. No invented hreflang locales exist.

## External integrations and boundaries

COD is the sole active payment method. There is no card-data collection and no pretend bKash/Nagad flow. SMTP notifications are off by default. If enabled, transport errors are logged without claiming successful delivery; cached per-order event markers suppress routine repeated sends. SMTP acceptance is not proof of inbox delivery. Delivery time and courier status are staff managed.

Newsletter signup records consent/preferences only. It does not send campaigns. GA4, Meta Pixel, Search Console verification, product feeds, abandoned-cart automation, transactional SMS, provider payments and courier APIs need a separate configured integration. No marketing requests are sent from the frontend. Reviews and blog category relationships have database foundations; verified-review submission/moderation and a separate blog category manager are not exposed in this release. Policies and exact product specifications await owner-supplied content.

Database cache locks serialize notification attempts. External mail has unavoidable delivery ambiguity if a process fails after the provider accepts mail but before the cache marker is saved; use a provider idempotency API/outbox for stricter delivery guarantees.
