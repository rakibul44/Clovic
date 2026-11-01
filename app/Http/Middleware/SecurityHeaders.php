<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ! $request->user()->active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403);
        }
        $timezone = Settings::get('timezone', config('app.timezone'));
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
        if (Settings::get('maintenance_mode', '0') === '1' && ! AdminAccess::staff($request->user()) && ! $request->is('admin*', 'login', 'logout', 'forgot-password', 'reset-password*', 'up')) {
            return response()->view('maintenance', [], 503)->header('Retry-After', '3600')->header('Cache-Control', 'private, no-store');
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'self'");
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if ($request->is('admin*', 'account*', 'cart*', 'checkout*', 'order*', 'track-order*', 'login', 'register', 'wishlist', 'forgot-password', 'reset-password*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->user() || count(session('cart', [])) > 0) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
