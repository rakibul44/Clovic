<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\OrderNotifier;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function dashboard(Request $r)
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $q = Order::query()->when($r->from, fn ($q) => $q->whereDate('created_at', '>=', $r->from))->when($r->to, fn ($q) => $q->whereDate('created_at', '<=', $r->to));

        return view('admin.dashboard', ['best' => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')->join('products', 'products.id', '=', 'product_variants.product_id')->where('orders.status', 'delivered')->where('orders.payment_status', 'paid')->when($r->from, fn ($q) => $q->whereDate('orders.created_at', '>=', $r->from))->when($r->to, fn ($q) => $q->whereDate('orders.created_at', '<=', $r->to))->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as sold'))->groupBy('products.id', 'products.name')->orderByDesc('sold')->limit(10)->get(), 'counts' => (clone $q)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status'), 'revenue' => (clone $q)->where('status', 'delivered')->where('payment_status', 'paid')->sum('total'), 'orders' => (clone $q)->latest()->limit(10)->get(), 'low' => ProductVariant::with('product')->whereColumn('stock', '<=', 'low_stock_threshold')->limit(20)->get()]);
    }

    public function products(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100']);

        return view('admin.products', ['products' => Product::with('category', 'variants')->when($r->q, fn ($q) => $q->where('name', 'like', '%'.$r->q.'%'))->latest()->paginate(20)]);
    }

    public function productForm(?int $id = null)
    {
        return view('admin.product', ['product' => $id ? Product::with('variants', 'images')->findOrFail($id) : new Product, 'categories' => Category::all()]);
    }

    public function saveProduct(Request $r, ?int $id = null)
    {
        $product = $id ? Product::findOrFail($id) : new Product;
        $d = $r->validate(['name' => 'required|string|max:150', 'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('products')->ignore($id)], 'category_id' => 'required|exists:categories,id', 'description' => 'required|string|max:10000', 'fabric' => 'nullable|string|max:2000', 'fit' => 'nullable|string|max:2000', 'care' => 'nullable|string|max:2000', 'price' => 'required|integer|min:1|max:100000000', 'sale_price' => 'nullable|integer|min:1|lte:price', 'status' => 'required|in:draft,published,archived', 'featured' => 'nullable|boolean', 'new_arrival' => 'nullable|boolean', 'demo' => 'nullable|boolean', 'noindex' => 'nullable|boolean', 'seo_title' => 'nullable|string|max:160', 'meta_description' => 'nullable|string|max:320', 'variants' => 'required|array|min:1|max:100', 'variants.*.sku' => 'required|string|max:100|distinct', 'variants.*.size' => 'required|string|max:30', 'variants.*.color' => 'required|string|max:50', 'variants.*.stock' => 'required|integer|min:0|max:1000000', 'variants.*.low_stock_threshold' => 'required|integer|min:0|max:10000', 'images' => 'nullable|array|max:8', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=8000,max_height=8000', 'og_image_id' => 'nullable|integer', 'image_alt' => 'nullable|string|max:200', 'image_alts' => 'nullable|array', 'image_alts.*' => 'nullable|string|max:200', 'delete_images' => 'nullable|array', 'delete_images.*' => 'integer']);
        $combinations = [];
        foreach ($d['variants'] as $v) {
            $key = strtolower($v['size'].'|'.$v['color']);
            if (isset($combinations[$key])) {
                throw ValidationException::withMessages(['variants' => 'Size/color combinations must be unique.']);
            }
            $combinations[$key] = true;
            $conflict = ProductVariant::where('sku', $v['sku'])->when($product->exists, fn ($q) => $q->where('product_id', '!=', $product->id))->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['variants' => 'A SKU already belongs to another product.']);
            }
        }
        $uploaded = [];
        try {
            DB::transaction(function () use ($r, $d, $product, &$uploaded) {
                $old = $product->slug;
                $product->fill(collect($d)->except(['variants', 'images', 'og_image_id', 'image_alt', 'image_alts', 'delete_images'])->all());
                foreach (['featured', 'new_arrival', 'demo', 'noindex'] as $flag) {
                    $product->$flag = $r->boolean($flag);
                }
                $product->save();
                if ($old && $old !== $product->slug) {
                    DB::table('slug_redirects')->updateOrInsert(['old_path' => 'product/'.$old], ['new_path' => 'product/'.$product->slug, 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($d['variants'] as $v) {
                    $variant = $product->variants()->where('sku', $v['sku'])->lockForUpdate()->first();
                    $previous = $variant?->stock ?? 0;
                    if ($variant) {
                        $variant->fill($v);
                        $variant->save();
                    } else {
                        $variant = $product->variants()->create($v);
                    }
                    if ($v['stock'] != $previous) {
                        InventoryMovement::create(['product_variant_id' => $variant->id, 'user_id' => auth()->id(), 'quantity' => $v['stock'] - $previous, 'reason' => 'admin product stock adjustment']);
                    }
                }
                foreach ($r->file('images', []) as $index => $file) {
                    $path = $file->store('products', 'public');
                    $uploaded[] = $path;
                    $product->images()->create(['path' => $path, 'alt' => $d['image_alt'] ?? $product->name, 'position' => $product->images()->count() + $index]);
                }
                foreach ($d['image_alts'] ?? [] as $imageId => $alt) {
                    $product->images()->whereKey($imageId)->update(['alt' => $alt ?: $product->name]);
                }
                foreach ($d['delete_images'] ?? [] as $imageId) {
                    $image = $product->images()->find($imageId);
                    if ($image) {
                        $path = $image->path;
                        $image->delete();
                        DB::afterCommit(fn () => Storage::disk('public')->delete($path));
                    }
                }
                $og = $r->input('og_image_id');
                if ($og) {
                    $image = $product->images()->findOrFail($og);
                    $product->update(['og_image' => $image->path]);
                } elseif ($product->og_image && ! $product->images()->where('path', $product->og_image)->exists()) {
                    $product->update(['og_image' => null]);
                }
                $this->logAudit('product.saved', (string) $product->id, ['status' => $product->status]);
            }, 5);
        } catch (\Throwable $e) {
            foreach ($uploaded as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return redirect('/admin/products/'.$product->id.'/edit')->with('success', 'Product saved. Existing SKUs remain attached to historical orders.');
    }

    public function bulk(Request $r)
    {
        $d = $r->validate(['ids' => 'required|array|max:100', 'ids.*' => 'integer|exists:products,id', 'status' => 'required|in:draft,published,archived']);
        DB::transaction(function () use ($d) {
            Product::whereIn('id', $d['ids'])->update(['status' => $d['status']]);
            $this->logAudit('products.bulk', 'products', $d);
        });

        return back()->with('success', 'Products updated.');
    }

    public function export()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'slug', 'category_id', 'price', 'sale_price', 'status', 'description', 'sku', 'size', 'color', 'stock']);
            ProductVariant::with('product')->chunkById(200, function ($variants) use ($out) {
                foreach ($variants as $v) {
                    $p = $v->product;
                    fputcsv($out, array_map(fn ($s) => is_string($s) && preg_match('/^[=+@\-]/', $s) ? "'".$s : $s, [$p->name, $p->slug, $p->category_id, $p->price, $p->sale_price, $p->status, $p->description, $v->sku, $v->size, $v->color, $v->stock]));
                }
            });
            fclose($out);
        }, 'clovic-products.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $r)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);
        $file = fopen($r->file('file')->path(), 'r');
        $header = fgetcsv($file);
        $expected = ['name', 'slug', 'category_id', 'price', 'sale_price', 'status', 'description', 'sku', 'size', 'color', 'stock'];
        abort_unless($header === $expected, 422, 'Use the exported CSV column layout.');
        $rows = [];
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) !== count($header)) {
                abort(422, 'Malformed CSV row.');
            }
            $rows[] = array_combine($header, $row);
            if (count($rows) > 1000) {
                abort(422, 'Limit imports to 1,000 variants.');
            }
        }
        fclose($file);
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $data = validator($row, ['name' => 'required|string|max:150', 'slug' => 'required|alpha_dash|max:150', 'category_id' => 'required|exists:categories,id', 'price' => 'required|integer|min:1|max:100000000', 'sale_price' => 'nullable|integer|min:1|lte:price', 'status' => 'required|in:draft,published,archived', 'description' => 'required|string|max:10000', 'sku' => 'required|string|max:100', 'size' => 'required|string|max:30', 'color' => 'required|string|max:50', 'stock' => 'required|integer|min:0|max:1000000'])->validate();
                $data['sale_price'] = $data['sale_price'] === '' ? null : $data['sale_price'];
                $p = Product::updateOrCreate(['slug' => $data['slug']], collect($data)->except(['sku', 'size', 'color', 'stock'])->all());
                $v = ProductVariant::where('sku', $data['sku'])->lockForUpdate()->first();
                if ($v && $v->product_id !== $p->id) {
                    abort(422, 'SKU belongs to a different product.');
                }
                $old = $v?->stock ?? 0;
                $v = ProductVariant::updateOrCreate(['sku' => $data['sku']], collect($data)->only(['size', 'color', 'stock'])->all() + ['product_id' => $p->id]);
                if ($old != $v->stock) {
                    InventoryMovement::create(['product_variant_id' => $v->id, 'user_id' => auth()->id(), 'quantity' => $v->stock - $old, 'reason' => 'CSV import']);
                }
            }
            $this->logAudit('products.import', 'products', ['rows' => count($rows)]);
        });

        return back()->with('success', 'Import completed.');
    }

    public function inventory()
    {
        return view('admin.inventory', ['variants' => ProductVariant::with('product')->orderBy('stock')->paginate(30), 'movements' => InventoryMovement::with('variant')->latest()->limit(50)->get()]);
    }

    public function stock(Request $r)
    {
        $d = $r->validate(['variant_id' => 'required|exists:product_variants,id', 'quantity' => 'required|integer|between:-100000,100000', 'reason' => 'required|string|max:200']);
        DB::transaction(function () use ($d) {
            $v = ProductVariant::lockForUpdate()->findOrFail($d['variant_id']);
            abort_if($v->stock + $d['quantity'] < 0, 422, 'Stock cannot be negative.');
            $v->increment('stock', $d['quantity']);
            InventoryMovement::create(['product_variant_id' => $v->id, 'quantity' => $d['quantity'], 'reason' => $d['reason'], 'user_id' => auth()->id()]);
            $this->logAudit('inventory.adjusted', (string) $v->id, $d);
        }, 5);

        return back()->with('success', 'Stock adjustment recorded.');
    }

    public function orders(Request $r)
    {
        $r->validate(['customer' => 'nullable|integer|exists:users,id', 'q' => 'nullable|string|max:100', 'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled,return_requested,returned,refunded', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);

        return view('admin.orders', ['orders' => Order::when($r->customer, fn ($q) => $q->where('user_id', $r->customer))->when($r->q, fn ($q) => $q->where(fn ($b) => $b->where('reference', 'like', '%'.$r->q.'%')->orWhere('name', 'like', '%'.$r->q.'%')->orWhere('phone', 'like', '%'.$r->q.'%')))->when($r->status, fn ($q) => $q->where('status', $r->status))->when($r->from, fn ($q) => $q->whereDate('created_at', '>=', $r->from))->when($r->to, fn ($q) => $q->whereDate('created_at', '<=', $r->to))->latest()->paginate(25)->withQueryString()]);
    }

    public function order(int $id)
    {
        return view('admin.order', ['order' => Order::with('items', 'history', 'payments', 'shipping')->findOrFail($id), 'transitions' => OrderWorkflow::TRANSITIONS]);
    }

    public function updateOrder(Request $r, int $id, OrderWorkflow $workflow)
    {
        $d = $r->validate(['status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,return_requested,returned,refunded', 'payment_status' => 'required|in:unpaid,paid,refunded', 'internal_notes' => 'nullable|string|max:5000', 'courier' => 'nullable|string|max:100|required_with:tracking_number', 'tracking_number' => 'nullable|string|max:100|required_with:courier']);
        $order = DB::transaction(function () use ($id, $d, $workflow) {
            $order = Order::lockForUpdate()->findOrFail($id);
            if ($d['payment_status'] === 'refunded' && ! in_array($order->payment_status, ['paid', 'refunded'])) {
                abort(422, 'An unpaid order cannot be refunded.');
            }
            if ($d['payment_status'] === 'unpaid' && $order->payment_status !== 'unpaid') {
                abort(422, 'Collected payments cannot be reset to unpaid.');
            }
            if ($d['payment_status'] === 'paid' && $order->payment_status === 'refunded') {
                abort(422, 'A refunded payment cannot be collected again.');
            }
            $order->update(['payment_status' => $d['payment_status'], 'internal_notes' => $d['internal_notes'] ?? null]);
            $order->payments()->update(['status' => $d['payment_status']]);
            if (! empty($d['courier'])) {
                $order->shipping()->updateOrCreate(['order_id' => $order->id], ['courier' => $d['courier'], 'tracking_number' => $d['tracking_number']]);
            }
            $order = $workflow->transition($order, $d['status'], auth()->id());
            $this->logAudit('order.updated', $order->reference, collect($d)->except('internal_notes')->all());

            return $order;
        }, 5);
        app(OrderNotifier::class)->send($order, $d['status']);

        return back()->with('success', 'Order updated. Payment status reflects the collection/refund you recorded.');
    }

    public const RESOURCES = ['categories' => Category::class, 'pages' => Page::class, 'blog' => BlogPost::class, 'coupons' => Coupon::class];

    public function content(string $resource, ?int $id = null)
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        $class = self::RESOURCES[$resource];

        return view('admin.content', ['resource' => $resource, 'records' => $class::latest()->paginate(30), 'record' => $id ? $class::findOrFail($id) : new $class]);
    }

    public function saveContent(Request $r, string $resource, ?int $id = null)
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        $class = self::RESOURCES[$resource];
        $record = $id ? $class::findOrFail($id) : new $class;
        $table = $record->getTable();
        $rules = match ($resource) {
            'categories' => ['name' => 'required|string|max:150', 'description' => 'nullable|string|max:10000', 'published' => 'nullable|boolean'],'pages' => ['title' => 'required|string|max:150', 'content' => 'required|string|max:100000', 'published' => 'nullable|boolean'],'blog' => ['title' => 'required|string|max:150', 'author' => 'required|string|max:100', 'excerpt' => 'nullable|string|max:1000', 'content' => 'required|string|max:100000', 'published_at' => 'nullable|date'],'coupons' => ['code' => ['required', 'alpha_dash', 'max:40', Rule::unique('coupons')->ignore($id)], 'percent' => 'required|integer|between:1,100', 'minimum' => 'required|integer|min:0|max:100000000', 'usage_limit' => 'required|integer|min:1|max:1000000', 'expires_at' => 'nullable|date', 'active' => 'nullable|boolean']
        };
        if ($resource !== 'coupons') {
            $rules += ['slug' => ['required', 'alpha_dash', 'max:150', Rule::unique($table)->ignore($id)], 'seo_title' => 'nullable|string|max:160', 'meta_description' => 'nullable|string|max:320'];
        }
        if (in_array($resource, ['categories', 'blog'])) {
            $rules['remove_image'] = 'nullable|boolean';
            $rules['image_upload'] = 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096';
        }
        $d = $r->validate($rules);
        unset($d['image_upload'],$d['remove_image']);
        foreach (['published', 'active'] as $flag) {
            if (array_key_exists($flag, $rules)) {
                $d[$flag] = $r->boolean($flag);
            }
        }
        if ($resource === 'coupons') {
            $d['code'] = strtoupper($d['code']);
        }
        DB::transaction(function () use ($record, $d, $resource, $r) {
            $old = $record->slug;
            $oldImage = $record->image;
            $record->fill($d);
            if (in_array($resource, ['categories', 'blog']) && $r->boolean('remove_image')) {
                $record->image = null;
            }
            if ($r->hasFile('image_upload')) {
                $record->image = $r->file('image_upload')->store('content', 'public');
            }
            $record->save();
            if ($oldImage && $oldImage !== $record->image) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($oldImage));
            }
            if ($old && $old !== $record->slug && in_array($resource, ['categories', 'blog'])) {
                $prefix = $resource === 'categories' ? 'category/' : 'blog/';
                DB::table('slug_redirects')->updateOrInsert(['old_path' => $prefix.$old], ['new_path' => $prefix.$record->slug, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->logAudit($resource.'.saved', (string) $record->id, []);
        });

        return redirect('/admin/content/'.$resource.'/'.$record->id)->with('success', 'Content saved.');
    }

    public function settings()
    {
        return view('admin.settings', ['settings' => StoreSetting::pluck('value', 'key')]);
    }

    public function saveSettings(Request $r)
    {
        $r->validate(['navigation' => 'nullable|json|max:5000']);
        $navigation = json_decode($r->input('navigation', '[]'), true);
        validator(['links' => $navigation], ['links' => 'array|max:8', 'links.*.label' => 'required|string|max:30', 'links.*.path' => ['required', 'string', 'max:200', 'regex:#^/(?!/)[a-zA-Z0-9/?&=_%.-]*$#']])->validate();
        $d = $r->validate(['remove_hero_image' => 'nullable|boolean', 'remove_logo' => 'nullable|boolean', 'navigation' => 'nullable|json|max:5000', 'brand_intro' => 'nullable|string|max:2000', 'hero_second_line' => 'required|string|max:100', 'show_featured' => 'required|in:0,1', 'show_offers' => 'required|in:0,1', 'brand' => 'required|string|max:100', 'announcement' => 'nullable|string|max:250', 'delivery_inside' => 'required|integer|min:0|max:1000000', 'delivery_outside' => 'required|integer|min:0|max:1000000', 'email' => 'nullable|email|max:150', 'phone' => 'nullable|string|max:30', 'facebook' => 'nullable|url:https|max:250', 'instagram' => 'nullable|url:https|max:250', 'whatsapp' => 'nullable|url:https|max:250', 'hero_title' => 'required|string|max:150', 'hero_text' => 'required|string|max:1000', 'hero_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', 'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', 'restore_stock' => 'required|in:0,1', 'seo_title' => 'nullable|string|max:160', 'meta_description' => 'nullable|string|max:320', 'low_stock_threshold' => 'required|integer|min:0|max:10000']);
        $obsolete = [];
        unset($d['remove_hero_image'],$d['remove_logo']);
        foreach (['hero_image', 'logo'] as $key) {
            $previous = StoreSetting::where('key', $key)->value('value');
            unset($d[$key]);
            if ($r->boolean('remove_'.$key)) {
                $d[$key] = null;
            }
            if ($r->hasFile($key)) {
                $d[$key] = $r->file($key)->store('brand', 'public');
            }
            if (array_key_exists($key, $d) && $previous && $previous !== $d[$key]) {
                $obsolete[] = $previous;
            }
        }
        DB::transaction(function () use ($d, $obsolete) {
            foreach ($obsolete as $path) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));
            }
            foreach ($d as $key => $value) {
                StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
            $this->logAudit('settings.saved', 'store', array_keys($d));
        });

        return back()->with('success', 'Settings saved. Domain, currency and provider secrets are managed in the deployment environment.');
    }

    public function customers(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:100']);

        return view('admin.customers', ['customers' => User::withCount('orders')->when($r->q, fn ($q) => $q->where(fn ($b) => $b->where('name', 'like', '%'.$r->q.'%')->orWhere('email', 'like', '%'.$r->q.'%')))->latest()->paginate(25)]);
    }

    public function customer(Request $r, int $id)
    {
        abort_unless(auth()->user()->role === 'owner', 403);
        $d = $r->validate(['role' => 'required|in:customer,admin', 'active' => 'required|boolean']);
        $u = User::findOrFail($id);
        abort_if($u->role === 'owner' || $u->id === auth()->id(), 403);
        DB::transaction(function () use ($u, $d) {
            $u->forceFill($d)->save();
            $this->logAudit('customer.access', (string) $u->id, $d);
        });

        return back()->with('success', 'Customer access updated.');
    }

    public function audit()
    {
        return view('admin.audit', ['logs' => AdminAuditLog::latest()->paginate(50)]);
    }

    private function logAudit(string $action, string $subject, array $changes): void
    {
        AdminAuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'changes' => $changes]);
    }
}
