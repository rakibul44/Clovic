<?php

namespace App\Models;

use App\Services\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['category_id', 'name', 'slug', 'description', 'fabric', 'fit', 'care', 'price', 'sale_price', 'status', 'featured', 'new_arrival', 'demo', 'seo_title', 'meta_description', 'og_image', 'noindex', 'short_description', 'brand', 'bestseller', 'canonical_url', 'cost_price'];

    protected $hidden = ['cost_price'];

    protected $casts = ['price' => 'integer', 'sale_price' => 'integer', 'featured' => 'boolean', 'new_arrival' => 'boolean', 'demo' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published')->whereHas('category', fn ($c) => $c->where('published', true));
    }

    public function getCurrentPriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }

    public function getDisplayImageAttribute(): string
    {
        $image = $this->images->first();
        if ($image) {
            return Storage::url($image->path);
        }
        $path = 'assets/'.$this->category->slug.'.svg';

        return '/'.(is_file(public_path($path)) ? $path : 'assets/garment.svg');
    }

    public function getDisplayPriceAttribute(): int
    {
        return $this->variants->min(fn ($variant) => $variant->price_override ?? $this->current_price) ?? $this->current_price;
    }

    public function getSelectionOptionsAttribute(): array
    {
        return $this->variants->map(function ($variant) {
            $price = $variant->price_override ?? $this->current_price;
            $image = $variant->image_id ? $this->images->firstWhere('id', $variant->image_id) : null;

            return $variant->only(['id', 'size', 'color', 'stock', 'sku', 'color_hex']) + ['product_id' => $this->id, 'price' => $price, 'price_label' => Settings::money($price), 'image' => $image ? Storage::url($image->path) : $this->display_image];
        })->values()->all();
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
