<?php

namespace App\Services;

use App\Models\User;

class AdminAccess
{
    public const ROLES = ['owner' => 'Super Admin', 'admin' => 'Administrator', 'store_manager' => 'Store Manager', 'product_manager' => 'Product Manager', 'order_manager' => 'Order Manager', 'content_manager' => 'Content Manager', 'support' => 'Support Staff'];

    public const GRANTS = [
        'owner' => ['*'], 'admin' => ['overview', 'products', 'categories', 'inventory', 'orders', 'customers', 'coupons', 'content', 'homepage', 'reviews', 'shipping', 'shipping.configure', 'payments', 'reports', 'seo', 'notifications', 'settings', 'logs', 'cost'],
        'store_manager' => ['overview', 'products', 'categories', 'inventory', 'orders', 'customers', 'coupons', 'reviews', 'shipping', 'shipping.configure', 'payments', 'reports', 'notifications'],
        'product_manager' => ['overview', 'products', 'categories', 'inventory', 'notifications'],
        'order_manager' => ['overview', 'orders', 'customers', 'shipping', 'payments', 'notifications'],
        'content_manager' => ['overview', 'categories', 'content', 'homepage', 'reviews', 'seo', 'notifications'],
        'support' => ['overview', 'orders.read', 'customers.read', 'shipping.read', 'notifications'],
    ];

    public static function can(?User $user, string $permission): bool
    {
        if (! $user?->active) {
            return false;
        }$grants = self::GRANTS[$user->role] ?? [];

        return in_array('*', $grants, true) || in_array($permission, $grants, true);
    }

    public static function canRead(?User $user, string $module): bool
    {
        return self::can($user, $module) || self::can($user, $module.'.read');
    }

    public static function staff(?User $user): bool
    {
        return $user?->active && isset(self::ROLES[$user->role]);
    }

    public static function module(string $path, ?string $resource = null): string
    {
        if ($path === 'admin') {
            return 'overview';
        }$segment = explode('/', $path)[1] ?? 'overview';

        return match ($segment) {
            'content' => match ($resource) {
                'categories' => 'categories','coupons' => 'coupons',default => 'content'
            },'audit' => 'logs','search' => 'overview',default => $segment
        };
    }
}
