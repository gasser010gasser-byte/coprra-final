<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Performance tests for AI-related API endpoints.
 *
 * @internal
 *
 * @coversNothing
 */
final class AIEndpointsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const AI_RESPONSE_TIME_THRESHOLD_MS = 3000; // 3 seconds for AI operations
    private const AI_MEMORY_THRESHOLD_MB = 100; // 100MB for AI operations
    private const MAX_QUERY_COUNT = 15;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('public');
        $this->enableQueryLogging();
    }

    #[Test]
    public function testAIAnalyzePerformance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Test Product for AI Analysis',
            'description' => 'This is a detailed product description for AI analysis testing.',
        ]);

        $this->actingAs($user);

        DB::flushQueryLog();
        $initialMemory = memory_get_usage(true);
        $startTime = microtime(true);

        $response = $this->postJson('/api/ai/analyze', [
            'product_id' => $product->id,
            'analysis_type' => 'sentiment',
            'text' => 'This product is amazing and works perfectly for my needs.',
        ]);

        $responseTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $initialMemory) / 1024 / 1024;

        $response->assertStatus(200);
        self::assertLessThan(
            self::AI_RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "AI analyze took {$responseTime}ms, exceeds threshold"
        );
        self::assertLessThan(
            self::AI_MEMORY_THRESHOLD_MB,
            $memoryUsed,
            "AI analyze used {$memoryUsed}MB memory, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(self::MAX_QUERY_COUNT);
    }

    #[Test]
    public function testAIProductClassificationPerformance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        DB::flushQueryLog();
        $initialMemory = memory_get_usage(true);
        $startTime = microtime(true);

        $response = $this->postJson('/api/ai/classify-product', [
            'name' => 'MacBook Pro 16-inch M2 Laptop',
            'description' => 'High-performance laptop with M2 chip, 16GB RAM, 512GB SSD',
            'features' => ['M2 chip', '16GB RAM', '512GB SSD', 'Retina display'],
        ]);

        $responseTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $initialMemory) / 1024 / 1024;

        $response->assertStatus(200);
        self::assertLessThan(
            self::AI_RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "AI classification took {$responseTime}ms, exceeds threshold"
        );
        self::assertLessThan(
            self::AI_MEMORY_THRESHOLD_MB,
            $memoryUsed,
            "AI classification used {$memoryUsed}MB memory, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(10);
    }

    #[Test]
    public function testAIImageAnalysisPerformance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a fake image file
        $image = UploadedFile::fake()->image('product.jpg', 800, 600);

        DB::flushQueryLog();
        $initialMemory = memory_get_usage(true);
        $startTime = microtime(true);

        $response = $this->postJson('/api/ai/analyze-image', [
            'image' => $image,
            'analysis_type' => 'product_detection',
        ]);

        $responseTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $initialMemory) / 1024 / 1024;

        $response->assertStatus(200);
        self::assertLessThan(
            self::AI_RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "AI image analysis took {$responseTime}ms, exceeds threshold"
        );
        self::assertLessThan(
            self::AI_MEMORY_THRESHOLD_MB,
            $memoryUsed,
            "AI image analysis used {$memoryUsed}MB memory, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(8);
    }

    #[Test]
    public function testAIRecommendationsPerformance(): void
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(20)->create();

        $this->actingAs($user);

        DB::flushQueryLog();
        $initialMemory = memory_get_usage(true);
        $startTime = microtime(true);

        $response = $this->postJson('/api/ai/recommendations', [
            'user_id' => $user->id,
            'product_id' => $products->first()->id,
            'recommendation_type' => 'similar_products',
            'limit' => 10,
        ]);

        $responseTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $initialMemory) / 1024 / 1024;

        $response->assertStatus(200);
        self::assertLessThan(
            self::AI_RESPONSE_TIME_THRESHOLD_MS,
            $responseTime,
            "AI recommendations took {$responseTime}ms, exceeds threshold"
        );
        self::assertLessThan(
            self::AI_MEMORY_THRESHOLD_MB,
            $memoryUsed,
            "AI recommendations used {$memoryUsed}MB memory, exceeds threshold"
        );
        $this->assertQueryCountWithinLimit(self::MAX_QUERY_COUNT);
    }

    #[Test]
    public function testAIBatchProcessingPerformance(): void
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(5)->create();

        $this->actingAs($user);

        $batchData = $products->map(static function ($product) {
            return [
                'product_id' => $product->id,
                'analysis_type' => 'sentiment',
                'text' => "Review text for product {$product->name}",
            ];
        })->toArray();

        DB::flushQueryLog();
        $initialMemory = memory_get_usage(true);
        $startTime = microtime(true);

        $response = $this->postJson('/api/ai/batch-analyze', [
            'analyses' => $batchData,
        ]);

        $responseTime = (microtime(true) - $startTime) * 1000;
        $memoryUsed = (memory_get_usage(true) - $initialMemory) / 1024 / 1024;

        $response->assertStatus(200);
        self::assertLessThan(self::AI_RESPONSE_TIME_THRESHOLD_MS * 2, $responseTime, // Double threshold for batch
            "AI batch processing took {$responseTime}ms, exceeds threshold");
        self::assertLessThan(self::AI_MEMORY_THRESHOLD_MB * 1.5, $memoryUsed, // 1.5x threshold for batch
            "AI batch processing used {$memoryUsed}MB memory, exceeds threshold");
        $this->assertQueryCountWithinLimit(self::MAX_QUERY_COUNT * 2); // Double query limit for batch
    }

    #[Test]
    public function testAICachePerformance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user);

        $requestData = [
            'product_id' => $product->id,
            'analysis_type' => 'sentiment',
            'text' => 'This is a test review for caching.',
        ];

        // First request (cache miss)
        DB::flushQueryLog();
        $startTime = microtime(true);
        $response1 = $this->postJson('/api/ai/analyze', $requestData);
        $firstRequestTime = (microtime(true) - $startTime) * 1000;

        $response1->assertStatus(200);

        // Second request (should be cached)
        DB::flushQueryLog();
        $startTime = microtime(true);
        $response2 = $this->postJson('/api/ai/analyze', $requestData);
        $secondRequestTime = (microtime(true) - $startTime) * 1000;

        $response2->assertStatus(200);

        // Cached request should be significantly faster
        self::assertLessThan(
            $firstRequestTime * 0.5,
            $secondRequestTime,
            "Cached AI request took {$secondRequestTime}ms, should be faster than {$firstRequestTime}ms"
        );

        // Cached request should use fewer queries
        $cachedQueries = \count(DB::getQueryLog());
        self::assertLessThan(
            5,
            $cachedQueries,
            "Cached AI request used {$cachedQueries} queries, should use fewer"
        );
    }

    #[Test]
    public function testAIErrorHandlingPerformance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test with invalid data to trigger error handling
        DB::flushQueryLog();
        $startTime = microtime(true);

        $response = $this->postJson('/api/ai/analyze', [
            'product_id' => 99999, // Non-existent product
            'analysis_type' => 'invalid_type',
            'text' => '',
        ]);

        $responseTime = (microtime(true) - $startTime) * 1000;

        // Error responses should still be fast
        self::assertLessThan(1000, $responseTime, // 1 second for error handling
            "AI error handling took {$responseTime}ms, exceeds threshold");

        // Should return appropriate error status
        $response->assertStatus(422); // Validation error
    }

    #[Test]
    public function testAIMemoryLeakPrevention(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $initialMemory = memory_get_usage(true);

        // Perform multiple AI operations
        for ($i = 0; $i < 5; ++$i) {
            $this->postJson('/api/ai/analyze', [
                'product_id' => Product::factory()->create()->id,
                'analysis_type' => 'sentiment',
                'text' => "Test analysis iteration {$i}",
            ]);
        }

        // Force garbage collection
        if (\function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024;

        // Memory increase should be reasonable for multiple AI operations
        self::assertLessThan(50, $memoryIncrease, // 50MB max increase
            "Memory increased by {$memoryIncrease}MB after multiple AI operations");
    }

    #[Test]
    public function testAIRateLimitingPerformance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $responses = [];
        $totalTime = 0;

        // Make multiple rapid requests
        for ($i = 0; $i < 10; ++$i) {
            $startTime = microtime(true);
            $response = $this->postJson('/api/ai/analyze', [
                'product_id' => Product::factory()->create()->id,
                'analysis_type' => 'sentiment',
                'text' => "Rapid request {$i}",
            ]);
            $requestTime = (microtime(true) - $startTime) * 1000;
            $totalTime += $requestTime;

            $responses[] = $response;
        }

        // Check that rate limiting doesn't severely impact performance
        $averageTime = $totalTime / 10;
        self::assertLessThan(
            self::AI_RESPONSE_TIME_THRESHOLD_MS * 1.5,
            $averageTime,
            "Average AI request time {$averageTime}ms exceeds threshold with rate limiting"
        );

        // Some requests might be rate limited, but not all
        $successfulRequests = collect($responses)->filter(static fn ($r) => 200 === $r->status())->count();
        self::assertGreaterThan(
            5,
            $successfulRequests,
            "Too many requests were rate limited: only {$successfulRequests}/10 succeeded"
        );
    }
}
