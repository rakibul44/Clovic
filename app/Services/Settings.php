<?php

namespace App\Services;

use App\Models\StoreSetting;

class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return StoreSetting::where('key', $key)->value('value') ?? $default;
    }

    public static function delivery(string $area, int $netSubtotal): int
    {
        $threshold = (int) self::get('free_shipping_threshold', 0);
        if ($threshold > 0 && $netSubtotal >= $threshold) {
            return 0;
        }

        return (int) self::get($area === 'inside' ? 'delivery_inside' : 'delivery_outside', $area === 'inside' ? 8000 : 15000);
    }

    public static function money(int $amount): string
    {
        return '৳'.number_format($amount / 100, $amount % 100 ? 2 : 0);
    }
}
