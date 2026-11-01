<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponPricing
{
    public function customerKey(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }$phone = preg_replace('/^(?:\+?88)/', '', preg_replace('/[\s-]/', '', $phone));

        return hash_hmac('sha256', 'phone:'.$phone, (string) config('app.key'));
    }

    public function calculate(?string $code, $lines, ?string $phone = null, bool $lock = false): array
    {
        if (! $code) {
            return ['coupon' => null, 'discount' => 0, 'eligible' => 0, 'customer_key' => $this->customerKey($phone)];
        }
        $query = Coupon::where('code', strtoupper(trim($code)));
        if ($lock) {
            $query->lockForUpdate();
        }$coupon = $query->first();
        $subtotal = $lines->sum(fn ($line) => $line['variant']->current_price * $line['quantity']);
        $key = $this->customerKey($phone);
        if (! $coupon || ! $coupon->active || ($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->expires_at && $coupon->expires_at->isPast()) || $coupon->used >= $coupon->usage_limit || $subtotal < $coupon->minimum) {
            $this->invalid();
        }
        if ($coupon->per_customer_limit) {
            if (! $key) {
                throw ValidationException::withMessages(['coupon' => 'Enter your checkout phone number to check this coupon.']);
            }$used = DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->where('customer_key', $key)->count();
            if ($used >= $coupon->per_customer_limit) {
                $this->invalid();
            }
        }
        $eligible = $lines->filter(fn ($line) => (! $coupon->product_id || $line['variant']->product_id === $coupon->product_id) && (! $coupon->category_id || $line['variant']->product->category_id === $coupon->category_id))->sum(fn ($line) => $line['variant']->current_price * $line['quantity']);
        if ($eligible < 1) {
            $this->invalid();
        }
        $discount = $coupon->type === 'fixed' ? min($eligible, $coupon->amount) : intdiv($eligible * $coupon->percent, 100);
        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, $coupon->maximum_discount);
        }

        return ['coupon' => $coupon, 'discount' => $discount, 'eligible' => $eligible, 'customer_key' => $key];
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['coupon' => 'This coupon is not available for this order.']);
    }
}
