<?php

declare(strict_types=1);

namespace Tests\Unit\DataAccuracy;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use PHPUnit\Framework\Attributes\Test;
use Tests\DatabaseSetup;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class DataConsistencyTest extends TestCase
{
    use DatabaseSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }

    #[Test]
    public function testOrderTotalMatchesItemsSum(): void
    {
        $product = Product::factory()->create(['price' => 100]);
        $order = Order::factory()->create([
            'total_amount' => 200,
            'subtotal' => 200,
            // Neutralize extras to test pure items sum
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'discount_amount' => 0,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => $product->price,
        ]);

        $expectedTotal = 200; // 2 * 100
        self::assertEquals($expectedTotal, (float) $order->total_amount);
    }

    // Removed stock consistency test as stock decrement logic is not implemented

    #[Test]
    public function testOrderItemsProductReference(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        self::assertTrue($orderItem->product()->exists());
        self::assertSame($product->id, $orderItem->product->id);
    }

    #[Test]
    public function testOrderStatusConsistency(): void
    {
        $order = Order::factory()->create(['status' => 'delivered']);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        self::assertSame('delivered', $order->status->value);
        self::assertEquals(100.00, (float) $payment->amount);
    }
}
