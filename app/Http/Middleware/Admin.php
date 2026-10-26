<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Admin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->active && in_array($request->user()->role, ['admin', 'owner']), 403);

        return $next($request);
    }
}
