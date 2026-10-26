<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Checkout;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function variant(int $stock = 5): ProductVariant
    {
        $c = Category::create(['name' => 'Polo shirts', 'slug' => 'polo-shirts', 'published' => true]);
        $p = Product::create(['category_id' => $c->id, 'name' => 'Test polo', 'slug' => 'test-polo', 'description' => 'An accurately described test item.', 'price' => 120000, 'status' => 'published']);

        return $p->variants()->create(['sku' => 'TEST-M', 'size' => 'M', 'color' => 'Navy', 'stock' => $stock]);
    }

    private function data(): array
    {
        return ['name' => 'Test Customer', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => '123 Test Road, Dhaka', 'idempotency_key' => (string) Str::uuid()];
    }

    public function test_public_pages_and_seo_render(): void
    {
        $v = $this->variant();
        $this->seed();
        foreach (['/', '/shop', '/category/polo-shirts', '/product/test-polo', '/cart', '/track-order', '/login', '/register', '/forgot-password', '/about', '/contact', '/faq', '/shipping-policy', '/return-exchange-policy', '/privacy-policy', '/terms-and-conditions', '/size-guide', '/blog'] as $path) {
            $this->get($path)->assertOk();
        }
        $html = $this->get('/product/test-polo')->assertSee('rel="canonical"', false)->assertSee('priceCurrency', false)->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        foreach ($matches[1] as $json) {
            $this->assertIsArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        }
        $this->get('/sitemap.xml')->assertOk()->assertSee('/product/test-polo')->assertDontSee('/admin');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin');
        $this->get('/product/missing')->assertNotFound();
        $this->get('/category/missing')->assertNotFound();
    }

    public function test_server_prices_delivery_and_snapshots(): void
    {
        $v = $this->variant();
        $d = $this->data();
        $d['price'] = 1;
        $d['total'] = 1;
        $o = app(Checkout::class)->place($d, [$v->id => 2], 'owner');
        $this->assertEquals(248000, $o->total);
        $this->assertEquals(3, $v->fresh()->stock);
        $this->assertEquals('unpaid', $o->payment_status);
        $v->product->update(['name' => 'Changed', 'price' => 90000]);
        $this->assertEquals('Test polo', $o->items->first()->product_name);
        $this->assertEquals(120000, $o->items->first()->unit_price);
    }

    public function test_outside_delivery_and_coupon(): void
    {
        $v = $this->variant();
        Coupon::create(['code' => 'TEN', 'percent' => 10, 'minimum' => 100, 'usage_limit' => 1, 'active' => true]);
        $d = $this->data() + ['coupon' => 'TEN'];
        $d['area'] = 'outside';
        $o = app(Checkout::class)->place($d, [$v->id => 1], 'owner');
        $this->assertEquals(123000, $o->total);
        $this->assertEquals(12000, $o->discount);
        $this->assertEquals(1, Coupon::first()->used);
    }

    public function test_duplicate_checkout_does_not_decrement_twice(): void
    {
        $v = $this->variant();
        $d = $this->data();
        $service = app(Checkout::class);
        $one = $service->place($d, [$v->id => 2], 'owner');
        $two = $service->place($d, [$v->id => 2], 'owner');
        $this->assertEquals($one->id, $two->id);
        $this->assertEquals(1, Order::count());
        $this->assertEquals(3, $v->fresh()->stock);
    }

    public function test_checkout_rolls_back_all_lines(): void
    {
        $v = $this->variant();
        $other = $v->product->variants()->create(['sku' => 'TEST-L', 'size' => 'L', 'color' => 'Navy', 'stock' => 0]);
        try {
            app(Checkout::class)->place($this->data(), [$v->id => 2, $other->id => 1], 'owner');
            $this->fail('Should reject stock');
        } catch (ValidationException $e) {
        }
        $this->assertEquals(5, $v->fresh()->stock);
        $this->assertEquals(0, Order::count());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_last_stock_cannot_be_sold_twice(): void
    {
        $v = $this->variant(1);
        app(Checkout::class)->place($this->data(), [$v->id => 1], 'owner');
        try {
            app(Checkout::class)->place($this->data(), [$v->id => 1], 'other');
            $this->fail('Oversold');
        } catch (ValidationException $e) {
        }
        $this->assertEquals(0, $v->fresh()->stock);
        $this->assertEquals(1, Order::count());
    }

    public function test_cancellation_restores_once_and_rejects_transition(): void
    {
        $v = $this->variant();
        $o = app(Checkout::class)->place($this->data(), [$v->id => 2], 'owner');
        $w = app(OrderWorkflow::class);
        $w->transition($o, 'cancelled', null);
        $w->transition($o, 'cancelled', null);
        $this->assertEquals(5, $v->fresh()->stock);
        $this->expectException(ValidationException::class);
        $w->transition($o, 'delivered', null);
    }

    public function test_delivery_does_not_collect_payment(): void
    {
        $v = $this->variant();
        $o = app(Checkout::class)->place($this->data(), [$v->id => 1], 'owner');
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $o = app(OrderWorkflow::class)->transition($o, $status, null);
        }
        $this->assertEquals('unpaid', $o->fresh()->payment_status);
    }

    public function test_tracking_privacy(): void
    {
        $v = $this->variant();
        $o = app(Checkout::class)->place($this->data(), [$v->id => 1], 'owner');
        $this->post('/track-order', ['reference' => $o->reference, 'phone' => $o->phone])->assertOk()->assertSee($o->reference)->assertDontSee($o->address)->assertDontSee($o->phone)->assertDontSee($o->name);
        $this->get('/order/'.$o->reference)->assertNotFound();
    }

    public function test_guest_cart_checkout_http_flow(): void
    {
        $v = $this->variant();
        $this->post('/cart', ['variant_id' => $v->id, 'quantity' => 2, 'add' => 1])->assertRedirect();
        $this->get('/cart')->assertOk()->assertSee('Test polo');
        $this->get('/checkout')->assertOk();
        $key = session('checkout_key');
        $this->post('/checkout', array_merge($this->data(), ['idempotency_key' => $key]))->assertRedirect();
        $o = Order::firstOrFail();
        $this->get('/order/'.$o->reference)->assertOk();
        $this->assertEquals(248000, $o->total);
    }

    public function test_customer_cannot_access_admin_or_other_orders(): void
    {
        $v = $this->variant();
        $u = User::factory()->create();
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($u)->get('/admin')->assertForbidden();
        $this->actingAs($u)->post('/admin/products', [])->assertForbidden();
        $o = app(Checkout::class)->place($this->data(), [$v->id => 1], 'owner');
        $other = User::factory()->create();
        $this->actingAs($other)->get('/order/'.$o->reference)->assertNotFound();
    }

    public function test_admin_pages_render_and_invalid_upload_rejected(): void
    {
        $v = $this->variant();
        $u = User::factory()->create();
        $u->forceFill(['role' => 'owner'])->save();
        $this->seed();
        $this->actingAs($u);
        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/products/'.$v->product_id.'/edit', '/admin/inventory', '/admin/orders', '/admin/content/categories', '/admin/content/pages', '/admin/content/blog', '/admin/content/coupons', '/admin/settings', '/admin/customers', '/admin/audit'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->post('/admin/products', ['images' => [UploadedFile::fake()->create('evil.php', 1, 'application/x-httpd-php')]])->assertSessionHasErrors('images.0');
    }

    public function test_search_filters_and_counts(): void
    {
        $v = $this->variant();
        $this->get('/shop?q=TEST-M&size=M&color=Navy&available=1')->assertOk()->assertSee('Test polo')->assertSee('1 piece');
        $this->get('/shop?size=L')->assertOk()->assertSee('No pieces found.');
        $this->get('/shop?sort=invalid')->assertRedirect();
    }

    public function test_customer_registration_and_wishlist(): void
    {
        $v = $this->variant();
        $this->post('/register', ['name' => 'Customer', 'email' => 'person@example.test', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123'])->assertRedirect('/account');
        $this->assertAuthenticated();
        $this->post('/wishlist', ['product_id' => $v->product_id])->assertRedirect();
        $this->get('/wishlist')->assertOk()->assertSee('Test polo');
        $this->get('/account')->assertOk();
    }

    public function test_unpublished_products_are_not_purchasable(): void
    {
        $v = $this->variant();
        $v->product->update(['status' => 'draft']);
        $this->get('/product/test-polo')->assertNotFound();
        $this->post('/cart', ['variant_id' => $v->id, 'quantity' => 1])->assertSessionHasErrors('cart');
        $this->get('/sitemap.xml')->assertDontSee('/product/test-polo');
    }
}
