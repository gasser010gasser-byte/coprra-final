<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    protected $model = PriceHistory::class;

    /**
     * @return (\DateTime|float|ProductFactory)[]
     *
     * @psalm-return array{product_id: ProductFactory, price: float, recorded_at: \DateTime}
     */
    #[\Override]
    public function definition()
    {
        return [
            'product_id' => Product::factory(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'old_price' => null,
            'currency' => 'USD',
            'recorded_at' => $this->faker->dateTimeThisYear(),
        ];
    }
}
