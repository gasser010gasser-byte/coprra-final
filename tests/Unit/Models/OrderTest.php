<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Unit tests for the Order model.
 *
 * @internal
 */
#[CoversClass(Order::class)]
final class OrderTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that user relation is a BelongsTo instance.
     */
    public function testUserRelation(): void
    {
        $order = new Order();

        $relation = $order->user();

        self::assertInstanceOf(BelongsTo::class, $relation);
        self::assertSame(User::class, $relation->getRelated()::class);
    }

    /**
     * Test that items relation is a HasMany instance.
     */
    public function testItemsRelation(): void
    {
        $order = new Order();

        $relation = $order->items();

        self::assertInstanceOf(HasMany::class, $relation);
        self::assertSame(OrderItem::class, $relation->getRelated()::class);
    }

    /**
     * Test scopeByStatus adds where clause for status.
     */
    public function testScopeByStatus(): void
    {
        $status = 'pending';
        $query = \Mockery::mock(Builder::class);
        $query->shouldReceive('where')->once()->with('status', $status)->andReturnSelf();

        $order = new Order();
        $result = $order->scopeByStatus($query, $status);

        self::assertInstanceOf(Builder::class, $result);
    }

    /**
     * Test scopeForUser adds where clause for user_id.
     */
    public function testScopeForUser(): void
    {
        $userId = 1;
        $query = \Mockery::mock(Builder::class);
        $query->shouldReceive('where')->once()->with('user_id', $userId)->andReturnSelf();

        $order = new Order();
        $result = $order->scopeForUser($query, $userId);

        self::assertInstanceOf(Builder::class, $result);
    }

    public function testOrderHasRequiredAttributes()
    {
        $order = Order::factory()->create();

        self::assertNotNull($order->user_id);
        self::assertIsString($order->status);
        self::assertSame('pending', (string) $order->status); // Cast enum to string
        self::assertGreaterThan(0, $order->total_amount);
        self::assertNotNull($order->created_at);
    }
}
