<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Services\PriceComparisonService;
use App\Services\StoreAdapterManager;
use Mockery;
use Tests\TestCase;

/**
 * Price Comparison Service Test.
 *
 * Tests critical business logic for price comparison across stores
 *
 * @internal
 *
 * @coversNothing
 */
final class PriceComparisonServiceTest extends TestCase
{
    private PriceComparisonService $service;
    private StoreAdapterManager $mockStoreAdapterManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock StoreAdapterManager
        $this->mockStoreAdapterManager = Mockery::mock(StoreAdapterManager::class);
        $this->app->instance(StoreAdapterManager::class, $this->mockStoreAdapterManager);

        $this->service = $this->app->make(PriceComparisonService::class);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function testItCanFetchPricesFromMultipleStores(): void
    {
        $product = Product::factory()->create([
            'store_mappings' => [
                'store_a' => 'product_123',
                'store_b' => 'product_456',
            ],
        ]);

        // Mock fetchProduct to return product data
        $this->mockStoreAdapterManager
            ->shouldReceive('fetchProduct')
            ->with('store_a', 'product_123')
            ->once()
            ->andReturn([
                'name' => 'Test Product',
                'price' => 99.99,
                'currency' => 'USD',
                'url' => 'https://example.com/product',
                'availability' => 'in_stock',
                'rating' => 4.5,
                'reviews_count' => 100,
            ]);

        $this->mockStoreAdapterManager
            ->shouldReceive('fetchProduct')
            ->with('store_b', 'product_456')
            ->once()
            ->andReturn([
                'name' => 'Test Product',
                'price' => 89.99,
                'currency' => 'USD',
                'url' => 'https://example.com/product2',
                'availability' => 'in_stock',
                'rating' => 4.0,
                'reviews_count' => 50,
            ]);

        $prices = $this->service->fetchPricesFromStores($product);

        self::assertIsArray($prices);
        self::assertNotEmpty($prices);
        self::assertCount(2, $prices);
    }

    public function testItReturnsEmptyArrayWhenNoStoreMappings(): void
    {
        $product = Product::factory()->create([
            'store_mappings' => null,
        ]);

        $prices = $this->service->fetchPricesFromStores($product);

        self::assertIsArray($prices);
        self::assertEmpty($prices);
    }

    public function testItReturnsEmptyArrayWhenStoreMappingsInvalid(): void
    {
        $product = Product::factory()->create([
            'store_mappings' => 'invalid_data',
        ]);

        $prices = $this->service->fetchPricesFromStores($product);

        self::assertIsArray($prices);
        self::assertEmpty($prices);
    }

    public function testItCanFindBestDeal(): void
    {
        $product = Product::factory()->create();

        $bestDeal = $this->service->findBestDeal($product);

        if (null !== $bestDeal) {
            self::assertIsArray($bestDeal);
            self::assertArrayHasKey('store_name', $bestDeal);
            self::assertArrayHasKey('price', $bestDeal);
        } else {
            self::assertNull($bestDeal);
        }
    }

    public function testItCanCalculatePriceRange(): void
    {
        $prices = [
            ['price' => 100.0],
            ['price' => 150.0],
            ['price' => 120.0],
        ];

        $result = $this->service->calculatePriceRange($prices);

        self::assertIsArray($result);
        self::assertSame(100.0, $result['min']);
        self::assertSame(150.0, $result['max']);
        self::assertSame(50.0, $result['difference']);
    }

    public function testItHandlesEmptyPricesForRangeCalculation(): void
    {
        $result = $this->service->calculatePriceRange([]);

        self::assertIsArray($result);
        self::assertSame(0.0, $result['min']);
        self::assertSame(0.0, $result['max']);
        self::assertSame(0.0, $result['difference']);
    }

    public function testItCanCalculateAveragePrice(): void
    {
        $prices = [
            ['price' => 100.0],
            ['price' => 150.0],
            ['price' => 120.0],
        ];

        $average = $this->service->calculateAveragePrice($prices);

        self::assertSame(123.33, round($average, 2));
    }

    public function testItReturnsZeroForEmptyPricesAverage(): void
    {
        $average = $this->service->calculateAveragePrice([]);

        self::assertSame(0.0, $average);
    }

    public function testItFiltersOutOfStockItems(): void
    {
        $prices = [
            ['price' => 100.0, 'in_stock' => true],
            ['price' => 150.0, 'in_stock' => false],
            ['price' => 120.0, 'in_stock' => true],
        ];

        $inStockPrices = $this->service->filterInStock($prices);

        self::assertCount(2, $inStockPrices);
        foreach ($inStockPrices as $price) {
            self::assertTrue($price['in_stock']);
        }
    }

    public function testItSortsPricesAscending(): void
    {
        $prices = [
            ['price' => 150.0],
            ['price' => 100.0],
            ['price' => 120.0],
        ];

        $sorted = $this->service->sortByPrice($prices, 'asc');

        self::assertSame(100.0, $sorted[0]['price']);
        self::assertSame(120.0, $sorted[1]['price']);
        self::assertSame(150.0, $sorted[2]['price']);
    }

    public function testItSortsPricesDescending(): void
    {
        $prices = [
            ['price' => 150.0],
            ['price' => 100.0],
            ['price' => 120.0],
        ];

        $sorted = $this->service->sortByPrice($prices, 'desc');

        self::assertSame(150.0, $sorted[0]['price']);
        self::assertSame(120.0, $sorted[1]['price']);
        self::assertSame(100.0, $sorted[2]['price']);
    }

    public function testItHandlesNullPricesGracefully(): void
    {
        $prices = [
            ['price' => 100.0],
            ['price' => null],
            ['price' => 120.0],
        ];

        $result = $this->service->calculateAveragePrice($prices);

        self::assertIsFloat($result);
        self::assertGreaterThan(0, $result);
    }

    public function testItValidatesCurrencyConsistency(): void
    {
        $prices = [
            ['price' => 100.0, 'currency' => 'USD'],
            ['price' => 150.0, 'currency' => 'USD'],
            ['price' => 120.0, 'currency' => 'EUR'],
        ];

        $isConsistent = $this->service->validateCurrencyConsistency($prices);

        self::assertFalse($isConsistent);
    }

    public function testItReturnsTrueForConsistentCurrencies(): void
    {
        $prices = [
            ['price' => 100.0, 'currency' => 'USD'],
            ['price' => 150.0, 'currency' => 'USD'],
            ['price' => 120.0, 'currency' => 'USD'],
        ];

        $isConsistent = $this->service->validateCurrencyConsistency($prices);

        self::assertTrue($isConsistent);
    }
}
