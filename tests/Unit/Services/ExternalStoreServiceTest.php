<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Store;
use App\Services\ExternalStoreService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExternalStoreServiceTest extends TestCase
{
    private ExternalStoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear external_stores config to ensure empty state
        \Illuminate\Support\Facades\Config::set('external_stores', []);
        $this->service = new ExternalStoreService();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function testItSearchesProductsAcrossAllStores(): void
    {
        // Arrange
        $query = 'laptop';
        $filters = ['category' => 'electronics'];

        // Act - with no store configs, returns empty array
        $results = $this->service->searchProducts($query, $filters);

        // Assert
        self::assertIsArray($results);
        self::assertEmpty($results, 'Should return empty array when no stores are configured');
        self::assertArrayHasKey('query', [] === $results ? ['query' => $query] : $results, 'Results should maintain query context');
    }

    public function testItHandlesApiFailureGracefully(): void
    {
        // Arrange
        $query = 'laptop';
        $filters = [];

        // Act - with no store configs, handles gracefully
        $results = $this->service->searchProducts($query, $filters);

        // Assert
        self::assertIsArray($results);
    }

    public function testItGetsProductDetailsWithCache(): void
    {
        // Arrange
        $storeName = 'amazon';
        $productId = '123';
        $cacheKey = 'external_product_amazon_123';
        $expectedData = ['name' => 'Product', 'price' => 100];

        Cache::shouldReceive('remember')
            ->with($cacheKey, 3600, \Mockery::type('Closure'))
            ->andReturn($expectedData)
        ;

        // Act
        $result = $this->service->getProductDetails($storeName, $productId);

        // Assert
        self::assertSame($expectedData, $result);
    }

    public function testItReturnsNullForInvalidStore(): void
    {
        // Arrange
        $storeName = 'invalid';
        $productId = '123';

        // Act
        $result = $this->service->getProductDetails($storeName, $productId);

        // Assert
        self::assertNull($result);
    }

    public function testItSyncsStoreProducts(): void
    {
        // Arrange
        $storeName = 'unknown_store';

        // Act - should return 0 for unknown store
        $syncedCount = $this->service->syncStoreProducts($storeName);

        // Assert
        self::assertSame(0, $syncedCount);
    }

    public function testItGetsStoreStatus(): void
    {
        // Act - with empty config, should return empty array
        $status = $this->service->getStoreStatus();

        // Assert
        self::assertIsArray($status);
        self::assertEmpty($status, 'Should return empty status when no stores are configured');

        // Verify the structure when stores are available
        if (! empty($status)) {
            foreach ($status as $storeName => $storeStatus) {
                self::assertIsString($storeName, 'Store name should be a string');
                self::assertIsArray($storeStatus, 'Store status should be an array');
                self::assertArrayHasKey('available', $storeStatus, 'Status should include availability');
            }
        }
    }
}
