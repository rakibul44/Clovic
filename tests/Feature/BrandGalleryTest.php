<?php

namespace Tests\Feature;

use App\Models\BrandPhoto;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandGalleryTest extends TestCase
{
    use DatabaseMigrations;

    private function photo(array $extra = []): BrandPhoto
    {
        return BrandPhoto::create($extra + ['path' => 'brand-gallery/test.jpg', 'alt' => 'Verified brand archive photo', 'width' => 206, 'height' => 206, 'published' => true, 'position' => 0]);
    }

    private function staff(string $role = 'content_manager'): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    public function test_only_published_photos_appear_in_public_gallery_and_homepage(): void
    {
        $this->photo();
        $hidden = $this->photo(['path' => 'brand-gallery/hidden.jpg', 'alt' => 'Hidden brand photograph', 'published' => false]);
        $this->get('/gallery')->assertOk()->assertSee('Verified brand archive photo')->assertDontSee('Hidden brand photograph')->assertSee('older images may no longer apply');
        $this->get('/')->assertOk()->assertSee('From the Clovic journal.')->assertDontSee('Hidden brand photograph');
        $this->get('/admin/gallery')->assertRedirect('/login');
        $this->actingAs($this->staff('product_manager'))->get('/admin/gallery')->assertForbidden();
        $this->post('/admin/gallery/'.$hidden->id.'/delete')->assertForbidden();
    }

    public function test_content_staff_can_hide_replace_and_delete_local_photos_with_audit(): void
    {
        Storage::fake('public');
        $photo = $this->photo();
        Storage::disk('public')->put($photo->path, 'old photo');
        $this->actingAs($this->staff());
        $this->get('/admin/gallery')->assertOk()->assertSee('Delete photo');
        $data = ['alt' => 'Approved detail image', 'caption' => 'Owner caption', 'position' => 2, 'published' => 0, 'image' => UploadedFile::fake()->image('replacement.jpg', 800, 800)];
        $this->post('/admin/gallery/'.$photo->id, $data)->assertRedirect()->assertSessionHasNoErrors();
        $photo->refresh();
        $this->assertFalse($photo->published);
        $this->assertSame(800, $photo->width);
        Storage::disk('public')->assertMissing('brand-gallery/test.jpg');
        Storage::disk('public')->assertExists($photo->path);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'gallery.updated']);
        $this->post('/admin/gallery/'.$photo->id.'/delete')->assertRedirect();
        $this->assertDatabaseMissing('brand_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->path);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'gallery.deleted']);
    }

    public function test_contact_details_are_rendered_and_unconfigured_social_links_are_omitted(): void
    {
        $this->seed();
        foreach (['phone' => '01818-266583', 'email' => 'clovicbd@gmail.com', 'business_address' => 'Sector 10, House 104, Road 12', 'facebook' => 'https://www.facebook.com/clovicbd'] as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->get('/contact')->assertOk()->assertSee('mailto:clovicbd@gmail.com', false)->assertSee('01818-266583')->assertSee('Sector 10, House 104, Road 12')->assertDontSee('instagram.com/clovicbd');
        $p = $this->photo();
        $this->actingAs($this->staff())->post('/admin/gallery/'.$p->id, ['alt' => 'Valid', 'position' => 0, 'image' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('image');
    }

    public function test_bulk_publish_unpublish_and_delete_only_affect_selected_photos(): void
    {
        Storage::fake('public');
        $a = $this->photo(['path' => 'brand-gallery/a.jpg', 'published' => false]);
        $b = $this->photo(['path' => 'brand-gallery/b.jpg', 'published' => false]);
        foreach ([$a, $b] as $photo) {
            Storage::disk('public')->put($photo->path, 'test photo');
        }
        $this->actingAs($this->staff())->get('/admin/gallery')->assertOk()->assertSee('Select all on this page')->assertSee('Publish selected')->assertSee('form="gallery-bulk"', false);
        $this->post('/admin/gallery/bulk', ['action' => 'publish', 'ids' => [$a->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->published);
        $this->assertFalse($b->fresh()->published);
        $this->post('/admin/gallery/bulk', ['action' => 'unpublish', 'ids' => [$a->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($a->fresh()->published);
        $this->post('/admin/gallery/bulk', ['action' => 'delete', 'ids' => [$a->id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('brand_photos', ['id' => $a->id]);
        $this->assertDatabaseHas('brand_photos', ['id' => $b->id]);
        Storage::disk('public')->assertMissing($a->path);
        Storage::disk('public')->assertExists($b->path);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'gallery.bulk_delete']);
    }

    public function test_select_all_includes_photos_beyond_the_current_page(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->photo(['path' => 'brand-gallery/'.$i.'.jpg', 'published' => false, 'position' => $i]);
        }
        $this->actingAs($this->staff())->get('/admin/gallery')->assertOk()->assertSee('Select all 25 photos across all pages');
        $this->post('/admin/gallery/bulk', ['action' => 'publish', 'all' => 1])->assertRedirect()->assertSessionHas('success', '25 photos published.');
        $this->assertSame(25, BrandPhoto::where('published', true)->count());
        $this->post('/admin/gallery/bulk', ['action' => 'unpublish', 'all' => 1])->assertRedirect();
        $this->assertSame(0, BrandPhoto::where('published', true)->count());
    }

    public function test_bulk_actions_reject_unauthorized_invalid_or_empty_selections(): void
    {
        $photo = $this->photo();
        $this->post('/admin/gallery/bulk', ['action' => 'delete', 'all' => 1])->assertRedirect('/login');
        $this->actingAs($this->staff('product_manager'))->post('/admin/gallery/bulk', ['action' => 'delete', 'all' => 1])->assertForbidden();
        $this->actingAs($this->staff());
        $this->post('/admin/gallery/bulk', ['action' => 'delete'])->assertSessionHasErrors('ids');
        $this->post('/admin/gallery/bulk', ['action' => 'delete', 'ids' => [$photo->id, 999999]])->assertSessionHasErrors('ids.1');
        $this->post('/admin/gallery/bulk', ['action' => 'unsupported', 'ids' => [$photo->id]])->assertSessionHasErrors('action');
        $this->assertDatabaseHas('brand_photos', ['id' => $photo->id]);
    }
}
