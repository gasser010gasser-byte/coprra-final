<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Address::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'street' => $this->faker->streetAddress,
            'line1' => $this->faker->streetAddress,
            'city' => $this->faker->city() ?: 'New York',
            'state' => $this->faker->state,
            'zip_code' => $this->faker->postcode,
            'country' => $this->faker->countryCode ?: 'US',
            'is_default' => false,
        ];
    }
}
