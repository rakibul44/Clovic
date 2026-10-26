<?php

namespace App\Services;

use App\Models\StoreSetting;

class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return StoreSetting::where('key', $key)->value('value') ?? $default;
    }

    public static function money(int $amount): string
    {
        return '৳'.number_format($amount / 100, $amount % 100 ? 2 : 0);
    }
}
