<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Unit tests for the OrderItem model.
 *
 * @internal
 */
#[CoversClass(OrderItem::class)]
final class OrderItemTest extends TestCase
{
    /**
     * Test fillable attributes.
     */
    public function testFillableAttributes(): void
    {
        $fillable = [
            'order_id',
            'product_id',
            'quantity',
            'price',
            'total',
            'unit_price',
            'subtotal',
            'product_details',
        ];

        self::assertSame($fillable, (new OrderItem())->getFillable());
    }

    /**
     * Test casts.
     */
    public function testCasts(): void
    {
        $orderItem = new OrderItem();
        $actualCasts = $orderItem->getCasts();

        // Assert required casts exist
        // Note: getCasts() may include 'id' => 'int' automatically in some Laravel versions
        // We only check for the explicitly defined casts and don't assert the entire array
        self::assertArrayHasKey('product_details', $actualCasts);
        self::assertSame('array', $actualCasts['product_details']);
        
        self::assertArrayHasKey('price', $actualCasts);
        self::assertSame('decimal:2', $actualCasts['price']);
        
        self::assertArrayHasKey('total', $actualCasts);
        self::assertSame('decimal:2', $actualCasts['total']);
        
        // Verify that id is not explicitly in $casts property (it may be added by Laravel automatically)
        $reflection = new \ReflectionClass($orderItem);
        $castsProperty = $reflection->getProperty('casts');
        $castsProperty->setAccessible(true);
        $definedCasts = $castsProperty->getValue($orderItem);
        self::assertArrayNotHasKey('id', $definedCasts, 'id should not be explicitly cast in $casts property');
    }

    /**
     * Test order relation is a BelongsTo instance.
     */
    public function testOrderRelation(): void
    {
        $orderItem = new OrderItem();

        $relation = $orderItem->order();

        self::assertInstanceOf(BelongsTo::class, $relation);
        self::assertSame(Order::class, $relation->getRelated()::class);
    }

    /**
     * Test product relation is a BelongsTo instance.
     */
    public function testProductRelation(): void
    {
        $orderItem = new OrderItem();

        $relation = $orderItem->product();

        self::assertInstanceOf(BelongsTo::class, $relation);
        self::assertSame(Product::class, $relation->getRelated()::class);
    }
}
