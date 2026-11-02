<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Checkout;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function variant(): ProductVariant
    {
        $variant = ProductVariant::factory()->create(['stock' => 4, 'sku' => 'STYLE-NAVY-M']);
        $variant->product->update(['demo' => false, 'price' => 120000, 'name' => 'Considered polo', 'new_arrival' => true]);

        return $variant;
    }

    public function test_search_suggestions_match_sku_and_return_images_and_real_prices(): void
    {
        $variant = $this->variant();
        $this->getJson('/search-suggestions?q=STYLE-NAVY')->assertOk()->assertJsonPath('0.name', 'Considered polo')->assertJsonPath('0.price', 'TK 1,200')->assertJsonStructure([['id', 'name', 'slug', 'image', 'price']]);
        $variant->product->update(['status' => 'draft']);
        $this->getJson('/search-suggestions?q=STYLE-NAVY')->assertOk()->assertExactJson([]);
    }

    public function test_ajax_cart_returns_trusted_totals_and_updates_quantity_without_navigation(): void
    {
        $variant = $this->variant();
        $this->postJson('/cart', ['variant_id' => $variant->id, 'quantity' => 2, 'add' => 1, 'price' => 1, 'subtotal' => 1])->assertOk()->assertJsonPath('cart.count', 2)->assertJsonPath('cart.subtotal', 240000)->assertJsonPath('cart.items.0.size', 'M')->assertJsonPath('redirect', null);
        $this->getJson('/cart/summary')->assertOk()->assertJsonPath('count', 2)->assertJsonPath('subtotal_label', 'TK 2,400');
        $this->postJson('/cart', ['variant_id' => $variant->id, 'quantity' => 5])->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->getJson('/cart/summary')->assertJsonPath('count', 2);
        $this->postJson('/cart', ['variant_id' => $variant->id, 'quantity' => 0])->assertOk()->assertJsonPath('cart.count', 0)->assertJsonPath('cart.items', []);
    }

    public function test_buy_now_adds_selected_variant_then_redirects_to_guest_checkout(): void
    {
        $variant = $this->variant();
        $this->postJson('/cart', ['variant_id' => $variant->id, 'quantity' => 1, 'add' => 1, 'buy_now' => 1])->assertOk()->assertJsonPath('redirect', '/checkout');
        $this->get('/checkout')->assertOk()->assertSee('Customer information')->assertSee('Amount payable')->assertSee('Cash on Delivery');
        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1, 'add' => 1, 'buy_now' => 1])->assertRedirect('/checkout');
    }

    public function test_ajax_wishlist_requires_login_and_preserves_toggle_state(): void
    {
        $variant = $this->variant();
        $this->postJson('/wishlist', ['product_id' => $variant->product_id])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/wishlist', ['product_id' => $variant->product_id])->assertOk()->assertJsonPath('saved', true);
        $this->postJson('/wishlist', ['product_id' => $variant->product_id])->assertOk()->assertJsonPath('saved', false);
    }

    public function test_new_storefront_has_selection_controls_and_admin_has_independent_shell(): void
    {
        $variant = $this->variant();
        $this->seed();
        $this->get('/product/'.$variant->product->slug)->assertOk()->assertSee('Choose size & add', false)->assertSee('Buy now')->assertSee('Size guide');
        $this->get('/shop')->assertOk()->assertSee('Filters & sorting', false)->assertSee('Choose options');
        $owner = User::factory()->create();
        $owner->forceFill(['role' => 'owner'])->save();
        foreach (['/admin', '/admin/reports', '/admin/reviews', '/admin/settings'] as $path) {
            $this->actingAs($owner)->get($path)->assertOk()->assertSee('CLOVIC WORKSPACE')->assertDontSee('THE CLOVIC LETTER');
        }
    }

    public function test_customer_and_empty_cart_state_are_not_shared_between_sessions(): void
    {
        $variant = $this->variant();
        $this->postJson('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $response = $this->get('/')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->getJson('/cart/summary')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_homepage_section_controls_hide_new_arrivals_without_removing_shop_products(): void
    {
        $variant = $this->variant();
        StoreSetting::create(['key' => 'show_new_arrivals', 'value' => '0']);
        $this->get('/')->assertOk()->assertDontSee('New to your wardrobe.');
        $this->get('/category/new-arrivals')->assertOk()->assertSee('Considered polo');
    }

    public function test_review_moderation_rejects_unverified_purchases_and_requires_admin(): void
    {
        $variant = $this->variant();
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $order = app(Checkout::class)->place(['name' => 'Fixture', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => 'Test address', 'idempotency_key' => (string) Str::uuid()], [$variant->id => 1], 'test-owner');
        $id = DB::table('reviews')->insertGetId(['order_item_id' => $order->items->first()->id, 'user_id' => $customer->id, 'rating' => 4, 'body' => 'Genuine test fixture', 'approved' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/admin/reviews/'.$id, ['approved' => 1])->assertForbidden();
        $owner = User::factory()->create();
        $owner->forceFill(['role' => 'owner'])->save();
        $this->actingAs($owner)->post('/admin/reviews/'.$id, ['approved' => 1])->assertUnprocessable();
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $order = app(OrderWorkflow::class)->transition($order, $status, $owner->id);
        }
        $order->update(['payment_status' => 'paid']);
        $this->post('/admin/reviews/'.$id, ['approved' => 1])->assertRedirect();
        $this->assertDatabaseHas('reviews', ['id' => $id, 'approved' => true]);
        $this->get('/product/'.$variant->product->slug)->assertOk()->assertSee('Genuine test fixture');
        $this->post('/admin/reviews/'.$id, ['approved' => 0])->assertRedirect();
        $this->get('/product/'.$variant->product->slug)->assertOk()->assertDontSee('Genuine test fixture');
    }
}
