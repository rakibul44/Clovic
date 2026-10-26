<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaManagementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_admin_can_remove_homepage_and_logo_files(): void
    {
        Storage::fake('public');
        $this->seed();
        $u = User::factory()->create();
        $u->forceFill(['role' => 'owner'])->save();
        $this->actingAs($u);
        foreach (['logo' => 'brand/logo.png', 'hero_image' => 'brand/hero.jpg'] as $key => $path) {
            Storage::disk('public')->put($path, 'test media');
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $path]);
        }
        $data = StoreSetting::pluck('value', 'key')->all() + ['remove_logo' => 1, 'remove_hero_image' => 1];
        unset($data['logo'], $data['hero_image']);
        $this->get('/admin/settings')->assertOk()->assertSee('Remove store logo')->assertSee('Remove homepage image');
        $this->post('/admin/settings', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(StoreSetting::where('key', 'logo')->value('value'));
        $this->assertNull(StoreSetting::where('key', 'hero_image')->value('value'));
        Storage::disk('public')->assertMissing('brand/logo.png');
        Storage::disk('public')->assertMissing('brand/hero.jpg');
    }

    public function test_admin_can_remove_category_image_without_deleting_category(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();
        $u->forceFill(['role' => 'owner'])->save();
        $this->actingAs($u);
        Storage::disk('public')->put('content/category.jpg', 'test media');
        $c = Category::factory()->create(['image' => 'content/category.jpg']);
        $this->post('/admin/content/categories/'.$c->id, ['name' => $c->name, 'slug' => $c->slug, 'published' => 1, 'remove_image' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($c->fresh()->image);
        Storage::disk('public')->assertMissing('content/category.jpg');
    }

    public function test_logo_uses_local_storage_in_header_and_footer(): void
    {
        $this->seed();
        StoreSetting::updateOrCreate(['key' => 'logo'], ['value' => 'brand/clovic-logo-original.png']);
        $html = $this->get('http://127.0.0.1:8000/')->assertOk()->getContent();
        $this->assertEquals(2, substr_count($html, 'src="/storage/brand/clovic-logo-original.png"'));
        $this->assertEquals('/storage/products/example.jpg', Storage::disk('public')->url('products/example.jpg'));
    }
}
