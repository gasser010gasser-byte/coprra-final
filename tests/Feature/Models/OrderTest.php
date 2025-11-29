<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @internal
 *
 * @coversNothing
 */
final class OrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function testItCanCreateAnOrder(): void
    {
        // Arrange
        $user = User::factory()->create();
        $attributes = [
            'order_number' => 'ORD-001',
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 100.00,
            'subtotal' => 90.00,
            'tax_amount' => 5.00,
            'shipping_amount' => 5.00,
            'discount_amount' => 0.00,
            'currency' => 'USD',
            'shipping_address' => ['street' => '123 Main St'],
            'billing_address' => ['street' => '123 Main St'],
            'notes' => 'Test order',
        ];

        // Act
        $order = Order::create($attributes);

        // Assert
        self::assertInstanceOf(Order::class, $order);
        self::assertSame('ORD-001', $order->order_number);
        self::assertSame('pending', $order->status);
        self::assertSame(100.00, $order->total_amount);
        self::assertSame('USD', $order->currency);
        self::assertIsArray($order->shipping_address);
        self::assertIsArray($order->billing_address);
    }

    #[Test]
    public function testOrderRelationships(): void
    {
        // Arrange
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        OrderItem::factory()->create(['order_id' => $order->id]);
        Payment::factory()->create(['order_id' => $order->id]);

        // Act
        $order->refresh();

        // Assert
        self::assertInstanceOf(User::class, $order->user);
        self::assertSame($user->id, $order->user->id);
        self::assertCount(1, $order->items);
        self::assertCount(1, $order->payments);
    }

    #[Test]
    public function testOrderCastsAttributesCorrectly(): void
    {
        // Arrange
        $order = Order::factory()->create([
            'shipping_address' => ['street' => '123 Main St', 'city' => 'Test City'],
            'billing_address' => ['street' => '456 Billing St'],
            'shipped_at' => '2023-01-01 10:00:00',
            'delivered_at' => '2023-01-02 15:00:00',
        ]);

        // Act & Assert
        self::assertIsArray($order->shipping_address);
        self::assertSame('123 Main St', $order->shipping_address['street']);
        self::assertIsArray($order->billing_address);
        self::assertInstanceOf(Carbon::class, $order->shipped_at);
        self::assertInstanceOf(Carbon::class, $order->delivered_at);
    }

    #[Test]
    public function testScopeByStatus(): void
    {
        // Arrange
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'delivered']);
        Order::factory()->create(['status' => 'pending']);

        // Act
        $pendingOrders = Order::byStatus('pending')->get();

        // Assert
        self::assertCount(2, $pendingOrders);
        $pendingOrders->each(function ($order) {
            $this->assertSame('pending', $order->status->value);
        });
    }

    #[Test]
    public function testScopeForUser(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Order::factory()->create(['user_id' => $user1->id]);
        Order::factory()->create(['user_id' => $user2->id]);
        Order::factory()->create(['user_id' => $user1->id]);

        // Act
        $userOrders = Order::forUser($user1->id)->get();

        // Assert
        self::assertCount(2, $userOrders);
        $userOrders->each(function ($order) use ($user1) {
            $this->assertSame($user1->id, $order->user_id);
        });
    }

    #[Test]
    public function testOrderFillableAttributes(): void
    {
        // Arrange
        $fillable = [
            'order_number',
            'user_id',
            'status',
            'total_amount',
            'subtotal',
            'tax_amount',
            'shipping_amount',
            'discount_amount',
            'currency',
            'shipping_address',
            'billing_address',
            'notes',
            'order_date',
            'shipped_at',
            'delivered_at',
        ];

        // Act
        $order = new Order();

        // Assert
        self::assertSame($fillable, $order->getFillable());
    }

    #[Test]
    public function testOrderStatusTransitions(): void
    {
        // Arrange
        $order = Order::factory()->create(['status' => 'pending']);

        // Act
        $order->update(['status' => 'shipped', 'shipped_at' => now()]);
        $order->update(['status' => 'delivered', 'delivered_at' => now()]);

        // Assert
        self::assertSame('delivered', $order->status);
        self::assertNotNull($order->shipped_at);
        self::assertNotNull($order->delivered_at);
    }

    #[Test]
    public function testOrderTotalsCalculation(): void
    {
        // Arrange
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'tax_amount' => 10.00,
            'shipping_amount' => 5.00,
            'discount_amount' => 5.00,
        ]);

        // Act & Assert
        self::assertSame(100.00, $order->subtotal);
        self::assertSame(10.00, $order->tax_amount);
        self::assertSame(5.00, $order->shipping_amount);
        self::assertSame(5.00, $order->discount_amount);
        self::assertSame(110.00, $order->total_amount); // subtotal + tax + shipping - discount
    }
}
