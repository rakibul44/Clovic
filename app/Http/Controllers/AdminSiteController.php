<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminSiteController extends Controller
{
    private function audit(string $action, string $subject, array $changes = []): void
    {
        AdminAuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'changes' => $changes]);
    }

    public function homepage()
    {
        return view('admin.homepage', ['sections' => DB::table('homepage_sections')->orderBy('position')->get()]);
    }

    public function section(Request $request, int $id)
    {
        $section = DB::table('homepage_sections')->where('id', $id)->first();
        abort_unless($section, 404);
        $data = $request->validate(['title' => 'nullable|string|max:180', 'content' => 'nullable|string|max:4000', 'link_label' => 'nullable|string|max:60', 'link_path' => ['nullable', 'string', 'max:200', 'regex:#^/(?!/)[a-zA-Z0-9/?&=_%.-]*$#'], 'image_alt' => 'nullable|string|max:200', 'position' => 'required|integer|min:0|max:1000', 'published' => 'nullable|boolean', 'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000', 'mobile_upload' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000', 'remove_image' => 'nullable|boolean', 'remove_mobile' => 'nullable|boolean']);
        $obsolete = [];
        $uploaded = [];
        unset($data['image_upload'],$data['mobile_upload'],$data['remove_image'],$data['remove_mobile']);
        $data['published'] = $request->boolean('published');
        foreach (['image' => ['image_upload', 'remove_image'], 'mobile_image' => ['mobile_upload', 'remove_mobile']] as $key => [$file,$remove]) {
            if ($request->boolean($remove)) {
                $data[$key] = null;
            }if ($request->hasFile($file)) {
                $data[$key] = $request->file($file)->store('homepage', 'public');
                $uploaded[] = $data[$key];
            }if (array_key_exists($key, $data) && $section->$key && $data[$key] !== $section->$key) {
                $obsolete[] = $section->$key;
            }
        }
        try {
            DB::transaction(function () use ($id, $data, $obsolete, $section) {
                DB::table('homepage_sections')->where('id', $id)->update($data + ['updated_at' => now()]);
                if (in_array($section->kind, ['new_arrivals', 'best_sellers', 'featured', 'offers'])) {
                    StoreSetting::updateOrCreate(['key' => 'show_'.$section->kind], ['value' => $data['published'] ? '1' : '0']);
                }
                foreach ($obsolete as $path) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($path));
                }$this->audit('homepage.section', (string) $id, collect($data)->except('content')->all());
            });
        } catch (\Throwable $e) {
            foreach ($uploaded as $path) {
                Storage::disk('public')->delete($path);
            }throw $e;
        }

        return back()->with('success', 'Homepage section saved. The storefront uses the new content and order immediately.');
    }

    public function seo()
    {
        return view('admin.seo', ['products' => Product::withCount('images')->latest()->paginate(20), 'pages' => Page::all(), 'categories' => Category::orderBy('position')->get(), 'redirects' => DB::table('slug_redirects')->latest()->limit(100)->get()]);
    }

    public function seoSettings(Request $request)
    {
        $data = $request->validate(['seo_title' => 'required|string|max:160', 'meta_description' => 'required|string|max:320', 'indexing' => 'required|in:normal,discourage', 'og_title' => 'nullable|string|max:160', 'og_description' => 'nullable|string|max:320', 'og_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096']);
        if ($request->hasFile('og_image')) {
            $data['og_image'] = $request->file('og_image')->store('brand', 'public');
        } else {
            unset($data['og_image']);
        }foreach ($data as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }$this->audit('seo.settings', 'store', array_keys($data));

        return back()->with('success', 'SEO defaults saved.');
    }

    public function seoContent(Request $request, string $type, int $id)
    {
        $classes = ['product' => Product::class, 'category' => Category::class, 'page' => Page::class];
        abort_unless(isset($classes[$type]), 404);
        $record = $classes[$type]::findOrFail($id);
        $data = $request->validate(['seo_title' => 'nullable|string|max:160', 'meta_description' => 'nullable|string|max:320', 'canonical_url' => 'nullable|url:https|max:250']);
        if (! empty($data['canonical_url'])) {
            abort_unless(parse_url($data['canonical_url'], PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST), 422, 'Canonical URL must use the configured store domain.');
        }$record->fill($data)->save();
        $this->audit('seo.content', $type.':'.$id);

        return back()->with('success', 'Page metadata saved.');
    }

    public function redirect(Request $request)
    {
        $pattern = 'regex:#^(?:product|category|blog)/[a-zA-Z0-9_-]+$#';
        $data = $request->validate(['old_path' => ['required', 'string', 'max:200', $pattern], 'new_path' => ['required', 'string', 'max:200', $pattern, 'different:old_path']]);
        $path = $data['new_path'];
        $seen = [$data['old_path'] => true];
        for ($i = 0; $i < 10; $i++) {
            abort_if(isset($seen[$path]), 422, 'Redirect would form a loop.');
            $seen[$path] = true;
            $next = DB::table('slug_redirects')->where('old_path', $path)->value('new_path');
            if (! $next) {
                break;
            }$path = $next;
        }DB::table('slug_redirects')->updateOrInsert(['old_path' => $data['old_path']], ['new_path' => $data['new_path'], 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('redirect.saved', $data['old_path'], ['destination' => $data['new_path']]);

        return back()->with('success', 'Permanent redirect saved. Only published destinations will be served.');
    }

    public function deleteRedirect(int $id)
    {
        $redirect = DB::table('slug_redirects')->where('id', $id)->first();
        abort_unless($redirect, 404);
        DB::table('slug_redirects')->where('id', $id)->delete();
        $this->audit('redirect.deleted', $redirect->old_path);

        return back()->with('success', 'Redirect removed.');
    }

    public function advancedSettings(Request $request)
    {
        $data = $request->validate(['business_address' => 'nullable|string|max:1000', 'brand_description' => 'nullable|string|max:1000', 'country' => 'required|in:BD', 'currency' => 'required|in:BDT', 'timezone' => ['required', Rule::in(timezone_identifiers_list())], 'maintenance_mode' => 'required|in:0,1', 'favicon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024|dimensions:min_width=32,min_height=32,max_width=1024,max_height=1024', 'remove_favicon' => 'nullable|boolean']);
        abort_if($data['maintenance_mode'] === '1' && $request->user()->role !== 'owner', 403);
        unset($data['remove_favicon']);
        $old = StoreSetting::where('key', 'favicon')->value('value');
        unset($data['favicon']);
        if ($request->boolean('remove_favicon')) {
            $data['favicon'] = null;
        }if ($request->hasFile('favicon')) {
            $data['favicon'] = $request->file('favicon')->store('brand', 'public');
        }DB::transaction(function () use ($data, $old) {
            foreach ($data as $key => $value) {
                StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }if (array_key_exists('favicon', $data) && $old && $old !== $data['favicon']) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($old));
            }$this->audit('settings.advanced', 'store', array_keys($data));
        });

        return back()->with('success', 'General settings saved. Staff login and admin access remain available in maintenance mode.');
    }
}
