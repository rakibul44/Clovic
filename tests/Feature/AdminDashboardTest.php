<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AdminSignals;
use App\Services\Checkout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'owner'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function variant(): ProductVariant
    {
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $variant->product->update(['demo' => false, 'cost_price' => 60000]);

        return $variant;
    }

    private function order(ProductVariant $variant): Order
    {
        return app(Checkout::class)->place(['name' => 'Private buyer', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => '123 Private Test Road', 'idempotency_key' => (string) Str::uuid()], [$variant->id => 1], 'test-owner');
    }

    public function test_roles_enforce_backend_permissions_and_hide_restricted_links(): void
    {
        $variant = $this->variant();
        $productManager = $this->staff('product_manager');
        $this->actingAs($productManager)->get('/admin/products')->assertOk()->assertDontSee('href="/admin/staff"', false)->assertDontSee('href="/admin/payments"', false);
        $this->get('/admin/products/'.$variant->product_id.'/edit')->assertOk()->assertDontSee('Unit cost price');
        foreach (['/admin/orders', '/admin/reports', '/admin/staff', '/admin/settings', '/admin/customers/export'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/admin/products/'.$variant->product_id, ['cost_price' => 1])->assertRedirect();
        $this->actingAs($this->staff('content_manager'))->get('/admin/homepage')->assertOk();
        $this->post('/admin/inventory', [])->assertForbidden();
        $this->actingAs($this->staff('support'))->get('/admin/orders')->assertOk();
        $this->post('/admin/orders/1', [])->assertForbidden();
        $this->get('/admin/orders/export')->assertForbidden();
    }

    public function test_new_modules_render_with_real_empty_states_and_exports(): void
    {
        $this->seed();
        $variant = $this->variant();
        $order = $this->order($variant);
        $this->actingAs($this->staff());
        foreach (['/admin', '/admin/reports', '/admin/staff', '/admin/homepage', '/admin/seo', '/admin/notifications', '/admin/payments', '/admin/shipping', '/admin/inventory', '/admin/products/'.$variant->product_id.'/preview', '/admin/orders/'.$order->id.'/document/invoice', '/admin/orders/'.$order->id.'/document/packing-slip'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['sales', 'products', 'categories', 'inventory', 'coupons'] as $kind) {
            $this->get('/admin/reports/export?kind='.$kind)->assertOk();
        }
        $this->get('/admin/orders/export')->assertDownload('clovic-orders.csv');
        $this->get('/admin/customers/export')->assertDownload('clovic-customers.csv');
    }

    public function test_soft_deletion_preserves_snapshots_and_restore_is_a_draft(): void
    {
        $variant = $this->variant();
        $order = $this->order($variant);
        $slug = $variant->product->slug;
        $id = $variant->product_id;
        $this->actingAs($this->staff())->post('/admin/products/'.$id.'/delete')->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->get('/product/'.$slug)->assertNotFound();
        $this->get('/admin/orders/'.$order->id.'/document/invoice')->assertOk()->assertSee($order->reference);
        $this->assertDatabaseCount('order_items', 1);
        $this->post('/admin/products/'.$id.'/restore')->assertRedirect();
        $this->assertEquals('draft', Product::findOrFail($id)->status);
        $this->get('/product/'.$slug)->assertNotFound();
    }

    public function test_owner_provisions_staff_and_managers_cannot_escalate(): void
    {
        $owner = $this->staff();
        $this->actingAs($owner)->post('/admin/staff', ['name' => 'Product colleague', 'email' => 'colleague@example.test', 'role' => 'product_manager', 'active' => 1, 'password' => 'StrongUniquePassword123', 'password_confirmation' => 'StrongUniquePassword123'])->assertRedirect()->assertSessionHasNoErrors();
        $member = User::where('email', 'colleague@example.test')->firstOrFail();
        $this->assertEquals('product_manager', $member->role);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'staff.saved', 'subject' => (string) $member->id]);
        $this->actingAs($member)->post('/admin/staff/'.$owner->id, ['role' => 'owner'])->assertForbidden();
        $this->actingAs($owner)->post('/admin/staff/'.$owner->id, [])->assertForbidden();
        $this->assertDatabaseMissing('admin_audit_logs', ['changes' => 'StrongUniquePassword123']);
    }

    public function test_category_hierarchy_cannot_cycle_or_delete_a_nonempty_category(): void
    {
        $this->actingAs($this->staff());
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->post('/admin/content/categories/'.$parent->id, ['name' => $parent->name, 'slug' => $parent->slug, 'parent_id' => $child->id, 'published' => 1])->assertSessionHasErrors('parent_id');
        $this->post('/admin/content/categories/'.$parent->id.'/delete')->assertUnprocessable();
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
        $this->post('/admin/content/categories/'.$child->id.'/delete')->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $child->id]);
    }

    public function test_bulk_stock_is_atomic_and_no_adjustment_can_make_stock_negative(): void
    {
        $one = $this->variant();
        $two = $this->variant();
        $this->actingAs($this->staff('product_manager'));
        $this->post('/admin/inventory/bulk', ['adjustments' => [['id' => $one->id, 'quantity' => 3], ['id' => $two->id, 'quantity' => -99]], 'reason' => 'Correction'])->assertUnprocessable();
        $this->assertEquals(10, $one->fresh()->stock);
        $this->assertEquals(10, $two->fresh()->stock);
        $this->post('/admin/inventory/bulk', ['adjustments' => [['id' => $one->id, 'quantity' => 2], ['id' => $two->id, 'quantity' => -1]], 'reason' => 'Stock receipt'])->assertRedirect();
        $this->assertEquals(12, $one->fresh()->stock);
        $this->assertEquals(9, $two->fresh()->stock);
    }

    public function test_homepage_builder_edits_reorders_and_hides_sections_without_deployment(): void
    {
        $this->seed();
        $this->actingAs($this->staff('content_manager'));
        $hero = DB::table('homepage_sections')->where('kind', 'hero')->first();
        $this->post('/admin/homepage/'.$hero->id, ['title' => 'A real edited headline', 'content' => 'Approved collection introduction', 'link_label' => 'Browse our edit', 'link_path' => '/shop', 'position' => 0, 'published' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('A real edited headline')->assertSee('Browse our edit');
        $this->post('/admin/homepage/'.$hero->id, ['title' => 'A real edited headline', 'position' => 0, 'published' => 0])->assertRedirect();
        $this->get('/')->assertDontSee('A real edited headline');
    }

    public function test_payment_recording_is_separate_from_delivery_and_requires_permission(): void
    {
        $variant = $this->variant();
        $order = $this->order($variant);
        $this->actingAs($this->staff('order_manager'))->post('/admin/payments/'.$order->id, ['status' => 'paid', 'reference' => 'COD-RECEIPT-TEST'])->assertRedirect();
        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->post('/admin/payments/'.$order->id, ['status' => 'refunded', 'reference' => 'COD-REFUND-TEST'])->assertRedirect();
        $this->assertEquals('refunded', $order->fresh()->payment_status);
        $this->actingAs($this->staff('support'))->post('/admin/payments/'.$order->id, ['status' => 'paid', 'reference' => 'INVALID'])->assertForbidden();
    }

    public function test_manual_shipping_updates_do_not_change_payment_or_order_state(): void
    {
        $order = $this->order($this->variant());
        $this->actingAs($this->staff('order_manager'))->post('/admin/shipping/'.$order->id, ['courier' => 'Manual courier', 'tracking_number' => 'TRACK-001', 'status' => 'dispatched', 'dispatched_at' => today()->toDateString(), 'estimated_at' => today()->addDays(3)->toDateString()])->assertRedirect();
        $this->assertEquals('dispatched', $order->fresh()->shipping->status);
        $this->assertEquals('pending', $order->fresh()->status);
        $this->assertEquals('unpaid', $order->fresh()->payment_status);
    }

    public function test_notifications_have_per_user_read_receipts(): void
    {
        $this->order($this->variant());
        $owner = $this->staff();
        $this->actingAs($owner);
        $signals = app(AdminSignals::class)->forUser($owner);
        $this->assertGreaterThan(0, $signals['unread']);
        $this->post('/admin/notifications/read', [])->assertRedirect();
        $this->assertEquals(0, app(AdminSignals::class)->forUser($owner)['unread']);
        $this->assertGreaterThan(0, app(AdminSignals::class)->forUser($this->staff())['unread']);
    }

    public function test_invoices_are_private_and_customer_export_is_audited(): void
    {
        $order = $this->order($this->variant());
        $this->get('/admin/orders/'.$order->id.'/document/invoice')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/orders/'.$order->id.'/document/invoice')->assertForbidden();
        $this->actingAs($this->staff())->get('/admin/customers/export')->assertOk();
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'customers.exported']);
    }

    public function test_safe_seo_controls_change_rendered_metadata_and_reject_redirect_loops(): void
    {
        $this->seed();
        $this->actingAs($this->staff('content_manager'))->post('/admin/seo', ['seo_title' => 'Approved Clovic title', 'meta_description' => 'A real store description.', 'indexing' => 'discourage'])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Approved Clovic title')->assertSee('noindex,follow');
        $this->get('/robots.txt')->assertSee('Disallow: /');
        $this->post('/admin/seo/redirects', ['old_path' => 'product/one', 'new_path' => 'product/two'])->assertRedirect();
        $this->post('/admin/seo/redirects', ['old_path' => 'product/two', 'new_path' => 'product/one'])->assertUnprocessable();
    }
}
