<?php

declare(strict_types=1);

namespace Tests\Unit\DataAccuracy;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PriceAccuracyTest extends TestCase
{
    use RefreshDatabase;

    // \PHPUnit\Framework\Attributes\Test
    public function testBasePriceCalculation(): void
    {
        $product = Product::factory()->create(['price' => 100.00]);
        self::assertEquals(100.00, (float) $product->price);
    }

    // \PHPUnit\Framework\Attributes\Test
    public function testTaxInclusivePricing(): void
    {
        $product = Product::factory()->create([
            'price' => 100.00,
        ]);
        $taxRate = 0.15;

        self::assertSame(115.00, round($product->price * (1 + $taxRate), 2));
    }

    // \PHPUnit\Framework\Attributes\Test
    public function testDiscountApplicationAccuracy(): void
    {
        $product = Product::factory()->create([
            'price' => 200.00,
        ]);
        $discount = 0.25;

        self::assertSame(150.00, $product->price * (1 - $discount));
    }
}
