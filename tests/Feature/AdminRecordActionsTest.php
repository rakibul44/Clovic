<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Checkout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRecordActionsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'owner'): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    public function test_content_entries_have_explicit_edit_delete_and_deletion_is_authorized_and_audited(): void
    {
        $page = Page::create(['title' => 'Test page', 'slug' => 'test-page', 'content' => 'Owner content', 'published' => true]);
        $this->post('/admin/content/pages/'.$page->id.'/delete')->assertRedirect('/login');
        $this->actingAs($this->staff('product_manager'))->post('/admin/content/pages/'.$page->id.'/delete')->assertForbidden();
        $this->actingAs($this->staff('content_manager'))->get('/admin/content/pages')->assertOk()->assertSee('>Edit</a>', false)->assertSee('>Delete</button>', false);
        $this->post('/admin/content/pages/'.$page->id.'/delete')->assertRedirect('/admin/content/pages');
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'content.deleted']);
        $post = BlogPost::create(['title' => 'Test post', 'slug' => 'test-post', 'author' => 'Clovic', 'content' => 'Owner text', 'published_at' => now()]);
        $this->post('/admin/content/blog/'.$post->id.'/delete')->assertRedirect();
        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_unused_coupon_can_be_deleted_but_redemption_history_is_protected(): void
    {
        $unused = Coupon::create(['code' => 'UNUSED', 'percent' => 10, 'minimum' => 0, 'usage_limit' => 10, 'used' => 0, 'active' => true]);
        $used = Coupon::create(['code' => 'USED', 'percent' => 10, 'minimum' => 0, 'usage_limit' => 10, 'used' => 1, 'active' => false]);
        $this->actingAs($this->staff('store_manager'))->post('/admin/content/coupons/'.$unused->id.'/delete')->assertRedirect();
        $this->assertDatabaseMissing('coupons', ['id' => $unused->id]);
        $this->post('/admin/content/coupons/'.$used->id.'/delete')->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('coupons', ['id' => $used->id, 'used' => 1]);
    }

    public function test_account_actions_preserve_users_and_only_owner_can_disable_staff(): void
    {
        $customer = User::factory()->create();
        $manager = $this->staff('store_manager');
        $owner = $this->staff();
        $support = $this->staff('support');
        $this->actingAs($manager)->post('/admin/customers/'.$customer->id.'/status', ['active' => 0])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'active' => false]);
        $this->post('/admin/customers/'.$owner->id.'/status', ['active' => 0])->assertNotFound();
        $this->post('/admin/staff/'.$support->id.'/status', ['active' => 0])->assertForbidden();
        $this->actingAs($owner)->post('/admin/staff/'.$support->id.'/status', ['active' => 0])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $support->id, 'active' => false]);
        $this->post('/admin/staff/'.$owner->id.'/status', ['active' => 0])->assertForbidden();
        $this->assertTrue($owner->fresh()->active);
    }

    public function test_homepage_remove_is_reversible_and_review_delete_preserves_purchase(): void
    {
        $owner = $this->staff();
        $section = DB::table('homepage_sections')->where('kind', 'hero')->first();
        $this->actingAs($owner)->post('/admin/homepage/'.$section->id.'/remove')->assertRedirect();
        $this->assertDatabaseHas('homepage_sections', ['id' => $section->id, 'published' => false]);
        $v = ProductVariant::factory()->create();
        $v->product->update(['demo' => false]);
        $buyer = User::factory()->create();
        $this->actingAs($buyer);
        $order = app(Checkout::class)->place(['name' => 'Test buyer', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => 'Test road', 'idempotency_key' => (string) Str::uuid()], [$v->id => 1], 'test-owner');
        $id = DB::table('reviews')->insertGetId(['user_id' => $buyer->id, 'order_item_id' => $order->items->first()->id, 'rating' => 4, 'body' => 'Test review', 'approved' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->get('/admin/reviews')->assertOk()->assertSee('Edit moderation')->assertSee('Delete review');
        $this->post('/admin/reviews/'.$id.'/delete')->assertRedirect();
        $this->assertDatabaseMissing('reviews', ['id' => $id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['id' => $order->items->first()->id]);
    }
}
