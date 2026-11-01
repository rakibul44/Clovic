<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'sku', 'size', 'color', 'stock', 'low_stock_threshold', 'price_override', 'image_id', 'color_hex'];

    public function getCurrentPriceAttribute(): int
    {
        return $this->price_override ?? $this->product->current_price;
    }

    public function getDisplayImageAttribute(): string
    {
        $image = $this->image_id ? $this->product->images->firstWhere('id', $this->image_id) : null;

        return $image ? Storage::url($image->path) : $this->product->display_image;
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
