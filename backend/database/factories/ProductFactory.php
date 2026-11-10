<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['created_by' => User::factory(), 'name' => fake()->words(3, true), 'sku' => fake()->unique()->bothify('SKU-####??'), 'description' => fake()->sentence(), 'price' => fake()->randomFloat(2, 1, 2000)];
    }
}
