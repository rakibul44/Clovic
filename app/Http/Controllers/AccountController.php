<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index()
    {
        return view('account', ['orders' => auth()->user()->orders()->with('items')->latest()->paginate(10)]);
    }

    public function profile(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'phone' => ['nullable', 'regex:/^01[3-9][0-9]{8}$/']]);
        $r->user()->update($d);

        return back()->with('success', 'Profile saved.');
    }

    public function wishlist()
    {
        $id = DB::table('wishlists')->where('user_id', auth()->id())->value('id');
        $ids = DB::table('wishlist_items')->where('wishlist_id', $id)->pluck('product_id');

        return view('wishlist', ['products' => Product::published()->with('images', 'variants', 'category')->whereIn('id', $ids)->get()]);
    }

    public function toggle(Request $r)
    {
        $d = $r->validate(['product_id' => 'required|exists:products,id']);
        DB::table('wishlists')->insertOrIgnore(['user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('wishlists')->where('user_id', auth()->id())->value('id');
        $q = DB::table('wishlist_items')->where('wishlist_id', $id)->where('product_id', $d['product_id']);
        if ($q->exists()) {
            $q->delete();
        } else {
            DB::table('wishlist_items')->insert(['wishlist_id' => $id, 'product_id' => $d['product_id'], 'created_at' => now(), 'updated_at' => now()]);
        }

        return back()->with('success', 'Wishlist updated.');
    }
}
