<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'order_number' => $this->faker->unique()->regexify('[A-Z0-9]{10}'),
            'user_id' => User::factory(),
            'status' => 'pending',
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            'subtotal' => $this->faker->randomFloat(2, 10, 1000),
            'tax_amount' => $this->faker->randomFloat(2, 0, 100),
            'shipping_amount' => $this->faker->randomFloat(2, 0, 50),
            'discount_amount' => $this->faker->randomFloat(2, 0, 100),
            'currency' => 'USD',
            'shipping_address' => [
                'street' => $this->faker->streetAddress,
                'city' => $this->faker->city,
                'state' => $this->faker->state,
                'zip' => $this->faker->postcode,
                'country' => $this->faker->country,
            ],
            'billing_address' => [
                'street' => $this->faker->streetAddress,
                'city' => $this->faker->city,
                'state' => $this->faker->state,
                'zip' => $this->faker->postcode,
                'country' => $this->faker->country,
            ],
            'notes' => $this->faker->sentence,
            'order_date' => $this->faker->dateTimeThisYear(),
            'shipped_at' => $this->faker->dateTimeThisYear(),
            'delivered_at' => $this->faker->dateTimeThisYear(),
        ];
    }
}
