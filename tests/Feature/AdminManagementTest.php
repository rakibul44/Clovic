<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Checkout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'owner'])->save();

        return $user;
    }

    private function payload(int $category): array
    {
        return ['name' => 'Actual polo', 'slug' => 'actual-polo', 'category_id' => $category, 'description' => 'Administrator supplied description', 'price' => 150000, 'status' => 'published', 'seo_title' => 'Actual polo | Clovic', 'meta_description' => 'Accurate product summary.', 'variants' => [['sku' => 'ACTUAL-M', 'size' => 'M', 'color' => 'Black', 'stock' => 8, 'low_stock_threshold' => 2]]];
    }

    public function test_category_product_image_edit_and_slug_redirect(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner());
        $this->post('/admin/content/categories', ['name' => 'Polo shirts', 'slug' => 'polo-shirts', 'published' => 1])->assertRedirect();
        $category = Category::firstOrFail();
        $data = $this->payload($category->id);
        $data['images'] = [UploadedFile::fake()->image('real-product.jpg', 600, 750)];
        $data['image_alt'] = 'Black polo front view';
        $this->post('/admin/products', $data)->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertDatabaseHas('product_variants', ['sku' => 'ACTUAL-M', 'stock' => 8]);
        $this->assertDatabaseHas('inventory_movements', ['quantity' => 8]);
        Storage::disk('public')->assertExists($product->images->first()->path);
        unset($data['images']);
        $data['slug'] = 'updated-polo';
        $data['variants'][0]['stock'] = 6;
        $this->post('/admin/products/'.$product->id, $data)->assertRedirect();
        $this->assertEquals(6, ProductVariant::first()->stock);
        $this->get('/product/actual-polo')->assertRedirect('/product/updated-polo')->assertStatus(301);
        $this->get('/product/updated-polo')->assertOk();
    }

    public function test_inventory_rejects_negative_and_records_adjustment(): void
    {
        $v = ProductVariant::factory()->create(['stock' => 3]);
        $this->actingAs($this->owner());
        $this->post('/admin/inventory', ['variant_id' => $v->id, 'quantity' => -4, 'reason' => 'Invalid'])->assertStatus(422);
        $this->assertEquals(3, $v->fresh()->stock);
        $this->post('/admin/inventory', ['variant_id' => $v->id, 'quantity' => 2, 'reason' => 'Stock in'])->assertRedirect();
        $this->assertEquals(5, $v->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', ['quantity' => 2, 'reason' => 'Stock in']);
    }

    public function test_csv_import_export_and_bulk_status(): void
    {
        $c = Category::factory()->create();
        $this->actingAs($this->owner());
        $csv = "name,slug,category_id,price,sale_price,status,description,sku,size,color,stock\nImported shirt,imported-shirt,{$c->id},100000,,draft,Accurate description,IMPORT-M,M,Blue,7\n";
        $this->post('/admin/products/import', ['file' => UploadedFile::fake()->createWithContent('catalog.csv', $csv)])->assertRedirect();
        $p = Product::firstOrFail();
        $this->assertEquals(7, $p->variants->first()->stock);
        $this->get('/admin/products/export')->assertOk()->assertDownload('clovic-products.csv');
        $this->post('/admin/products/bulk', ['ids' => [$p->id], 'status' => 'published'])->assertRedirect();
        $this->assertEquals('published', $p->fresh()->status);
    }

    public function test_disabled_customer_and_role_escalation_are_blocked(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/account/profile', ['name' => 'Changed', 'role' => 'owner'])->assertRedirect();
        $this->assertEquals('customer', $u->fresh()->role);
        $u->forceFill(['active' => false])->save();
        $this->actingAs($u)->get('/account')->assertForbidden();
    }

    public function test_settings_and_coupon_preview_are_server_calculated(): void
    {
        $this->seed();
        $v = ProductVariant::factory()->create(['stock' => 3]);
        $v->product->update(['demo' => false, 'price' => 100000]);
        $this->actingAs($this->owner())->post('/admin/content/coupons', ['code' => 'SAVE', 'percent' => 15, 'minimum' => 0, 'usage_limit' => 3, 'active' => 1])->assertRedirect();
        auth()->logout();
        $this->post('/cart', ['variant_id' => $v->id, 'quantity' => 1])->assertRedirect();
        $this->get('/checkout')->assertOk();
        $this->post('/checkout/quote', ['coupon' => 'SAVE'])->assertRedirect('/checkout');
        $this->get('/checkout')->assertOk()->assertSee('৳150')->assertSee('Amount payable');
    }

    public function test_password_reset_uses_laravel_broker_and_invalidates_old_password(): void
    {
        $u = User::factory()->create();
        $token = Password::createToken($u);
        $this->post('/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewSecurePassword123', $u->fresh()->password));
        $this->post('/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'AnotherSecurePassword123', 'password_confirmation' => 'AnotherSecurePassword123'])->assertSessionHasErrors('email');
    }

    public function test_stock_and_order_rollback_when_snapshot_write_fails(): void
    {
        $v = ProductVariant::factory()->create(['stock' => 4]);
        $v->product->update(['demo' => false]);
        OrderItem::creating(function () {
            throw new \RuntimeException('Injected write failure');
        });
        try {
            app(Checkout::class)->place(['name' => 'Test', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => '123 Test address', 'idempotency_key' => (string) Str::uuid()], [$v->id => 1], 'test-owner');
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Injected write failure', $e->getMessage());
        } finally {
            OrderItem::flushEventListeners();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertEquals(4, $v->fresh()->stock);
    }

    public function test_settings_validate_navigation_and_change_delivery(): void
    {
        $this->seed();
        $this->actingAs($this->owner());
        $data = ['brand' => 'Clovic', 'announcement' => 'New edit', 'delivery_inside' => 9000, 'delivery_outside' => 16000, 'low_stock_threshold' => 3, 'hero_title' => 'Everyday style.', 'hero_second_line' => 'Elevated.', 'hero_text' => 'The considered edit.', 'restore_stock' => '1', 'show_featured' => '1', 'show_offers' => '1', 'navigation' => json_encode([['label' => 'Shop', 'path' => 'javascript:alert(1)']])];
        $this->post('/admin/settings', $data)->assertSessionHasErrors('links.0.path');
        $data['navigation'] = json_encode([['label' => 'Shop', 'path' => '/shop']]);
        $this->post('/admin/settings', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('store_settings', ['key' => 'delivery_inside', 'value' => '9000']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'settings.saved']);
    }

    public function test_order_status_failure_rolls_back_payment_and_notes(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->product->update(['demo' => false]);
        $order = app(Checkout::class)->place(['name' => 'Fixture', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => 'Test address', 'idempotency_key' => (string) Str::uuid()], [$variant->id => 1], 'test-owner');
        $this->actingAs($this->owner())->post('/admin/orders/'.$order->id, ['status' => 'delivered', 'payment_status' => 'paid', 'internal_notes' => 'must rollback'])->assertSessionHasErrors('status');
        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('unpaid', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->internal_notes);
        $this->assertEquals('unpaid', $order->payments->first()->status);
        $this->get('/admin/orders/'.$order->id)->assertOk();
    }

    public function test_customer_can_place_a_new_order_and_retry_an_old_submission(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $variant->product->update(['demo' => false]);
        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->get('/checkout')->assertOk();
        $firstKey = session('checkout_key');
        $data = ['name' => 'Fixture', 'phone' => '+8801712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => 'Test address', 'idempotency_key' => $firstKey];
        $this->post('/checkout', $data)->assertRedirect();
        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->get('/checkout')->assertOk();
        $secondKey = session('checkout_key');
        $this->assertNotEquals($firstKey, $secondKey);
        $data['idempotency_key'] = $secondKey;
        $this->post('/checkout', $data)->assertRedirect();
        $data['idempotency_key'] = $firstKey;
        $this->post('/checkout', $data)->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->assertEquals(3, $variant->fresh()->stock);
        $this->assertEquals('01712345678', Order::first()->phone);
    }
}
