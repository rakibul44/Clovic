<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;

class SeoController extends Controller
{
    public function sitemap()
    {
        $urls = [url('/'), url('/shop'), url('/blog'), url('/category/new-arrivals'), url('/category/sale')];
        foreach (Product::published()->where('noindex', false)->where('demo', false)->get() as $p) {
            $urls[] = url('/product/'.$p->slug);
        }
        foreach (Category::where('published', true)->get() as $c) {
            $urls[] = url('/category/'.$c->slug);
        }
        foreach (Page::where('published', true)->get() as $p) {
            $urls[] = url('/'.$p->slug);
        }
        foreach (BlogPost::where('published_at', '<=', now())->get() as $p) {
            $urls[] = url('/blog/'.$p->slug);
        }

        return response(view('sitemap', compact('urls')), 200, ['Content-Type' => 'application/xml']);
    }

    public function robots()
    {
        return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /account\nDisallow: /cart\nDisallow: /checkout\nDisallow: /order/\nDisallow: /track-order\nDisallow: /wishlist\nDisallow: /login\nDisallow: /register\nDisallow: /forgot-password\nDisallow: /reset-password\nSitemap: ".url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
