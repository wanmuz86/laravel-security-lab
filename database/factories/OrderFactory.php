<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference' => 'ORD-'.fake()->unique()->numerify('######'),
            'description' => fake()->sentence(4),
            'total_cents' => fake()->numberBetween(1000, 50000),
            'status' => 'draft',
        ];
    }
}
