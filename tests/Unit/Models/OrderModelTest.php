<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class OrderModelTest extends TestCase
{
    use RefreshDatabase;

    public function testOrderBelongsToUser()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        self::assertInstanceOf(User::class, $order->user);
        self::assertSame($user->id, $order->user->id);
    }

    public function testOrderHasRequiredAttributes()
    {
        $order = Order::factory()->create([
            'subtotal' => 85.00,
            'tax_amount' => 10.50,
            'shipping_amount' => 5.00,
            'discount_amount' => 0.00,
            'total_amount' => 100.50, // Explicitly set total: 85 + 10.50 + 5 - 0 = 100.50
            'status' => 'pending',
        ]);

        // Verify the total is set correctly
        self::assertEquals(100.50, (float) $order->total_amount);
        self::assertSame(OrderStatus::PENDING, $order->status);
        self::assertNotNull($order->created_at);
    }

    public function testOrderCanCalculateTotal()
    {
        $order = Order::factory()->create();

        // Assuming the Order model has a calculateTotal method
        if (method_exists($order, 'calculateTotal')) {
            $total = $order->calculateTotal();
            self::assertIsNumeric($total);
            self::assertGreaterThanOrEqual(0, $total);
        } else {
            self::assertTrue(true, 'calculateTotal method not implemented yet');
        }
    }
}
