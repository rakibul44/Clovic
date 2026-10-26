<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Checkout
{
    public function place(array $data, array $cart, string $owner): Order
    {
        return DB::transaction(function () use ($data, $cart, $owner) {
            DB::table('checkout_attempts')->insertOrIgnore(['token' => $data['idempotency_key'], 'owner' => $owner, 'created_at' => now(), 'updated_at' => now()]);
            $attempt = DB::table('checkout_attempts')->where('token', $data['idempotency_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($attempt->owner, $owner), 403);
            $existing = Order::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->checkout_owner, $owner), 403);

                return $existing;
            }
            if (! $cart) {
                throw ValidationException::withMessages(['cart' => 'Your shopping bag is empty.']);
            }
            ksort($cart);
            $lines = [];
            $subtotal = 0;
            foreach ($cart as $id => $quantity) {
                $v = ProductVariant::with('product.category')->lockForUpdate()->find($id);
                if (! $v || $quantity < 1 || $quantity > 99 || $v->stock < $quantity || $v->product->status !== 'published' || ! $v->product->category->published) {
                    throw ValidationException::withMessages(['cart' => 'An item is unavailable. Please review your bag.']);
                }
                if ($v->product->demo && ! config('store.allow_demo_checkout')) {
                    throw ValidationException::withMessages(['cart' => 'Demonstration products cannot be ordered in this environment.']);
                }
                $subtotal += $v->product->current_price * $quantity;
                $lines[] = [$v, $quantity];
            }
            $discount = 0;
            $coupon = null;
            if (! empty($data['coupon'])) {
                $coupon = Coupon::where('code', strtoupper($data['coupon']))->lockForUpdate()->first();
                if (! $coupon || ! $coupon->active || ($coupon->expires_at && $coupon->expires_at->isPast()) || $coupon->used >= $coupon->usage_limit || $subtotal < $coupon->minimum) {
                    throw ValidationException::withMessages(['coupon' => 'This coupon is not available for this order.']);
                }
                $discount = intdiv($subtotal * $coupon->percent, 100);
            }
            $delivery = (int) Settings::get($data['area'] === 'inside' ? 'delivery_inside' : 'delivery_outside', $data['area'] === 'inside' ? 8000 : 15000);
            $order = Order::create(collect($data)->only(['name', 'phone', 'email', 'district', 'area', 'address', 'notes', 'idempotency_key'])->all() + ['reference' => 'CLV-'.strtoupper(Str::random(12)), 'checkout_owner' => $owner, 'user_id' => auth()->id(), 'subtotal' => $subtotal, 'delivery' => $delivery, 'discount' => $discount, 'total' => $subtotal + $delivery - $discount]);
            $remainingDiscount = $discount;
            $lineIndex = 0;
            foreach ($lines as [$v,$quantity]) {
                $lineIndex++;
                $lineDiscount = $lineIndex === count($lines) ? $remainingDiscount : intdiv($discount * $v->product->current_price * $quantity, $subtotal);
                $remainingDiscount -= $lineDiscount;
                $order->items()->create(['product_variant_id' => $v->id, 'product_name' => $v->product->name, 'sku' => $v->sku, 'size' => $v->size, 'color' => $v->color, 'quantity' => $quantity, 'unit_price' => $v->product->current_price, 'discount' => $lineDiscount]);
                $changed = ProductVariant::whereKey($v->id)->where('stock', '>=', $quantity)->decrement('stock', $quantity);
                if (! $changed) {
                    throw ValidationException::withMessages(['cart' => 'Stock changed. Please try again.']);
                }
                InventoryMovement::create(['product_variant_id' => $v->id, 'quantity' => -$quantity, 'reason' => 'checkout reservation', 'reference' => $order->reference]);
            }
            if ($coupon) {
                $coupon->increment('used');
                DB::table('coupon_redemptions')->insert(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $order->payments()->create(['method' => 'cod', 'status' => 'unpaid', 'amount' => $order->total]);
            $order->history()->create(['status' => 'pending', 'user_id' => auth()->id()]);

            return $order;
        }, 5);
    }
}
