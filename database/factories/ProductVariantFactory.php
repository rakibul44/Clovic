<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'sku' => fake()->unique()->bothify('TEST-####-??'), 'size' => 'M', 'color' => 'Navy', 'stock' => 5, 'low_stock_threshold' => 2];
    }
}
