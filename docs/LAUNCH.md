# Security and SEO launch checklist

## Business content

- Replace or archive every demo product. Upload authentic photos, accurate fabric/fit/care information and product-specific measurements. Check each available variant SKU, size, color and stock.
- Approve actual delivery coverage, fees, contact email/phone, hero/banner content, navigation and social links.
- Replace all draft policies with approved business text. Explicitly address size/color mistakes, damaged products, customer-requested changes, exchange eligibility/time limits, and who pays return shipping. Approve privacy retention/deletion/contact procedures and sales terms. Do not launch with the draft notices.
- Review seeded category descriptions and metadata. No fake testimonials, reviews, sales counts or artificial discounts should be added.

## Hosting and access

- Verify the actual plan against `DEPLOYMENT.md`, including web/CLI PHP 8.4+, MySQL, memory, extensions, storage, cron and document root.
- Set the verified HTTPS APP_URL, production environment, debug=false, secure/HTTP-only cookies, encrypted sessions and a unique retained APP_KEY.
- Protect `.env`, source, logs, dumps, private files and `.tools` by keeping them outside the public document root. Confirm URL probes cannot retrieve them.
- Create an owner with `clovic:owner`, remove obsolete admin access, and use strong unique credentials. Test customer/admin/owner boundaries. No default admin password exists.
- Test CSRF protection, login/recovery/tracking rate limits, secure cookie behavior and disabled-account enforcement on the target host.
- Test image MIME/size validation, serving of stored photos, and host-level nonexecution for uploads. Use least-privilege permissions and database credentials.
- Confirm encrypted backups and perform a restore rehearsal. Set operational retention limits for sessions, logs, customer data and checkout token records according to the approved policy.

## Commerce

- Place a real controlled COD order, check server totals for both delivery areas, coupon calculations, variant stock and item snapshots.
- Verify repeat submission does not create a second order. Verify a later order creates a new reference.
- Verify cancellation/return restocks once under the chosen setting. Check delivered status does not record COD collection.
- Verify authorized staff can record courier references and genuine payment collection/refunds, and that invalid transitions fail without partial changes.
- Verify confirmation ownership and tracking privacy using two separate customers and a guest session.
- Confirm SMTP sender credentials and real order/recovery messages before enabling STORE_MAIL_ENABLED. Keep bKash/Nagad and analytics inactive until fully implemented and verified.

## SEO, usability and performance

- Verify canonical origin and HTTPS redirects on the actual domain. Check per-product/category/article titles, descriptions, image alt text and social-sharing images.
- Check `/sitemap.xml`, `/robots.txt`, noindex/private headers, filtered URL canonicals, real 404s and permanent slug redirects.
- Validate live Product/Offer, BreadcrumbList, Organization, WebSite and Article JSON-LD using Google's available testing tools. No aggregate ratings should exist without eligible genuine data.
- Test the responsive store on actual mobile/desktop browsers, forms and checkout with a keyboard, dialog focus, search suggestions, error states and image galleries.
- Measure real performance and accessibility after production images are uploaded. Set image sizes/compression and cache policies appropriate to the host; private routes must remain no-store.
- Submit the real sitemap to Search Console only after the site and policies are approved. Publish hreflang only when corresponding localized pages actually exist.

## Optional future integrations

bKash/Nagad require a supported provider, credentials, verified callbacks, signature checks, amount/currency/order binding, idempotent settlement and refund tests. Courier APIs, SMS, consent-aware GA4/Meta tracking, feeds, abandoned-cart messaging and newsletter campaigns need separate implementation and credentialed testing. Database foundations for optional reviews/blog categories are present; this release does not expose a verified-review workflow. Do not advertise these integrations as live.
