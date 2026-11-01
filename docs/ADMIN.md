# Clovic admin guide

Sign in at /login, then open /admin. Establish the first owner with the interactive command `php artisan clovic:owner owner@example.test`; it prompts securely for a password. No default administrator or password is seeded. The owner manages staff under Staff; public registration never grants staff rights.

## Permissions

| Role | Access |
| --- | --- |
| Owner | All modules, costs and staff provisioning |
| Admin | Store operations, content, reports, costs and settings; cannot grant staff access |
| Store manager | Orders, products, categories, inventory, customers, coupons, shipping, payments and reports; no unit costs or staff grants |
| Product manager | Products, categories and inventory |
| Order manager | Orders, customers, manual shipping and payment records |
| Content manager | Categories, content, homepage, reviews and SEO |
| Support | Read orders, customers and shipping; no changes or customer/order exports |

All staff receive an overview and relevant notifications. Server middleware and sensitive controller guards enforce permissions even when someone types a restricted URL. Only the owner can grant/revoke staff roles. Owner accounts cannot be demoted through the staff form; staff cannot change their own access there. Account suspension blocks admin access.

## Catalog and inventory

Create a category, then a draft product with its real specifications and photography. Products use one primary category; categories support a parent and ordered presentation. Publish only when the category is published. Upload validated JPEG/PNG/WebP files, set alt text, image position and primary image. Variants have a unique SKU, size, color, optional color swatch, optional product-owned image, price override and separate stock.

Every amount entered in admin is integer paisa: 100 means ৳1. Product sale price is the fallback; a variant override is the exact price for that variant. Cost is optional, restricted to owner/admin and snapshotted when an order is created. Historical unknown costs remain unknown.

Product deletion is reversible: deleted items disappear publicly and remain in the Deleted filter. Restore returns them as drafts. Orders retain their original item snapshots. Empty categories can be deleted; move their products and children first. Inventory supports SKU/product/size/color search, stock filters, single and atomic bulk adjustments, CSV and movement history. Negative resulting stock is rejected.

Demo products stay labeled/noindex and are excluded from checkout by default. Stock photography is demonstration inspiration, not proof of actual inventory. Replace it with owner-approved product images. Media removal controls delete the corresponding stored file; order snapshots remain.

## Orders, shipping and payments

Orders supports status/date/phone/reference/payment/area filtering, private detail, public and internal notes, CSV, printable invoice and packing slip. Public notes appear to the customer; never put private operational information there. Invoices and exports require staff access. Packing slips omit financial amounts. Print documents use the purchased snapshots.

Use the existing fulfillment transitions. Cancellation/return stock restoration runs once under a lock. Review physical returned stock before restoring it. A delivered order does not automatically collect COD payment.

Shipping settings control inside/outside-Dhaka fees, a free-shipping threshold and owner-supplied delivery information. The threshold compares the subtotal after coupon discount. Shipping records have manual courier/tracking references, dispatch/estimated dates, status and private notes. These records do not silently advance fulfillment or collect payment. No live courier integration is configured.

Payments records collection/refund references only after staff verify the money movement. An unpaid order cannot be refunded; paid cannot be reset to unpaid. Recording a refund does not initiate a bank or wallet transfer. COD is the active method; bKash/Nagad/provider credentials and callbacks are not activated.

## Coupons and customers

Coupons support percentage/fixed paisa discounts, minimum subtotal, start/expiry, total usage, maximum discount, per-phone usage and product/category scope. If both scopes are set, both must match. Discounts never exceed eligible goods and are allocated only to eligible lines. The per-customer limit uses a normalized checkout phone hash; it is not identity verification. Coupon validation and redemption are serialized with database locks.

Customer records expose account status, paid spending, order history and private service notes. Exports are audited. Guest orders remain searchable in Orders and are not invented as registered accounts.

## Homepage, content and SEO

Homepage Builder enables and orders the supported sections by numeric position. Edit the fields exposed for each section. Hero and campaign support separate desktop/mobile images, alt text and local CTA destinations. Product sections use real catalog flags; best sellers use delivered, paid purchases. FAQ uses a published FAQ page; testimonials use approved verified purchases. Empty campaigns/testimonials are omitted rather than populated with fabricated content.

Content manages categories, plain-text pages, blog posts and coupons. Settings includes navigation, logo, announcement, contact/business information, favicon, timezone, BDT/Bangladesh defaults and maintenance mode. Only the owner may enable maintenance; login/admin remain available. Arbitrary HTML/scripts and other currencies/markets are not enabled.

SEO manages defaults, titles/descriptions, same-domain canonical overrides, indexing and permanent redirects. Product photo/metadata warnings identify incomplete catalog records. Sitemap and robots are generated from published records. Reviews appear publicly only when approved and linked to the same customer's delivered, paid order; moderation supports a public reply. Public review submission is not exposed.

## Reports and notifications

Reports filters daily/weekly/monthly periods and exports actual records. Revenue includes delivery, after discounts, for orders currently delivered and paid, grouped by order creation date. Returned/refunded/cancelled orders are excluded; this is an operational report, not a tax ledger. Revenue can change when an older order's state changes. New/returning buyer counts concern registered accounts. Category reports use the current catalog category.

Cost-enabled roles see known inventory cost and gross merchandise margin. Unknown historical costs prevent a complete margin claim; shipping/provider costs and overhead are not included. No synthetic sales or profits are seeded.

Notifications derive from recent orders, cancellations/returns, low stock and delivered-unpaid COD orders. Read state belongs to each staff user. These are in-app alerts, not delivered email/SMS. The audit log records important mutations and sensitive document/customer export access without logging passwords or payment credentials.

## Installation and release

Follow README.md and DEPLOYMENT.md. Back up database and uploads, install locked dependencies, run `php artisan migrate --force`, `php artisan storage:link`, compile `npm run build`, and rebuild Laravel caches. The additive admin migration preserves existing products, orders, accounts and settings; no demo orders/reviews are inserted. Do not roll it back on production without a backup and reviewed data-loss plan. No Docker, permanent Node process or worker is required for the dashboard. Verify actual Hostinger PHP 8.4+ and MySQL/InnoDB support.

Browser visual QA was unavailable because the saved browser policy denied localhost access. Before launch, manually check desktop/tablet/mobile navigation, form layouts, keyboard focus, charts, uploads and print documents on the actual HTTPS deployment. External services, owner policies, authentic catalog and hosting configuration still require owner setup.
