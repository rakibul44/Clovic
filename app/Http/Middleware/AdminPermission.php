<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;

class AdminPermission
{
    public function handle(Request $request, Closure $next)
    {
        $module = AdminAccess::module($request->path(), $request->route('resource'));
        $allowed = $request->isMethod('get') ? AdminAccess::canRead($request->user(), $module) : AdminAccess::can($request->user(), $module);
        if (preg_match('#^admin/customers/[0-9]+$#', $request->path()) && $request->isMethod('post')) {
            $allowed = AdminAccess::can($request->user(), 'staff');
        }
        if ($request->is('admin/products/export', 'admin/inventory/export', 'admin/orders/export', 'admin/customers/export')) {
            $allowed = AdminAccess::can($request->user(), $module);
        }
        abort_unless($allowed, 403);

        return $next($request);
    }
}
