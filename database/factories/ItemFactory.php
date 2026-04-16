<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        static $skuCounter = 0;
        $skuCounter++;

        $minThreshold = fake()->numberBetween(5, 20);
        $quantity = fake()->numberBetween(0, 100);

        return [
            'name' => fake()->words(3, true),
            'sku' => 'SKU-' . str_pad($skuCounter, 5, '0', STR_PAD_LEFT),
            'quantity' => $quantity,
            'price' => fake()->randomFloat(2, 5, 500),
            'min_stock_threshold' => $minThreshold,
            'category_id' => Category::inRandomOrder()->first()?->id ?? Category::factory(),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'quantity' => fake()->numberBetween(0, 5),
            ];
        });
    }

    public function highStock(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'quantity' => fake()->numberBetween(50, 200),
            ];
        });
    }
}
