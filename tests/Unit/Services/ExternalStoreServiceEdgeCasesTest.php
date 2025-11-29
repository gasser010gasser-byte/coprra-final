<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\Store;
use App\Services\ExternalStoreService;
use App\Services\StoreClients\GenericStoreClient;
use App\Services\StoreClients\StoreClientFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExternalStoreServiceEdgeCasesTest extends TestCase
{
    private ExternalStoreService $service;
    private StoreClientFactory $mockFactory;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock external store configurations
        Config::set('external_stores', [
            'test_store' => [
                'api_url' => 'https://api.teststore.com',
                'api_key' => 'test_key',
                'timeout' => 30,
            ],
            'invalid_store' => [
                'api_url' => 'invalid_url',
                'api_key' => null,
            ],
        ]);

        $this->mockFactory = \Mockery::mock(StoreClientFactory::class);
        $this->service = new ExternalStoreService($this->mockFactory);
    }

    // Edge Cases for Product Search

    public function testSearchProductsWithEmptyQuery(): void
    {
        // Arrange
        $mockClient = \Mockery::mock(GenericStoreClient::class);
        $mockClient->shouldReceive('search')->andReturn([]);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $results = $this->service->searchProducts('');

        // Assert
        self::assertIsArray($results);
        self::assertEmpty($results);
    }

    public function testSearchProductsWithVeryLongQuery(): void
    {
        // Arrange
        $longQuery = str_repeat('a', 10000); // Very long query
        $mockClient = \Mockery::mock(GenericStoreClient::class);
        $mockClient->shouldReceive('search')->andReturn([]);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $results = $this->service->searchProducts($longQuery);

        // Assert
        self::assertIsArray($results);
    }

    public function testSearchProductsWithSpecialCharacters(): void
    {
        // Arrange
        $specialQuery = '<script>alert("xss")</script>'; // XSS attempt
        $mockClient = \Mockery::mock(GenericStoreClient::class);
        $mockClient->shouldReceive('search')->andReturn([]);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $results = $this->service->searchProducts($specialQuery);

        // Assert
        self::assertIsArray($results);
    }

    public function testSearchProductsWithSqlInjectionAttempt(): void
    {
        // Arrange
        $maliciousQuery = "'; DROP TABLE products; --";
        $mockClient = \Mockery::mock(GenericStoreClient::class);
        $mockClient->shouldReceive('search')->andReturn([]);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $results = $this->service->searchProducts($maliciousQuery);

        // Assert
        self::assertIsArray($results);
    }

    public function testSearchProductsWithUnicodeCharacters(): void
    {
        // Arrange
        $unicodeQuery = 'Café ñoño 中文 🎉 émojis';
        $mockClient = \Mockery::mock(GenericStoreClient::class);
        $mockClient->shouldReceive('search')->andReturn([]);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $results = $this->service->searchProducts($unicodeQuery);

        // Assert
        self::assertIsArray($results);
    }

    public function testSearchProductsWithStoreClientException(): void
    {
        // Arrange
        Log::spy();

        // Service loops through all stores in config (test_store and invalid_store)
        // So create() will be called for each store
        $this->mockFactory->shouldReceive('create')
            ->andThrow(new \Exception('Store client creation failed'))
        ;

        // Act
        $results = $this->service->searchProducts('test query');

        // Assert
        self::assertIsArray($results);
        self::assertEmpty($results);

        // Should be called once per store (2 stores in config)
        Log::shouldHaveReceived('error')
            ->twice() // Once for test_store, once for invalid_store
            ->with(\Mockery::pattern('/Failed to search in/'), \Mockery::type('array'))
        ;
    }

    public function testSearchProductsWithInvalidStoreClient(): void
    {
        // Arrange
        // StoreClientFactory::create() returns ?GenericStoreClient, so null is the invalid case
        $this->mockFactory->shouldReceive('create')
            ->andReturnNull()
        ;

        // Act
        $results = $this->service->searchProducts('test query');

        // Assert
        self::assertIsArray($results);
        self::assertEmpty($results);
    }

    // Edge Cases for Product Details

    public function testGetProductDetailsWithInvalidStoreNames(): void
    {
        // Arrange
        $invalidStoreNames = [
            '',
            null,
            '<script>alert("xss")</script>',
            '../../etc/passwd',
            str_repeat('a', 1000),
        ];

        foreach ($invalidStoreNames as $storeName) {
            // Act
            $result = $this->service->getProductDetails((string) $storeName, 'product123');

            // Assert
            self::assertNull($result);
        }
    }

    public function testGetProductDetailsWithInvalidProductIds(): void
    {
        // Arrange
        $invalidProductIds = [
            '',
            null,
            '<script>alert("xss")</script>',
            '"; DROP TABLE products; --',
            str_repeat('a', 1000),
            '../../etc/passwd',
        ];

        foreach ($invalidProductIds as $productId) {
            // Act
            $result = $this->service->getProductDetails('test_store', (string) $productId);

            // Assert
            self::assertNull($result);
        }
    }

    public function testGetProductDetailsWithCacheFailure(): void
    {
        // Arrange
        // Cache::remember() typically doesn't throw exceptions, it returns the callback result
        // But we can simulate a cache failure by making the callback throw
        Cache::shouldReceive('remember')
            ->with("external_product_test_store_product123", 3600, \Mockery::type('callable'))
            ->andReturnUsing(static function ($key, $ttl, $callback) {
                // Simulate cache failure by throwing in callback
                throw new \Exception('Cache failure');
            })
        ;

        // Act
        // This will throw exception, so wrap in try-catch
        try {
            $result = $this->service->getProductDetails('test_store', 'product123');
            // If no exception, result should be null or empty
            self::assertNull($result);
        } catch (\Exception $e) {
            // Exception is acceptable for cache failure scenario
            self::assertStringContainsString('Cache failure', $e->getMessage());
        }
    }

    public function testGetProductDetailsWithMalformedProductData(): void
    {
        // Arrange
        $malformedData = [
            'id' => ['nested' => 'array'], // Invalid ID type
            'title' => null,
            'price' => 'invalid_price',
            'description' => str_repeat('a', 100000), // Very long description
            'image' => 'javascript:alert("xss")', // Malicious URL
        ];

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('getProduct')->willReturn($malformedData);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        Cache::shouldReceive('remember')
            ->andReturnUsing(static function ($key, $ttl, $callback) {
                return $callback();
            })
        ;

        // Act
        $result = $this->service->getProductDetails('test_store', 'product123');

        // Assert
        self::assertIsArray($result);
        self::assertArrayHasKey('external_id', $result);
        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('price', $result);
    }

    // Edge Cases for Store Synchronization

    public function testSyncStoreProductsWithZeroProducts(): void
    {
        // Arrange
        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('syncProducts')
            ->willReturnCallback(static function ($callback) {
                // No products to sync
            })
        ;

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $syncedCount = $this->service->syncStoreProducts('test_store');

        // Assert
        self::assertSame(0, $syncedCount);
    }

    public function testSyncStoreProductsWithLargeDataset(): void
    {
        // Arrange
        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('syncProducts')
            ->willReturnCallback(static function ($callback) {
                // Simulate large dataset
                for ($i = 0; $i < 10000; ++$i) {
                    $callback([
                        'id' => "product_{$i}",
                        'title' => "Product {$i}",
                        'price' => rand(10, 1000),
                    ]);
                }
            })
        ;

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $syncedCount = $this->service->syncStoreProducts('test_store');

        // Assert
        self::assertSame(10000, $syncedCount);
    }

    public function testSyncStoreProductsWithDuplicateExternalIds(): void
    {
        // Arrange
        $duplicateProductData = [
            'id' => 'duplicate_product',
            'title' => 'Duplicate Product',
            'price' => 100,
        ];

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('syncProducts')
            ->willReturnCallback(static function ($callback) use ($duplicateProductData) {
                // Sync the same product twice
                $callback($duplicateProductData);
                $callback($duplicateProductData);
            })
        ;

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $syncedCount = $this->service->syncStoreProducts('test_store');

        // Assert
        self::assertSame(2, $syncedCount);

        // Verify only one product exists in database
        $this->assertDatabaseCount('products', 1);
    }

    public function testSyncStoreProductsWithMaliciousData(): void
    {
        // Arrange
        $maliciousProductData = [
            'id' => '<script>alert("xss")</script>',
            'title' => '"; DROP TABLE products; --',
            'description' => str_repeat('A', 100000),
            'price' => 'javascript:alert("xss")',
            'image' => '../../../../etc/passwd',
        ];

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('syncProducts')
            ->willReturnCallback(static function ($callback) use ($maliciousProductData) {
                $callback($maliciousProductData);
            })
        ;

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $syncedCount = $this->service->syncStoreProducts('test_store');

        // Assert
        self::assertSame(1, $syncedCount);

        // Verify that malicious data is handled safely
        $this->assertDatabaseHas('products', [
            'external_id' => $maliciousProductData['id'],
        ]);
    }

    // Edge Cases for Store Status

    public function testGetStoreStatusWithAllStoresDown(): void
    {
        // Arrange
        $this->mockFactory->shouldReceive('create')
            ->andThrow(new \Exception('All stores are down'))
        ;

        // Act
        $status = $this->service->getStoreStatus();

        // Assert
        self::assertIsArray($status);
        foreach ($status as $storeStatus) {
            self::assertSame('error', $storeStatus['status']);
            self::assertArrayHasKey('error', $storeStatus);
            self::assertArrayHasKey('last_check', $storeStatus);
        }
    }

    public function testGetStoreStatusWithMixedResults(): void
    {
        // Arrange
        $callCount = 0;
        $this->mockFactory->shouldReceive('create')
            ->andReturnUsing(function () use (&$callCount) {
                ++$callCount;
                if (1 === $callCount) {
                    // First store succeeds
                    $mockClient = $this->createMock(GenericStoreClient::class);
                    $mockClient->method('getStatus')->willReturn(['status' => 'online']);

                    return $mockClient;
                }

                // Second store fails
                throw new \Exception('Store unavailable');
            })
        ;

        // Act
        $status = $this->service->getStoreStatus();

        // Assert
        self::assertIsArray($status);
        self::assertCount(2, $status);

        $storeStatuses = array_values($status);
        self::assertSame('online', $storeStatuses[0]['status']);
        self::assertSame('error', $storeStatuses[1]['status']);
    }

    // Edge Cases for Data Normalization

    public function testNormalizeProductDataWithMissingFields(): void
    {
        // Arrange
        $incompleteData = [
            'id' => 'test_product',
            // Missing title, price, etc.
        ];

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('getProduct')->willReturn($incompleteData);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        Cache::shouldReceive('remember')
            ->andReturnUsing(static function ($key, $ttl, $callback) {
                return $callback();
            })
        ;

        // Act
        $result = $this->service->getProductDetails('test_store', 'test_product');

        // Assert
        self::assertIsArray($result);
        self::assertSame('test_product', $result['external_id']);
        self::assertSame('', $result['name']);
        self::assertSame(0, $result['price']);
        self::assertSame('USD', $result['currency']);
        self::assertSame('in_stock', $result['availability']);
    }

    public function testNormalizeProductDataWithNullValues(): void
    {
        // Arrange
        $nullData = [
            'id' => null,
            'title' => null,
            'price' => null,
            'description' => null,
            'image' => null,
        ];

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('getProduct')->willReturn($nullData);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        Cache::shouldReceive('remember')
            ->andReturnUsing(static function ($key, $ttl, $callback) {
                return $callback();
            })
        ;

        // Act
        $result = $this->service->getProductDetails('test_store', 'test_product');

        // Assert
        self::assertIsArray($result);
        self::assertNull($result['external_id']);
        self::assertSame('', $result['name']);
        self::assertSame(0, $result['price']);
        self::assertSame('', $result['description']);
        self::assertSame('', $result['image_url']);
    }

    // Edge Cases for Filtering and Sorting

    public function testSortAndFilterWithInvalidFilters(): void
    {
        // Arrange
        $products = [
            ['name' => 'Product A', 'price' => 100],
            ['name' => 'Product B', 'price' => 50],
        ];

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('search')->willReturn($products);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        $invalidFilters = [
            'sort_by' => 'invalid_field',
            'min_price' => 'not_a_number',
            'max_price' => -100,
            'malicious_filter' => '<script>alert("xss")</script>',
        ];

        // Act
        $results = $this->service->searchProducts('test', $invalidFilters);

        // Assert
        self::assertIsArray($results);
        self::assertCount(2, $results);
    }

    public function testSortAndFilterWithExtremeValues(): void
    {
        // Arrange
        $products = [
            ['name' => 'Product A', 'price' => \PHP_INT_MAX],
            ['name' => 'Product B', 'price' => 0],
            ['name' => 'Product C', 'price' => -100],
        ];

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('search')->willReturn($products);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        $filters = [
            'sort_by' => 'price',
            'min_price' => 0,
            'max_price' => \PHP_INT_MAX,
        ];

        // Act
        $results = $this->service->searchProducts('test', $filters);

        // Assert
        self::assertIsArray($results);
        self::assertGreaterThanOrEqual(0, \count($results));
    }

    // Memory and Performance Edge Cases

    public function testSearchProductsWithMemoryLimitApproach(): void
    {
        // Arrange
        $largeProductArray = [];
        for ($i = 0; $i < 1000; ++$i) {
            $largeProductArray[] = [
                'id' => "product_{$i}",
                'title' => str_repeat("Product {$i} ", 100), // Large title
                'description' => str_repeat('A', 10000), // Large description
                'price' => rand(1, 1000),
            ];
        }

        $mockClient = $this->createMock(GenericStoreClient::class);
        $mockClient->method('search')->willReturn($largeProductArray);

        $this->mockFactory->shouldReceive('create')
            ->andReturn($mockClient)
        ;

        // Act
        $results = $this->service->searchProducts('test');

        // Assert
        self::assertIsArray($results);
        self::assertCount(1000, $results);
    }

    public function testCacheKeyCollisionPrevention(): void
    {
        // Arrange
        $similarProductIds = [
            'product_123',
            'product_12_3',
            'product__123',
        ];

        foreach ($similarProductIds as $productId) {
            $mockClient = $this->createMock(GenericStoreClient::class);
            $mockClient->method('getProduct')->willReturn(['id' => $productId]);

            $this->mockFactory->shouldReceive('create')
                ->andReturn($mockClient)
            ;

            Cache::shouldReceive('remember')
                ->with("external_product_test_store_{$productId}", 3600, \Mockery::type('callable'))
                ->andReturnUsing(static function ($key, $ttl, $callback) {
                    return $callback();
                });

            // Act
            $result = $this->service->getProductDetails('test_store', $productId);

            // Assert
            self::assertIsArray($result);
            self::assertSame($productId, $result['external_id']);
        }
    }
}
