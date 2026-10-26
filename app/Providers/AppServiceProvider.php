<?php

namespace App\Providers;

use App\Models\StoreSetting;
use Illuminate\Pagination\Paginator;
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
        });
        Paginator::useTailwind();
    }
}
