# Changed files

This inventory compares the current workspace with the checked-in revision. It includes the earlier storefront redesign preserved during the admin expansion. Generated build filenames change when assets are compiled.

## Modified or removed tracked files

```text
M	README.md
M	app/Http/Controllers/AccountController.php
M	app/Http/Controllers/AdminController.php
M	app/Http/Controllers/CartController.php
M	app/Http/Controllers/SeoController.php
M	app/Http/Controllers/StoreController.php
M	app/Http/Middleware/Admin.php
M	app/Http/Middleware/SecurityHeaders.php
M	app/Models/Category.php
M	app/Models/Coupon.php
M	app/Models/Page.php
M	app/Models/Product.php
M	app/Models/ProductVariant.php
M	app/Models/User.php
M	app/Providers/AppServiceProvider.php
M	app/Services/Cart.php
M	app/Services/Checkout.php
M	app/Services/Settings.php
M	database/seeders/DatabaseSeeder.php
M	docs/ARCHITECTURE.md
M	docs/TESTING.md
M	package.json
D	public/build/assets/app-BvKXuWIP.js
D	public/build/assets/app-DxL_pjit.css
M	public/build/manifest.json
M	resources/css/app.css
M	resources/js/app.js
M	resources/views/admin/content.blade.php
M	resources/views/admin/customers.blade.php
M	resources/views/admin/dashboard.blade.php
M	resources/views/admin/inventory.blade.php
M	resources/views/admin/order.blade.php
M	resources/views/admin/orders.blade.php
M	resources/views/admin/product.blade.php
M	resources/views/admin/products.blade.php
M	resources/views/admin/settings.blade.php
M	resources/views/auth/form.blade.php
M	resources/views/cart.blade.php
M	resources/views/checkout.blade.php
M	resources/views/components/admin-layout.blade.php
M	resources/views/components/cart-drawer.blade.php
M	resources/views/components/layout.blade.php
M	resources/views/components/product-card.blade.php
M	resources/views/confirmation.blade.php
M	resources/views/home.blade.php
M	resources/views/page.blade.php
M	resources/views/product.blade.php
M	resources/views/shop.blade.php
M	resources/views/track.blade.php
M	resources/views/wishlist.blade.php
M	routes/web.php
M	tests/Feature/MediaManagementTest.php
M	vite.config.js
```

## New files

```text
app/Http/Controllers/AdminOperationsController.php
app/Http/Controllers/AdminReportsController.php
app/Http/Controllers/AdminSiteController.php
app/Http/Middleware/AdminPermission.php
app/Services/AdminAccess.php
app/Services/AdminSignals.php
app/Services/CouponPricing.php
database/migrations/2026_10_27_153317_extend_store_for_admin_management.php
docs/ADMIN.md
public/build/assets/app-BSLnDPQm.css
public/build/assets/app-C6-kNMqm.js
resources/js/shop-state.js
resources/views/admin/customer.blade.php
resources/views/admin/document.blade.php
resources/views/admin/homepage.blade.php
resources/views/admin/notifications.blade.php
resources/views/admin/payments.blade.php
resources/views/admin/preview.blade.php
resources/views/admin/reports.blade.php
resources/views/admin/reviews.blade.php
resources/views/admin/search.blade.php
resources/views/admin/seo.blade.php
resources/views/admin/shipping.blade.php
resources/views/admin/staff.blade.php
resources/views/components/form-field.blade.php
resources/views/components/variant-picker.blade.php
resources/views/home-sections/benefits.blade.php
resources/views/home-sections/best_sellers.blade.php
resources/views/home-sections/brand.blade.php
resources/views/home-sections/campaign.blade.php
resources/views/home-sections/categories.blade.php
resources/views/home-sections/faq.blade.php
resources/views/home-sections/featured.blade.php
resources/views/home-sections/hero.blade.php
resources/views/home-sections/new_arrivals.blade.php
resources/views/home-sections/offers.blade.php
resources/views/home-sections/testimonials.blade.php
resources/views/maintenance.blade.php
tests/Feature/AdminCommerceTest.php
tests/Feature/AdminDashboardTest.php
tests/Feature/CartQueryTest.php
tests/Feature/StorefrontExperienceTest.php
tests/Frontend/shop-state.test.mjs
docs/ADMIN-CHANGES.md
```
