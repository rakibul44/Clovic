<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminOperationsController;
use App\Http\Controllers\AdminRecordController;
use App\Http\Controllers\AdminReportsController;
use App\Http\Controllers\AdminSiteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandGalleryController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StoreController;
use App\Http\Middleware\Admin;
use App\Http\Middleware\AdminPermission;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreController::class, 'home']);
Route::get('/shop', [StoreController::class, 'shop']);
Route::get('/category/{slug}', [StoreController::class, 'shop']);
Route::get('/product/{slug}', [StoreController::class, 'product']);
Route::get('/search-suggestions', [StoreController::class, 'suggestions'])->middleware('throttle:60,1');
Route::get('/blog/{slug?}', [StoreController::class, 'blog']);
Route::post('/newsletter', [StoreController::class, 'newsletter'])->middleware('throttle:5,1');
Route::get('/cart/summary', [CartController::class, 'summary']);
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'update'])->middleware('throttle:60,1')->block(10, 10);
Route::get('/checkout', [CartController::class, 'checkout']);
Route::post('/checkout/quote', [CartController::class, 'quote'])->middleware('throttle:15,1');
Route::post('/checkout', [CartController::class, 'place'])->middleware('throttle:10,1')->block(15, 15);
Route::get('/order/{reference}', [CartController::class, 'confirmation']);
Route::match(['get', 'post'], '/track-order', [CartController::class, 'track'])->middleware('throttle:10,1');
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.form', ['mode' => 'login']))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', fn () => view('auth.form', ['mode' => 'register']));
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/forgot-password', fn () => view('auth.form', ['mode' => 'forgot-password']))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.form', ['mode' => 'reset-password', 'token' => $token]))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update')->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    foreach (['/account', '/account/orders', '/account/profile'] as $path) {
        Route::get($path, [AccountController::class, 'index']);
    }
    Route::post('/account/profile', [AccountController::class, 'profile']);
    Route::get('/wishlist', [AccountController::class, 'wishlist']);
    Route::post('/wishlist', [AccountController::class, 'toggle']);
});
Route::get('/gallery', [BrandGalleryController::class, 'index']);
Route::prefix('admin')->middleware(['auth', Admin::class, AdminPermission::class])->group(function () {
    Route::get('/', [AdminReportsController::class, 'index']);
    Route::get('/gallery', [BrandGalleryController::class, 'admin']);
    Route::post('/gallery/bulk', [BrandGalleryController::class, 'bulk']);
    Route::post('/gallery/{id}', [BrandGalleryController::class, 'update']);
    Route::post('/gallery/{id}/delete', [BrandGalleryController::class, 'delete']);
    Route::get('/reports', [AdminReportsController::class, 'index']);
    Route::get('/reports/export', [AdminReportsController::class, 'export']);
    Route::get('/reviews', [AdminController::class, 'reviews']);
    Route::post('/reviews/{id}', [AdminController::class, 'review']);
    Route::get('/products', [AdminController::class, 'products']);
    Route::get('/products/create', [AdminController::class, 'productForm']);
    Route::get('/products/export', [AdminController::class, 'export']);
    Route::post('/products/import', [AdminController::class, 'import']);
    Route::post('/products/bulk', [AdminController::class, 'bulk']);
    Route::post('/products/{id}/delete', [AdminOperationsController::class, 'deleteProduct']);
    Route::post('/products/{id}/restore', [AdminOperationsController::class, 'restoreProduct']);
    Route::get('/products/{id}/preview', [AdminOperationsController::class, 'preview']);
    Route::get('/products/{id}/edit', [AdminController::class, 'productForm']);
    Route::post('/products/{id?}', [AdminController::class, 'saveProduct']);
    Route::get('/inventory', [AdminOperationsController::class, 'inventory']);
    Route::post('/inventory/bulk', [AdminOperationsController::class, 'bulkStock']);
    Route::get('/inventory/export', fn (Request $r) => app(AdminReportsController::class)->export($r->merge(['kind' => 'inventory'])));
    Route::post('/inventory', [AdminController::class, 'stock']);
    Route::get('/orders', [AdminController::class, 'orders']);
    Route::get('/orders/export', [AdminOperationsController::class, 'orderExport']);
    Route::get('/orders/{id}/document/{kind}', [AdminOperationsController::class, 'document']);
    Route::get('/orders/{id}', [AdminController::class, 'order']);
    Route::post('/orders/{id}', [AdminController::class, 'updateOrder']);
    Route::get('/content/{resource}/{id?}', [AdminController::class, 'content']);
    Route::post('/content/{resource}/{id?}', [AdminController::class, 'saveContent']);
    Route::get('/settings', [AdminController::class, 'settings']);
    Route::post('/settings', [AdminController::class, 'saveSettings']);
    Route::get('/customers', [AdminOperationsController::class, 'customers']);
    Route::get('/customers/export', [AdminOperationsController::class, 'customerExport']);
    Route::get('/customers/{id}', [AdminOperationsController::class, 'customerDetail']);
    Route::post('/customers/{id}/notes', [AdminOperationsController::class, 'customerNotes']);
    Route::post('/customers/{id}', [AdminController::class, 'customer']);
    Route::get('/search', [AdminOperationsController::class, 'search']);
    Route::get('/notifications', [AdminOperationsController::class, 'notifications']);
    Route::post('/notifications/read', [AdminOperationsController::class, 'readNotifications']);
    Route::get('/staff', [AdminOperationsController::class, 'staff']);
    Route::post('/staff/{id?}', [AdminOperationsController::class, 'saveStaff']);
    Route::get('/shipping', [AdminOperationsController::class, 'shipping']);
    Route::post('/shipping/settings', [AdminOperationsController::class, 'shippingSettings']);
    Route::post('/shipping/{id}', [AdminOperationsController::class, 'shipment']);
    Route::get('/payments', [AdminOperationsController::class, 'payments']);
    Route::post('/payments/{id}', [AdminOperationsController::class, 'payment']);
    Route::get('/homepage', [AdminSiteController::class, 'homepage']);
    Route::post('/homepage/{id}', [AdminSiteController::class, 'section']);
    Route::get('/seo', [AdminSiteController::class, 'seo']);
    Route::post('/seo', [AdminSiteController::class, 'seoSettings']);
    Route::post('/seo/{type}/{id}', [AdminSiteController::class, 'seoContent']);
    Route::post('/seo/redirects', [AdminSiteController::class, 'redirect']);
    Route::post('/seo/redirects/{id}/delete', [AdminSiteController::class, 'deleteRedirect']);
    Route::post('/settings/advanced', [AdminSiteController::class, 'advancedSettings']);
    Route::post('/content/{resource}/{id}/delete', [AdminRecordController::class, 'deleteContent']);
    Route::post('/reviews/{id}/delete', [AdminRecordController::class, 'deleteReview']);
    Route::post('/customers/{id}/status', [AdminRecordController::class, 'customerStatus']);
    Route::post('/staff/{id}/status', [AdminRecordController::class, 'staffStatus']);
    Route::post('/homepage/{id}/remove', [AdminRecordController::class, 'removeSection']);
    Route::get('/audit', [AdminController::class, 'audit']);
});
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/{slug}', [StoreController::class, 'page'])->where('slug', 'about|contact|faq|shipping-policy|return-exchange-policy|privacy-policy|terms-and-conditions|size-guide');
Route::fallback(function (Request $r) {
    $path = $r->path();
    if (Product::published()->where('slug', basename($path))->exists() && str_starts_with($path, 'product/')) {
        abort(404);
    }
    $seen = [];
    for ($i = 0; $i < 10; $i++) {
        if (isset($seen[$path])) {
            abort(404);
        }
        $seen[$path] = true;
        $next = DB::table('slug_redirects')->where('old_path', $path)->value('new_path');
        if (! $next) {
            break;
        }
        $path = $next;
    }
    if ($path !== $r->path()) {
        return redirect('/'.$path, 301);
    }
    abort(404);
});
