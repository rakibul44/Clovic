<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Cart
{
    public function quantities(): array
    {
        if (auth()->check()) {
            $id = DB::table('carts')->where('user_id', auth()->id())->value('id');

            return $id ? DB::table('cart_items')->where('cart_id', $id)->pluck('quantity', 'product_variant_id')->all() : [];
        }

        return session('cart', []);
    }

    public function set(int $variantId, int $quantity): void
    {
        if ($quantity < 0 || $quantity > 99) {
            throw ValidationException::withMessages(['cart' => 'Choose a quantity between 0 and 99.']);
        }
        $variant = ProductVariant::with('product.category')->findOrFail($variantId);
        if ($quantity > 0 && ($variant->product->status !== 'published' || ! $variant->product->category->published || $quantity > $variant->stock)) {
            throw ValidationException::withMessages(['cart' => 'This variant is unavailable in the requested quantity.']);
        }
        if (auth()->check()) {
            DB::table('carts')->insertOrIgnore(['user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('carts')->where('user_id', auth()->id())->value('id');
            if (! $quantity) {
                DB::table('cart_items')->where('cart_id', $id)->where('product_variant_id', $variantId)->delete();
            } else {
                DB::table('cart_items')->updateOrInsert(['cart_id' => $id, 'product_variant_id' => $variantId], ['quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()]);
            }
        } else {
            $cart = $this->quantities();
            if (! $quantity) {
                unset($cart[$variantId]);
            } else {
                $cart[$variantId] = $quantity;
            } session(['cart' => $cart]);
        }
    }

    public function lines()
    {
        $quantities = $this->quantities();

        return ProductVariant::with('product.images', 'product.category')->whereIn('id', array_keys($quantities))->get()->map(fn ($variant) => ['variant' => $variant, 'quantity' => (int) $quantities[$variant->id], 'total' => $variant->current_price * (int) $quantities[$variant->id]]);
    }

    public function snapshot(): array
    {
        $lines = $this->lines();

        return ['count' => $lines->sum('quantity'), 'subtotal' => $lines->sum('total'), 'subtotal_label' => Settings::money($lines->sum('total')), 'items' => $lines->map(function ($line) {
            $variant = $line['variant'];
            $product = $variant->product;

            return ['id' => $variant->id, 'name' => $product->name, 'url' => '/product/'.$product->slug, 'image' => $variant->display_image, 'size' => $variant->size, 'color' => $variant->color, 'sku' => $variant->sku, 'quantity' => $line['quantity'], 'max' => min(99, $variant->stock), 'available' => $product->status === 'published' && $product->category->published && $variant->stock > 0, 'unit_price_label' => Settings::money($variant->current_price), 'total_label' => Settings::money($line['total'])];
        })->values()->all()];
    }

    public function subtotal(): int
    {
        return $this->lines()->sum('total');
    }

    public function clear(): void
    {
        if (auth()->check()) {
            $id = DB::table('carts')->where('user_id', auth()->id())->value('id');
            DB::table('cart_items')->where('cart_id', $id)->delete();
        } session()->forget('cart');
    }

    public function mergeGuest(): void
    {
        $guest = session('cart', []);
        foreach ($guest as $id => $qty) {
            $v = ProductVariant::find($id);
            if ($v && $v->stock > 0 && $v->product->status === 'published' && $v->product->category->published) {
                $this->set($id, min(99, $v->stock, ($this->quantities()[$id] ?? 0) + $qty));
            }
        } session()->forget('cart');
    }
}
