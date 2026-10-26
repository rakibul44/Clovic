<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['The everyday polo', 'polo-shirts', 'Sage', 129000], ['The essential tee', 't-shirts', 'Sand', 79000], ['The relaxed shirt', 'casual-shirts', 'Stone', 159000], ['The daily trouser', 'trousers', 'Olive', 189000], ['The classic polo', 'polo-shirts', 'Sage', 139000], ['The weekend tee', 't-shirts', 'Sand', 89000], ['The studio shirt', 'casual-shirts', 'Stone', 169000], ['The easy trouser', 'trousers', 'Olive', 179000]] as $i => [$name,$category,$color,$price]) {
            $p = Product::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'category_id' => Category::where('slug', $category)->value('id'), 'description' => 'Demonstration catalog item. This name, price and garment illustration are sample content for testing the store. Actual fabric, fit, care instructions, size measurements and authentic product images must be supplied by the administrator.', 'price' => $price, 'status' => 'published', 'demo' => true, 'noindex' => true, 'new_arrival' => true, 'featured' => $i < 4]);
            foreach (['S', 'M', 'L', 'XL'] as $size) {
                $p->variants()->firstOrCreate(['sku' => 'DEMO-'.($i + 1).'-'.$size], ['size' => $size, 'color' => $color, 'stock' => 10, 'low_stock_threshold' => 5]);
            }
        }
    }
}
