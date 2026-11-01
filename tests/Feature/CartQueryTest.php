<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_drawer_queries_do_not_grow_with_each_item(): void
    {
        $this->actingAs(User::factory()->create());
        $cart = app(Cart::class);
        foreach (ProductVariant::factory()->count(8)->create(['stock' => 5]) as $variant) {
            $cart->set($variant->id, 1);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $snapshot = $cart->snapshot();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertEquals(8, $snapshot['count']);
        $this->assertCount(8, $snapshot['items']);
        $this->assertLessThanOrEqual(7, $queries, 'Drawer loading should use eager loading and read quantities once.');
    }
}
