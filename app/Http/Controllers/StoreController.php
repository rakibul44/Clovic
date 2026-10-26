<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function home()
    {
        $bestIds = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')->where('orders.status', 'delivered')->where('orders.payment_status', 'paid')->select('product_variants.product_id', DB::raw('SUM(quantity) as sold'))->groupBy('product_variants.product_id')->orderByDesc('sold')->limit(4)->pluck('product_id');

        return view('home', ['products' => Product::published()->with('images', 'variants', 'category')->latest()->limit(8)->get(), 'featured' => Product::published()->with('images', 'variants', 'category')->where('featured', true)->limit(4)->get(), 'offers' => Product::published()->with('images', 'variants', 'category')->whereNotNull('sale_price')->limit(4)->get(), 'categories' => Category::where('published', true)->get(), 'best' => Product::published()->with('images', 'variants', 'category')->whereIn('id', $bestIds)->get()]);
    }

    public function shop(Request $request, ?string $slug = null)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|integer|exists:categories,id', 'min' => 'nullable|integer|min:0|max:1000000', 'max' => 'nullable|integer|min:0|max:1000000', 'size' => 'nullable|string|max:30', 'color' => 'nullable|string|max:50', 'available' => 'nullable|in:1', 'sort' => 'nullable|in:newest,price_asc,price_desc,popularity', 'page' => 'nullable|integer|min:1|max:10000']);
        $collection = in_array($slug, ['new-arrivals', 'sale'], true) ? $slug : null;
        $category = $slug && ! $collection ? Category::where('slug', $slug)->where('published', true)->first() : null;
        if ($slug && ! $category && ! $collection) {
            return $this->oldSlug('category/'.$slug);
        }
        $q = Product::published()->with('images', 'variants', 'category');
        if ($collection === 'new-arrivals') {
            $q->where('new_arrival', true);
        }
        if ($collection === 'sale') {
            $q->whereNotNull('sale_price');
        }
        if ($category) {
            $q->where('category_id', $category->id);
        }
        if ($collection) {
            $category = new Category(['name' => $collection === 'sale' ? 'Current offers' : 'New arrivals', 'slug' => $collection, 'description' => $collection === 'sale' ? 'Explore currently configured offers.' : 'Meet your new everyday essentials.']);
        }
        if (! empty($data['category'])) {
            $q->where('category_id', $data['category']);
        }
        if (! empty($data['q'])) {
            $q->where(fn ($b) => $b->where('name', 'like', '%'.$data['q'].'%')->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', '%'.$data['q'].'%')));
        }
        if (isset($data['min'])) {
            $q->whereRaw('COALESCE(sale_price,price) >= ?', [$data['min'] * 100]);
        }
        if (isset($data['max'])) {
            $q->whereRaw('COALESCE(sale_price,price) <= ?', [$data['max'] * 100]);
        }
        if (! empty($data['size']) || ! empty($data['color']) || ! empty($data['available'])) {
            $q->whereHas('variants', function ($v) use ($data) {
                if (! empty($data['size'])) {
                    $v->where('size', $data['size']);
                }
                if (! empty($data['color'])) {
                    $v->where('color', $data['color']);
                }
                if (! empty($data['available'])) {
                    $v->where('stock', '>', 0);
                }
            });
        }
        match ($data['sort'] ?? 'newest') {
            'price_asc' => $q->orderByRaw('COALESCE(sale_price,price) ASC'),'price_desc' => $q->orderByRaw('COALESCE(sale_price,price) DESC'),'popularity' => $q->orderByRaw("(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN product_variants pv ON pv.id=oi.product_variant_id WHERE pv.product_id=products.id AND o.status='delivered' AND o.payment_status='paid') DESC"),default => $q->latest()
        };

        return view('shop', ['products' => $q->paginate(12)->withQueryString(), 'category' => $category, 'categories' => Category::where('published', true)->get(), 'sizes' => DB::table('product_variants')->distinct()->orderBy('size')->pluck('size'), 'colors' => DB::table('product_variants')->distinct()->orderBy('color')->pluck('color')]);
    }

    public function product(string $slug)
    {
        $product = Product::published()->with('images', 'variants', 'category')->where('slug', $slug)->first();
        if (! $product) {
            return $this->oldSlug('product/'.$slug);
        }
        $recent = session('recent', []);
        session(['recent' => array_slice(array_unique([$product->id, ...$recent]), 0, 8)]);

        return view('product', ['product' => $product, 'related' => Product::published()->with('images', 'variants', 'category')->where('category_id', $product->category_id)->whereKeyNot($product->id)->limit(4)->get(), 'recent' => Product::published()->with('images', 'variants', 'category')->whereIn('id', $recent)->whereKeyNot($product->id)->limit(4)->get()]);
    }

    public function page(string $slug)
    {
        return view('page', ['page' => Page::where('slug', $slug)->where('published', true)->firstOrFail()]);
    }

    public function blog(?string $slug = null)
    {
        if ($slug) {
            $post = BlogPost::where('slug', $slug)->where('published_at', '<=', now())->first();
            if (! $post) {
                return $this->oldSlug('blog/'.$slug);
            }

            return view('article', compact('post'));
        }

        return view('blog', ['posts' => BlogPost::where('published_at', '<=', now())->latest('published_at')->paginate(10)]);
    }

    public function suggestions(Request $r)
    {
        $r->validate(['q' => 'required|string|min:2|max:100']);

        return Product::published()->where('name', 'like', '%'.$r->q.'%')->limit(6)->get(['name', 'slug']);
    }

    private function oldSlug(string $path)
    {
        $seen = [];
        for ($i = 0; $i < 10; $i++) {
            if (isset($seen[$path])) {
                abort(404);
            }
            $seen[$path] = true;
            $next = DB::table('slug_redirects')->where('old_path', $path)->value('new_path');
            if (! $next) {
                break;
            }
            $path = $next;
            $slug = basename($path);
            $valid = str_starts_with($path, 'product/') ? Product::published()->where('slug', $slug)->exists() : (str_starts_with($path, 'category/') ? Category::where('slug', $slug)->where('published', true)->exists() : BlogPost::where('slug', $slug)->where('published_at', '<=', now())->exists());
            if ($valid) {
                return redirect('/'.$path, 301);
            }
        }
        abort(404);
    }

    public function newsletter(Request $r)
    {
        $v = $r->validate(['email' => 'required|email|max:150', 'consent' => 'accepted']);
        DB::table('newsletter_subscribers')->insertOrIgnore(['email' => $v['email'], 'consented_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Your newsletter preference has been saved.');
    }
}
