<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;

class Admin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(AdminAccess::staff($request->user()), 403);

        return $next($request);
    }
}
