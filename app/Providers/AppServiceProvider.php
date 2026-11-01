<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\StoreSetting;
use App\Services\AdminAccess;
use App\Services\AdminSignals;
use App\Services\Cart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::createAssetPathsUsing(fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/'));
        URL::forceRootUrl(config('app.url'));
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        View::composer('*', function ($view) {
            $request = request();
            if (! $request->attributes->has('store_settings')) {
                $request->attributes->set('store_settings', StoreSetting::pluck('value', 'key')->all());
            }
            $view->with('store', $request->attributes->get('store_settings'));
            if ($request->is('admin*') && AdminAccess::staff($request->user())) {
                if (! $request->attributes->has('admin_signals')) {
                    $request->attributes->set('admin_signals', app(AdminSignals::class)->forUser($request->user()));
                }
                $view->with('adminSignals', $request->attributes->get('admin_signals'));
            }
            if (! $request->is('admin*')) {
                if (! $request->attributes->has('storefront_data')) {
                    $wishlistId = auth()->check() ? DB::table('wishlists')->where('user_id', auth()->id())->value('id') : null;
                    $request->attributes->set('storefront_data', [
                        'cartState' => app(Cart::class)->snapshot(),
                        'navigationCategories' => Category::where('published', true)->orderBy('position')->orderBy('name')->get(),
                        'customNavigation' => collect(json_decode($request->attributes->get('store_settings')['navigation'] ?? '[]', true) ?: [])->reject(fn ($link) => in_array($link['path'], ['/shop', '/category/new-arrivals', '/category/sale', '/category/polo-shirts'], true))->values()->all(),
                        'wishlistIds' => $wishlistId ? DB::table('wishlist_items')->where('wishlist_id', $wishlistId)->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [],
                    ]);
                }
                foreach ($request->attributes->get('storefront_data') as $key => $value) {
                    $view->with($key, $value);
                }
            }
        });
        Paginator::useTailwind();
    }
}
