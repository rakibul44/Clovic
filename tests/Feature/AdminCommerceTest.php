<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Services\Checkout;
use App\Services\CouponPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminCommerceTest extends TestCase
{
    use RefreshDatabase;

    private function variant(): ProductVariant
    {
        $v = ProductVariant::factory()->create(['stock' => 10, 'price_override' => 100000]);
        $v->product->update(['demo' => false, 'cost_price' => 45000]);

        return $v;
    }

    private function order(array $cart, ?string $coupon = null, string $phone = '01712345678')
    {
        return app(Checkout::class)->place(['name' => 'Test buyer', 'phone' => $phone, 'district' => 'Dhaka', 'area' => 'inside', 'address' => 'Test address', 'coupon' => $coupon, 'idempotency_key' => (string) Str::uuid()], $cart, 'owner');
    }

    private function coupon(array $extra = []): Coupon
    {
        return Coupon::create($extra + ['code' => 'TEST', 'type' => 'percent', 'percent' => 20, 'amount' => 0, 'minimum' => 0, 'usage_limit' => 100, 'used' => 0, 'active' => true]);
    }

    public function test_variant_price_and_cost_are_snapshotted_and_free_shipping_uses_discounted_total(): void
    {
        $v = $this->variant();
        StoreSetting::updateOrCreate(['key' => 'free_shipping_threshold'], ['value' => '95000']);
        $this->coupon();
        $order = $this->order([$v->id => 1], 'TEST');
        $this->assertSame(100000, $order->subtotal);
        $this->assertSame(20000, $order->discount);
        $this->assertSame(8000, $order->delivery);
        $this->assertSame(45000, $order->items->first()->unit_cost);
        $this->assertSame(100000, $order->items->first()->unit_price);
        $v->update(['price_override' => 110000]);
        $v->product->update(['cost_price' => 1]);
        $this->assertSame(45000, $order->fresh()->items->first()->unit_cost);
        $this->assertSame(0, $this->order([$v->id => 1])->delivery);
    }

    public function test_fixed_coupon_is_scoped_and_discount_allocation_never_touches_other_products(): void
    {
        $a = $this->variant();
        $b = $this->variant();
        $this->coupon(['type' => 'fixed', 'amount' => 150000, 'product_id' => $a->product_id]);
        $o = $this->order([$a->id => 1, $b->id => 1], 'TEST');
        $this->assertSame(100000, $o->discount);
        $this->assertEquals(100000, $o->items->firstWhere('product_variant_id', $a->id)->discount);
        $this->assertEquals(0, $o->items->firstWhere('product_variant_id', $b->id)->discount);
        $this->assertSame(100000, $o->items->sum('discount'));
    }

    public function test_coupon_cap_and_normalized_phone_limit_are_enforced_without_stock_loss(): void
    {
        $v = $this->variant();
        $c = $this->coupon(['maximum_discount' => 5000, 'per_customer_limit' => 1]);
        $o = $this->order([$v->id => 1], 'TEST');
        $this->assertSame(5000, $o->discount);
        $this->assertDatabaseHas('coupon_redemptions', ['coupon_id' => $c->id, 'discount' => 5000]);
        try {
            $this->order([$v->id => 1], 'TEST', '+8801712345678');
            $this->fail('Repeated customer coupon must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('coupon', $e->errors());
        }$this->assertSame(9, $v->fresh()->stock);
        $this->assertSame(1, $c->fresh()->used);
    }

    public function test_future_expired_and_unmatched_category_coupons_are_rejected(): void
    {
        $v = $this->variant();
        $other = $this->variant();
        $c = $this->coupon(['starts_at' => now()->addDay()]);
        $pricing = app(CouponPricing::class);
        $lines = collect([['variant' => $v, 'quantity' => 1]]);
        foreach ([['starts_at' => now()->addDay()], ['starts_at' => null, 'expires_at' => now()->subDay()], ['expires_at' => null, 'category_id' => $other->product->category_id]] as $values) {
            $c->update($values);
            try {
                $pricing->calculate('TEST', $lines, '01712345678');
                $this->fail('Unavailable coupon must fail');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('coupon', $e->errors());
            }
        }
    }
}
