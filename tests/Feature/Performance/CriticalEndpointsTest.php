<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Performance tests for critical API endpoints.
 *
 * @internal
 *
 * @coversNothing
 */
final class CriticalEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const RESPONSE_TIME_THRESHOLD_MS = 500;
    private const SEARCH_RESPONSE_TIME_THRESHOLD_MS = 1000;
    private const AI_RESPONSE_TIME_THRESHOLD_MS = 2000;
    private const MAX_QUERY_COUNT = 10;
    private const MAX_MEMORY_USAGE_MB = 50;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->enableQueryLogging();
    }

    #[Test]
    public function testAuthenticationEndpointsPerformance(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Test login performance
        $startTime = microtime(true);
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $loginTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $loginTime,
            "Login endpoint took {$loginTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(5);

        // Test user info performance
        $token = $response->json('token');

        DB::flushQueryLog();
        $startTime = microtime(true);
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/user')
        ;
        $userInfoTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $userInfoTime,
            "User info endpoint took {$userInfoTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(2);
    }

    #[Test]
    public function testProductListingPerformance(): void
    {
        // Create test data with relationships
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(5)->create();
        $stores = Store::factory()->count(3)->create();

        Product::factory()->count(50)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
        ]);

        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/products?per_page=20');

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Products listing took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(self::MAX_QUERY_COUNT);

        // Verify response structure
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'category', 'brand'],
            ],
            'meta' => ['total', 'per_page', 'current_page'],
        ]);
    }

    #[Test]
    public function testProductDetailPerformance(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson("/api/products/{$product->id}");

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Product detail took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(5);
    }

    #[Test]
    public function testPriceSearchPerformance(): void
    {
        // Create test data
        $categories = Category::factory()->count(3)->create();
        $brands = Brand::factory()->count(3)->create();

        Product::factory()->count(20)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
            'name' => 'Test Laptop Product',
        ]);

        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/price-search?query=laptop&limit=10');

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::SEARCH_RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Price search took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(self::MAX_QUERY_COUNT);
    }

    #[Test]
    public function testBestOfferPerformance(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson("/api/v1/best-offer?product_id={$product->id}");

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::SEARCH_RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Best offer took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(self::MAX_QUERY_COUNT);
    }

    #[Test]
    public function testSearchEndpointPerformance(): void
    {
        // Create searchable test data
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(5)->create();

        Product::factory()->count(30)->create([
            'category_id' => $categories->random()->id,
            'brand_id' => $brands->random()->id,
        ]);

        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/search?q=product&limit=15');

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::SEARCH_RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Search endpoint took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(self::MAX_QUERY_COUNT);
    }

    #[Test]
    public function testCategoriesListingPerformance(): void
    {
        Category::factory()->count(20)->create();

        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/categories');

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Categories listing took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(3);
    }

    #[Test]
    public function testBrandsListingPerformance(): void
    {
        Brand::factory()->count(25)->create();

        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/brands');

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Brands listing took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(3);
    }

    #[Test]
    public function testSystemHealthPerformance(): void
    {
        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/health');

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "Health check took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(5);
    }

    #[Test]
    public function testSystemInfoPerformance(): void
    {
        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/system/info');

        $responseTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        self::assertLessThan(
            self::RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "System info took {$responseTime}ms, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(3);
    }

    #[Test]
    public function testMemoryUsageForCriticalEndpoints(): void
    {
        $initialMemory = memory_get_usage(true);

        // Test multiple endpoints to ensure memory doesn't accumulate
        $this->getJson('/api/categories');
        $this->getJson('/api/brands');
        $this->getJson('/api/health');
        $this->getJson('/api/system/info');

        $finalMemory = memory_get_usage(true);
        $memoryUsedMB = ($finalMemory - $initialMemory) / 1024 / 1024;

        self::assertLessThan(
            self::MAX_MEMORY_USAGE_MB,
            $memoryUsedMB,
            "Memory usage {$memoryUsedMB}MB exceeds threshold for critical endpoints"
        );
    }

    #[Test]
    public function testConcurrentRequestsPerformance(): void
    {
        // Create test data
        Category::factory()->count(5)->create();
        Brand::factory()->count(5)->create();
        Product::factory()->count(20)->create();

        $startTime = microtime(true);
        $responses = [];

        // Simulate concurrent requests
        for ($i = 0; $i < 5; ++$i) {
            $responses[] = $this->getJson('/api/products?per_page=10');
            $responses[] = $this->getJson('/api/categories');
            $responses[] = $this->getJson('/api/brands');
        }

        $totalTime = (microtime(true) - $startTime) * 1000;

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Total time for 15 requests should be reasonable
        self::assertLessThan(5000, $totalTime, // 5 seconds for 15 requests
            "Concurrent requests took {$totalTime}ms, exceeds threshold");
    }

    /**
     * Assert that response time is within acceptable limits.
     */
    private function assertResponseTime(float $actualMs, float $thresholdMs = self::RESPONSE_TIME_THRESHOLD_MS): void
    {
        self::assertLessThan(
            $thresholdMs,
            $actualMs,
            "Response time {$actualMs}ms exceeds threshold {$thresholdMs}ms"
        );
    }
}
