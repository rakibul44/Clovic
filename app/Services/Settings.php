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
        return 'TK '.number_format($amount / 100, $amount % 100 ? 2 : 0);
    }

    public static function decimal(?int $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return ($amount < 0 ? '-' : '').intdiv(abs($amount), 100).'.'.str_pad((string) (abs($amount) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function auditAmounts(mixed $value, ?string $key = null): mixed
    {
        $money = ['price', 'sale_price', 'cost_price', 'price_override', 'amount', 'minimum', 'maximum_discount', 'subtotal', 'delivery', 'discount', 'total', 'unit_price', 'unit_cost', 'delivery_inside', 'delivery_outside', 'free_shipping_threshold'];
        if (is_array($value)) {
            return collect($value)->map(fn ($item, $field) => self::auditAmounts($item, (string) $field))->all();
        }
        if ($value !== null && in_array($key, $money, true) && is_numeric($value)) {
            return self::money((int) $value);
        }

        return $value;
    }
}
