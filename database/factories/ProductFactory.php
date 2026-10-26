<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return ['category_id' => Category::factory(), 'name' => $name, 'slug' => Str::slug($name), 'description' => 'Test catalog item.', 'price' => 120000, 'status' => 'published', 'demo' => true, 'noindex' => true];
    }
}
