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
final class PriceHistoryAccuracyTest extends TestCase
{
    use RefreshDatabase;

    // \[\PHPUnit\Framework\Attributes\Test]
    public function testPriceHistoryRecordsChanges(): void
    {
        $product = Product::factory()->create(['price' => 100.00]);

        $product->update(['price' => 120.00]);
        $product->update(['price' => 110.00]);

        // Refresh to get latest price history and reload relationship
        $product->refresh();
        $product->load('priceHistory');
        
        // Use the query builder to ensure we get all records
        $priceHistory = $product->priceHistory()->get();
        self::assertCount(3, $priceHistory);
        // Oldest record should be the initial price (100.00)
        $oldest = $product->priceHistory()->orderBy('recorded_at', 'asc')->first();
        self::assertNotNull($oldest);
        self::assertEquals(100.00, (float) $oldest->price);
        // Latest record should be the current price (110.00)
        $latest = $product->priceHistory()->orderBy('recorded_at', 'desc')->first();
        self::assertNotNull($latest);
        self::assertEquals(110.00, (float) $latest->price);
    }

    // \[\PHPUnit\Framework\Attributes\Test]
    public function testHistoricalPricesAccuracy(): void
    {
        $product = Product::factory()->create(['price' => 200.00]);

        $historicalPrices = [
            ['price' => 180.00, 'recorded_at' => now()->subDays(3), 'currency' => 'USD'],
            ['price' => 190.00, 'recorded_at' => now()->subDays(1), 'currency' => 'USD'],
        ];

        $product->priceHistory()->createMany($historicalPrices);

        $oldest = $product->priceHistory()->orderBy('recorded_at', 'asc')->first();
        self::assertEquals(180.00, (float) $oldest->price);
        self::assertTrue($product->priceHistory()->where('price', 190.00)->exists());
    }

    // \[\PHPUnit\Framework\Attributes\Test]
    public function testPriceFluctuationDetection(): void
    {
        $product = Product::factory()->create(['price' => 150.00]);

        $product->update(['price' => 135.00]); // -10%
        
        // Refresh and check that the significant change (150 -> 135 = 10% drop) is detected
        $product->refresh();
        self::assertTrue($product->hasSignificantPriceChange(10)); // 10% threshold should detect the change
        
        // Now update again to 148.50, which brings it closer to original
        $product->update(['price' => 148.50]); // +10% from 135
        $product->refresh();
        
        // Current (148.50) vs oldest (150) = ~1% change, so 15% threshold should be false
        self::assertFalse($product->hasSignificantPriceChange(15));
    }
}
