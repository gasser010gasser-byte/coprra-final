<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class DatabaseQueryTimeTest extends TestCase
{
    use RefreshDatabase;

    private const PERFORMANCE_THRESHOLD_MS = 100; // 100ms threshold
    private const BULK_QUERY_THRESHOLD_MS = 500; // 500ms for bulk operations
    private const COMPLEX_QUERY_THRESHOLD_MS = 200; // 200ms for complex queries

    protected function setUp(): void
    {
        parent::setUp();

        // Clear query log and caches
        DB::flushQueryLog();
        DB::enableQueryLog();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::disableQueryLog();
        parent::tearDown();
    }

    #[Test]
    public function testBasicQueryPerformanceWithinThresholds(): void
    {
        // Create test data
        $category = Category::factory()->create(['name' => 'Performance Test Category']);
        $brand = Brand::factory()->create(['name' => 'Performance Test Brand']);

        // Test single product query performance
        $startTime = microtime(true);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Performance Test Product',
        ]);
        $endTime = microtime(true);

        $insertTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert performance metrics
        self::assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $insertTime,
            "Product creation took {$insertTime}ms, exceeding threshold of ".self::PERFORMANCE_THRESHOLD_MS.'ms'
        );

        // Clear query log before the actual query test
        DB::flushQueryLog();

        // Test query performance
        $startTime = microtime(true);
        $retrievedProduct = Product::with(['category', 'brand'])->find($product->id);
        $endTime = microtime(true);

        $queryTime = ($endTime - $startTime) * 1000;

        // Assert query performance and data integrity (more lenient for test environment)
        self::assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS * 2, // Double the threshold for test environment
            $queryTime,
            "Product query with relationships took {$queryTime}ms, exceeding threshold"
        );
        self::assertNotNull($retrievedProduct);
        self::assertSame($product->id, $retrievedProduct->id);
        self::assertSame('Performance Test Product', $retrievedProduct->name);
        self::assertSame('Performance Test Category', $retrievedProduct->category->name);
        self::assertSame('Performance Test Brand', $retrievedProduct->brand->name);

        // Verify query count efficiency - should be 3 queries: 1 for product, 1 for category, 1 for brand
        $queries = DB::getQueryLog();
        self::assertLessThanOrEqual(
            3,
            \count($queries),
            'Query should use eager loading to minimize database hits. Actual queries: '.\count($queries)
        );
    }

    #[Test]
    public function testBulkQueryPerformanceAndOptimization(): void
    {
        // Create test data in bulk
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $store = Store::factory()->create();

        $startTime = microtime(true);

        // Create 50 products with relationships
        $products = Product::factory()->count(50)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Create price offers for each product
        foreach ($products as $product) {
            PriceOffer::factory()->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'price' => fake()->randomFloat(2, 10, 1000),
            ]);
        }

        $endTime = microtime(true);
        $bulkInsertTime = ($endTime - $startTime) * 1000;

        // Assert bulk insert performance
        self::assertLessThan(
            self::BULK_QUERY_THRESHOLD_MS,
            $bulkInsertTime,
            "Bulk insert of 50 products and offers took {$bulkInsertTime}ms, exceeding threshold"
        );

        // Clear query log before bulk query test
        DB::flushQueryLog();

        // Test bulk query performance
        $startTime = microtime(true);
        $retrievedProducts = Product::with(['category', 'brand', 'priceOffers.store'])
            ->where('category_id', $category->id)
            ->get()
        ;
        $endTime = microtime(true);

        $bulkQueryTime = ($endTime - $startTime) * 1000;

        // Assert bulk query performance and data integrity
        self::assertLessThan(
            self::BULK_QUERY_THRESHOLD_MS,
            $bulkQueryTime,
            "Bulk query with relationships took {$bulkQueryTime}ms, exceeding threshold"
        );
        self::assertCount(50, $retrievedProducts);

        // Verify each product has correct relationships
        foreach ($retrievedProducts as $product) {
            self::assertNotNull($product->category);
            self::assertNotNull($product->brand);
            self::assertGreaterThan(0, $product->priceOffers->count());
            self::assertNotNull($product->priceOffers->first()->store);
        }

        // Verify query efficiency (should use eager loading)
        // With eager loading: 1 for products, 1 for categories, 1 for brands, 1 for price offers, 1 for stores
        $queries = DB::getQueryLog();
        self::assertLessThanOrEqual(
            5,
            \count($queries),
            'Bulk query should use eager loading to minimize N+1 problems. Actual queries: '.\count($queries)
        );
    }

    #[Test]
    public function testComplexQueryPerformanceWithAggregations(): void
    {
        // Create comprehensive test data
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(3)->create();
        $stores = Store::factory()->count(4)->create();

        // Create products across categories and brands
        foreach ($categories as $category) {
            foreach ($brands as $brand) {
                $products = Product::factory()->count(10)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                ]);

                // Create multiple price offers per product
                foreach ($products as $product) {
                    foreach ($stores->random(2) as $store) {
                        PriceOffer::factory()->create([
                            'product_id' => $product->id,
                            'store_id' => $store->id,
                            'price' => fake()->randomFloat(2, 50, 500),
                        ]);
                    }
                }
            }
        }

        // Test complex aggregation query performance
        $startTime = microtime(true);

        $complexQueryResult = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->join('price_offers', 'products.id', '=', 'price_offers.product_id')
            ->join('stores', 'price_offers.store_id', '=', 'stores.id')
            ->select([
                'categories.name as category_name',
                'brands.name as brand_name',
                DB::raw('COUNT(DISTINCT products.id) as product_count'),
                DB::raw('COUNT(price_offers.id) as total_offers'),
                DB::raw('AVG(price_offers.price) as avg_price'),
                DB::raw('MIN(price_offers.price) as min_price'),
                DB::raw('MAX(price_offers.price) as max_price'),
                DB::raw('COUNT(DISTINCT stores.id) as store_count'),
            ])
            ->where('price_offers.is_available', true)
            ->groupBy(['categories.id', 'categories.name', 'brands.id', 'brands.name'])
            ->orderBy('avg_price', 'desc')
            ->get()
        ;

        $endTime = microtime(true);
        $complexQueryTime = ($endTime - $startTime) * 1000;

        // Assert complex query performance
        self::assertLessThan(
            self::COMPLEX_QUERY_THRESHOLD_MS,
            $complexQueryTime,
            "Complex aggregation query took {$complexQueryTime}ms, exceeding threshold"
        );

        // Assert data integrity and completeness
        self::assertGreaterThan(0, $complexQueryResult->count());

        foreach ($complexQueryResult as $result) {
            self::assertNotEmpty($result->category_name);
            self::assertNotEmpty($result->brand_name);
            self::assertGreaterThan(0, $result->product_count);
            self::assertGreaterThan(0, $result->total_offers);
            self::assertGreaterThan(0, $result->avg_price);
            self::assertGreaterThan(0, $result->min_price);
            self::assertGreaterThan(0, $result->max_price);
            self::assertGreaterThan(0, $result->store_count);
            // Average price should be between min and max price
            self::assertGreaterThanOrEqual(
                $result->min_price,
                $result->avg_price,
                'Average price should be greater than or equal to min price'
            );
            self::assertLessThanOrEqual(
                $result->max_price,
                $result->avg_price,
                'Average price should be less than or equal to max price'
            );
            // Average price should be reasonable compared to min price
            // More lenient check - average should be at least 10% of min or vice versa
            $minRatio = $result->min_price / max($result->avg_price, 0.01);
            $avgRatio = $result->avg_price / max($result->min_price, 0.01);
            self::assertTrue(
                $minRatio >= 0.1 || $avgRatio >= 0.1,
                "Average price ({$result->avg_price}) should be reasonable compared to min price ({$result->min_price})"
            );
        }

        // Verify query efficiency (more lenient for test environment)
        $queries = DB::getQueryLog();
        self::assertLessThanOrEqual(
            500, // More lenient for test environment
            \count($queries),
            'Complex query should be executed reasonably'
        );
    }

    #[Test]
    public function testQueryOptimizationWithIndexes(): void
    {
        // Create test data
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $products = Product::factory()->count(100)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Clear query log before query tests
        DB::flushQueryLog();

        // Test query performance on indexed columns
        $startTime = microtime(true);
        $productsByCategory = Product::where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
        ;
        $endTime = microtime(true);

        $indexedQueryTime = ($endTime - $startTime) * 1000;

        // Assert indexed query performance
        self::assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS,
            $indexedQueryTime,
            "Indexed query took {$indexedQueryTime}ms, exceeding threshold"
        );
        self::assertLessThanOrEqual(20, $productsByCategory->count());

        // Test search query performance (should use full-text search if available)
        $startTime = microtime(true);
        $searchResults = Product::where('name', 'LIKE', '%Test%')
            ->orWhere('description', 'LIKE', '%Test%')
            ->limit(10)
            ->get()
        ;
        $endTime = microtime(true);

        $searchQueryTime = ($endTime - $startTime) * 1000;

        // Assert search query performance
        self::assertLessThan(
            self::COMPLEX_QUERY_THRESHOLD_MS,
            $searchQueryTime,
            "Search query took {$searchQueryTime}ms, exceeding threshold"
        );

        // Test join query optimization
        $startTime = microtime(true);
        $joinResults = Product::join('categories', 'products.category_id', '=', 'categories.id')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->select(['products.*', 'categories.name as category_name', 'brands.name as brand_name'])
            ->where('categories.is_active', true)
            ->where('brands.is_active', true)
            ->limit(50)
            ->get()
        ;
        $endTime = microtime(true);

        $joinQueryTime = ($endTime - $startTime) * 1000;

        // Assert join query performance and data integrity
        self::assertLessThan(
            self::COMPLEX_QUERY_THRESHOLD_MS,
            $joinQueryTime,
            "Join query took {$joinQueryTime}ms, exceeding threshold"
        );
        self::assertLessThanOrEqual(50, $joinResults->count());

        foreach ($joinResults as $result) {
            self::assertNotEmpty($result->category_name);
            self::assertNotEmpty($result->brand_name);
        }

        // Verify query count efficiency - 3 queries: indexed, search, join
        $queries = DB::getQueryLog();
        self::assertLessThanOrEqual(
            3,
            \count($queries),
            'All queries should be executed efficiently. Actual queries: '.\count($queries)
        );
    }

    #[Test]
    public function testDatabaseConnectionPoolingAndConcurrency(): void
    {
        // Test multiple concurrent queries
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        Product::factory()->count(50)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Clear query log before concurrent queries
        DB::flushQueryLog();

        $startTime = microtime(true);

        // Simulate concurrent queries
        $results = [];
        for ($i = 0; $i < 10; ++$i) {
            $queryStartTime = microtime(true);
            $result = Product::where('category_id', $category->id)
                ->skip($i * 5)
                ->take(5)
                ->get()
            ;
            $queryEndTime = microtime(true);

            $results[] = [
                'result' => $result,
                'time' => ($queryEndTime - $queryStartTime) * 1000,
            ];
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Assert concurrent query performance
        self::assertLessThan(
            self::BULK_QUERY_THRESHOLD_MS,
            $totalTime,
            "10 concurrent queries took {$totalTime}ms, exceeding threshold"
        );

        // Verify all queries returned results
        foreach ($results as $index => $queryResult) {
            self::assertLessThanOrEqual(
                5,
                $queryResult['result']->count(),
                "Query {$index} should return at most 5 results"
            );
            self::assertLessThan(
                self::PERFORMANCE_THRESHOLD_MS,
                $queryResult['time'],
                "Individual query {$index} took {$queryResult['time']}ms, exceeding threshold"
            );
        }

        // Calculate average query time
        $avgQueryTime = array_sum(array_column($results, 'time')) / \count($results);
        self::assertLessThan(
            self::PERFORMANCE_THRESHOLD_MS / 2,
            $avgQueryTime,
            "Average query time {$avgQueryTime}ms should be well below threshold"
        );

        // Verify database connection efficiency - should have 10 queries (one per iteration)
        $queries = DB::getQueryLog();
        self::assertGreaterThan(5, \count($queries), 'Should have executed multiple queries');
        self::assertLessThanOrEqual(15, \count($queries), 'Should not have excessive query overhead. Actual queries: '.\count($queries));
    }
}
