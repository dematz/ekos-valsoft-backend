<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'action'     => fake()->randomElement(['create', 'update', 'delete']),
            'model_type' => fake()->randomElement(['Item', 'Category', 'User']),
            'model_id'   => fake()->numberBetween(1, 100),
            'changes'    => null,
        ];
    }

    public function create(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'create',
                'changes' => null,
            ];
        });
    }

    public function update(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'update',
                'changes' => [
                    'field_name' => [
                        'old' => 'old_value',
                        'new' => 'new_value',
                    ],
                ],
            ];
        });
    }

    public function delete(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'delete',
                'changes' => null,
            ];
        });
    }
}
