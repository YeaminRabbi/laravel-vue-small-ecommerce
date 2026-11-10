<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopProductFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'product_id' => Product::factory(), 'name' => fake()->words(3, true), 'description' => fake()->sentence(), 'price' => 100, 'is_active' => true];
    }
}
