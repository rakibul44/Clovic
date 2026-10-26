<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'slug', 'description', 'fabric', 'fit', 'care', 'price', 'sale_price', 'status', 'featured', 'new_arrival', 'demo', 'seo_title', 'meta_description', 'og_image', 'noindex'];

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

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
