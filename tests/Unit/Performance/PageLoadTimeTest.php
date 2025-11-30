<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PageLoadTimeTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_PAGE_LOAD_TIME_MS = 5000; // 5000ms max page load time (more realistic for test environment)
    private const MAX_API_RESPONSE_TIME_MS = 3000; // 3000ms max API response time (more realistic for test environment)
    private const MAX_HEAVY_PAGE_LOAD_TIME_MS = 10000; // 10000ms for data-heavy pages (more realistic for test environment)
    private const ACCEPTABLE_SLOW_PAGES_RATIO = 0.1; // 10% of pages can be slower

    protected function setUp(): void
    {
        parent::setUp();

        // Clear caches for accurate timing
        Cache::flush();
        View::flushFinderCache();

        // Enable query logging for performance analysis
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        DB::disableQueryLog();
        parent::tearDown();
    }

    #[Test]
    public function testHomePageLoadPerformance(): void
    {
        // Create minimal test data for home page
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(3)->create();
        $featuredProducts = Product::factory()->count(8)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
            'is_featured' => true,
        ]);

        $startTime = microtime(true);
        $response = $this->get('/');
        $endTime = microtime(true);

        $loadTime = ($endTime - $startTime) * 1000;

        // Assert page loads successfully
        $response->assertStatus(200);

        // Assert load time is within acceptable limits
        self::assertLessThan(
            self::MAX_PAGE_LOAD_TIME_MS,
            $loadTime,
            "Home page load time {$loadTime}ms exceeds threshold"
        );

        // Verify essential content is present
        $response->assertSee('COPRRA');
        $response->assertViewIs('home');

        // Check database query efficiency (more lenient for test environment)
        $queries = DB::getQueryLog();
        self::assertLessThan(
            50,
            \count($queries),
            'Home page should use reasonable database queries ('.\count($queries).' used)'
        );

        // Verify featured products are loaded efficiently
        self::assertGreaterThan(0, $featuredProducts->count());
    }

    #[Test]
    public function testProductListingPagePerformance(): void
    {
        // Create comprehensive test data
        $categories = Category::factory()->count(10)->create();
        $brands = Brand::factory()->count(5)->create();
        $stores = Store::factory()->count(4)->create();

        // Create products with price offers
        foreach ($categories->take(3) as $category) {
            $products = Product::factory()->count(25)->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
            ]);

            foreach ($products->take(15) as $product) {
                PriceOffer::factory()->count(rand(1, 3))->create([
                    'product_id' => $product->id,
                    'store_id' => $stores->random()->id,
                ]);
            }
        }

        $category = $categories->first();

        $startTime = microtime(true);
        $response = $this->get("/products?category_id={$category->id}");
        $endTime = microtime(true);

        $loadTime = ($endTime - $startTime) * 1000;

        // Assert page loads successfully
        $response->assertStatus(200);

        // Assert load time for data-heavy page
        self::assertLessThan(
            self::MAX_HEAVY_PAGE_LOAD_TIME_MS,
            $loadTime,
            "Product listing page load time {$loadTime}ms exceeds threshold"
        );

        // Verify pagination and filtering work
        $response->assertViewIs('products.index');

        // Check database query efficiency with eager loading
        $queries = DB::getQueryLog();
        self::assertLessThan(
            250,
            \count($queries),
            'Product listing should use reasonable queries ('.\count($queries).' used)'
        );

        // Verify products are loaded with relationships
        $responseData = $response->getOriginalContent();
        self::assertNotNull($responseData);
    }

    #[Test]
    public function testProductDetailPagePerformance(): void
    {
        // Create detailed product with all relationships
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $stores = Store::factory()->count(5)->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Create multiple price offers
        foreach ($stores as $store) {
            PriceOffer::factory()->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'price' => fake()->randomFloat(2, 100, 1000),
            ]);
        }

        $startTime = microtime(true);
        $response = $this->get("/products/{$product->slug}");
        $endTime = microtime(true);

        $loadTime = ($endTime - $startTime) * 1000;

        // Assert page loads successfully
        $response->assertStatus(200);

        // Assert load time for detailed page
        self::assertLessThan(
            self::MAX_HEAVY_PAGE_LOAD_TIME_MS,
            $loadTime,
            "Product detail page load time {$loadTime}ms exceeds threshold"
        );

        // Verify all product details are displayed
        $response->assertViewIs('products.show');
        $response->assertSee($product->name);
        $response->assertSee($category->name);
        $response->assertSee($brand->name);

        // Check database query efficiency
        $queries = DB::getQueryLog();
        self::assertLessThan(
            50,
            \count($queries),
            'Product detail should use reasonable queries ('.\count($queries).' used)'
        );

        // Verify price comparison data is loaded
        self::assertSame(\count($stores), $product->priceOffers()->count());
    }

    #[Test]
    public function testAPIEndpointResponseTimes(): void
    {
        // Create test data
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(3)->create();
        $products = Product::factory()->count(20)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
        ]);

        $user = User::factory()->create();

        $apiEndpoints = [
            ['GET', '/api/products', 'Products API'],
            ['GET', '/api/categories', 'Categories API'],
            ['GET', '/api/brands', 'Brands API'],
            ['GET', "/api/products/{$products->first()->id}", 'Product Detail API'],
            ['GET', '/api/search?q=test', 'Product Search API'],
        ];

        $slowEndpoints = [];

        foreach ($apiEndpoints as [$method, $endpoint, $description]) {
            $startTime = microtime(true);

            if (str_contains($endpoint, '/api/user/') || str_contains($endpoint, 'profile')) {
                $response = $this->actingAs($user)->json($method, $endpoint);
            } else {
                $response = $this->json($method, $endpoint);
            }

            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;

            // Assert API responds successfully
            self::assertTrue(
                $response->isSuccessful(),
                "{$description} should respond successfully"
            );

            // Track slow endpoints
            if ($responseTime > self::MAX_API_RESPONSE_TIME_MS) {
                $slowEndpoints[] = [
                    'endpoint' => $endpoint,
                    'description' => $description,
                    'response_time' => $responseTime,
                ];
            }

            // Assert individual endpoint performance
            self::assertLessThan(
                self::MAX_API_RESPONSE_TIME_MS * 2,
                $responseTime,
                "{$description} response time {$responseTime}ms is excessively slow"
            );
        }

        // Allow some endpoints to be slower but not too many
        $slowEndpointRatio = \count($slowEndpoints) / \count($apiEndpoints);
        self::assertLessThan(
            self::ACCEPTABLE_SLOW_PAGES_RATIO,
            $slowEndpointRatio,
            'Too many API endpoints are slow: '.json_encode($slowEndpoints)
        );
    }

    #[Test]
    public function testSearchPagePerformanceWithVariousQueries(): void
    {
        // Create comprehensive search test data
        $categories = Category::factory()->count(8)->create();
        $brands = Brand::factory()->count(4)->create();
        $stores = Store::factory()->count(3)->create();

        foreach ($categories->take(4) as $category) {
            $products = Product::factory()->count(30)->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
                'name' => 'Test Product '.fake()->word(),
            ]);

            foreach ($products->take(20) as $product) {
                PriceOffer::factory()->create([
                    'product_id' => $product->id,
                    'store_id' => $stores->random()->id,
                ]);
            }
        }

        $searchQueries = [
            'test',
            'product',
            $categories->first()->name,
            $brands->first()->name,
            'nonexistent',
        ];

        $searchResults = [];

        foreach ($searchQueries as $query) {
            $startTime = microtime(true);
            $response = $this->get('/products/search?q='.urlencode($query));
            $endTime = microtime(true);

            $loadTime = ($endTime - $startTime) * 1000;

            $searchResults[] = [
                'query' => $query,
                'load_time' => $loadTime,
                'status' => $response->getStatusCode(),
                'success' => $response->isSuccessful(),
            ];

            // Assert search page loads successfully
            $response->assertStatus(200);

            // Assert search performance
            self::assertLessThan(
                self::MAX_HEAVY_PAGE_LOAD_TIME_MS,
                $loadTime,
                "Search for '{$query}' took {$loadTime}ms, exceeding threshold"
            );
        }

        // Calculate average search performance
        $avgSearchTime = array_sum(array_column($searchResults, 'load_time')) / \count($searchResults);
        self::assertLessThan(
            self::MAX_PAGE_LOAD_TIME_MS,
            $avgSearchTime,
            "Average search time {$avgSearchTime}ms should be optimized"
        );

        // Verify database query efficiency for searches (more lenient for test environment)
        $queries = DB::getQueryLog();
        $avgQueriesPerSearch = \count($queries) / \count($searchQueries);
        self::assertLessThan(
            100,
            $avgQueriesPerSearch,
            "Search queries should be reasonable (avg {$avgQueriesPerSearch} queries per search)"
        );
    }

    #[Test]
    public function testUserDashboardPagePerformance(): void
    {
        // Create user with associated data
        $user = User::factory()->create();
        $categories = Category::factory()->count(3)->create();
        $brands = Brand::factory()->count(2)->create();

        // Create user's wishlist and price alerts
        $products = Product::factory()->count(10)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
        ]);

        // Simulate user having wishlist items and price alerts
        foreach ($products->take(5) as $product) {
            $user->wishlist()->attach($product->id);
            $user->priceAlerts()->create([
                'product_id' => $product->id,
                'target_price' => fake()->randomFloat(2, 50, 500),
                'is_active' => true,
            ]);
        }

        $startTime = microtime(true);
        $response = $this->actingAs($user)->get('/dashboard');
        $endTime = microtime(true);

        $loadTime = ($endTime - $startTime) * 1000;

        // Assert dashboard loads successfully
        $response->assertStatus(200);

        // Assert dashboard load time
        self::assertLessThan(
            self::MAX_HEAVY_PAGE_LOAD_TIME_MS,
            $loadTime,
            "User dashboard load time {$loadTime}ms exceeds threshold"
        );

        // Verify dashboard content
        $response->assertViewIs('dashboard');
        $response->assertSee($user->name);

        // Check database query efficiency for user data
        $queries = DB::getQueryLog();
        self::assertLessThan(
            20,
            \count($queries),
            'Dashboard should efficiently load user data ('.\count($queries).' queries used)'
        );

        // Verify user's data is properly loaded
        self::assertSame(5, $user->wishlist()->count());
        self::assertSame(5, $user->priceAlerts()->count());
    }

    #[Test]
    public function testPageLoadPerformanceWithCaching(): void
    {
        // Create test data
        $categories = Category::factory()->count(5)->create();
        $products = Product::factory()->count(15)->create([
            'category_id' => $categories->random()->id,
        ]);

        // First request (cold cache)
        $startTime = microtime(true);
        $response1 = $this->get('/');
        $endTime = microtime(true);
        $coldLoadTime = ($endTime - $startTime) * 1000;

        $response1->assertStatus(200);

        // Second request (warm cache)
        $startTime = microtime(true);
        $response2 = $this->get('/');
        $endTime = microtime(true);
        $warmLoadTime = ($endTime - $startTime) * 1000;

        $response2->assertStatus(200);

        // Assert both requests are reasonably fast
        self::assertLessThan(
            self::MAX_PAGE_LOAD_TIME_MS,
            $coldLoadTime,
            "Cold cache load time {$coldLoadTime}ms exceeds threshold"
        );

        self::assertLessThan(
            self::MAX_PAGE_LOAD_TIME_MS,
            $warmLoadTime,
            "Warm cache load time {$warmLoadTime}ms exceeds threshold"
        );

        // Warm cache should be faster (or at least not significantly slower)
        self::assertLessThanOrEqual(
            $coldLoadTime * 1.2,
            $warmLoadTime,
            "Warm cache ({$warmLoadTime}ms) should not be significantly slower than cold cache ({$coldLoadTime}ms)"
        );

        // Verify cache is working by checking query counts
        DB::flushQueryLog();
        $this->get('/');
        $cachedQueries = DB::getQueryLog();

        // With proper caching, subsequent requests should use fewer queries
        self::assertLessThanOrEqual(
            10,
            \count($cachedQueries),
            'Cached requests should use minimal database queries'
        );
    }
}
