<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<OrderItem>
     */
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return (Factory|float|int|ProductFactory)[]
     *
     * @psalm-return array{order_id: Factory, product_id: ProductFactory, quantity: int, unit_price: float, total_price: float}
     */
    #[\Override]
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $quantity * $unitPrice,
            // For backward compatibility
            'price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
        ];
    }
}
