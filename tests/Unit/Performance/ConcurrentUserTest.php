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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConcurrentUserTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_RESPONSE_TIME_MS = 200; // 200ms max response time
    private const CONCURRENT_USERS = 10; // Number of concurrent users to simulate
    private const STRESS_TEST_USERS = 25; // Higher load for stress testing
    private const ACCEPTABLE_FAILURE_RATE = 0.05; // 5% acceptable failure rate

    protected function setUp(): void
    {
        parent::setUp();

        // Clear caches and queues
        Cache::flush();
        Queue::fake();
        Session::flush();

        // Enable query logging for performance monitoring
        DB::enableQueryLog();
    }

    protected function tearDown(): void
    {
        DB::disableQueryLog();
        parent::tearDown();
    }

    #[Test]
    public function testConcurrentUserAuthenticationPerformance(): void
    {
        // Create test users
        $users = User::factory()->count(self::CONCURRENT_USERS)->create();

        $startTime = microtime(true);
        $authResults = [];

        // Simulate concurrent authentication attempts
        foreach ($users as $index => $user) {
            $userStartTime = microtime(true);

            // Simulate authentication process
            $response = $this->actingAs($user)->get('/api/user/profile');
            $userEndTime = microtime(true);

            $authResults[] = [
                'user_id' => $user->id,
                'response_time' => ($userEndTime - $userStartTime) * 1000,
                'status_code' => $response->getStatusCode(),
                'success' => $response->isSuccessful(),
            ];
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Assert overall performance
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * self::CONCURRENT_USERS,
            $totalTime,
            "Concurrent authentication took {$totalTime}ms, exceeding threshold"
        );

        // Analyze individual user performance
        $successfulAuths = array_filter($authResults, static fn ($result) => $result['success']);
        $failureRate = 1 - (\count($successfulAuths) / \count($authResults));

        self::assertLessThan(
            self::ACCEPTABLE_FAILURE_RATE,
            $failureRate,
            "Authentication failure rate {$failureRate} exceeds acceptable threshold"
        );

        // Check individual response times
        foreach ($authResults as $result) {
            self::assertLessThan(
                self::MAX_RESPONSE_TIME_MS,
                $result['response_time'],
                "User {$result['user_id']} authentication took {$result['response_time']}ms"
            );

            if ($result['success']) {
                self::assertSame(200, $result['status_code']);
            }
        }

        // Calculate performance metrics
        $avgResponseTime = array_sum(array_column($successfulAuths, 'response_time')) / \count($successfulAuths);
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * 0.7,
            $avgResponseTime,
            "Average authentication time {$avgResponseTime}ms should be well below threshold"
        );
    }

    #[Test]
    public function testConcurrentProductSearchPerformance(): void
    {
        // Create test data
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(3)->create();
        $stores = Store::factory()->count(4)->create();

        // Create products with price offers
        foreach ($categories as $category) {
            $products = Product::factory()->count(20)->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
            ]);

            foreach ($products as $product) {
                PriceOffer::factory()->count(rand(1, 3))->create([
                    'product_id' => $product->id,
                    'store_id' => $stores->random()->id,
                ]);
            }
        }

        // Create concurrent users
        $users = User::factory()->count(self::CONCURRENT_USERS)->create();

        $startTime = microtime(true);
        $searchResults = [];

        // Simulate concurrent product searches
        foreach ($users as $index => $user) {
            $searchStartTime = microtime(true);

            // Different search patterns for each user
            $searchQueries = [
                '/api/products/search?q=test',
                '/api/products/search?category_id='.$categories->random()->id,
                '/api/products/search?brand_id='.$brands->random()->id,
                '/api/products/search?min_price=50&max_price=500',
                '/api/products/search?sort=price_asc&limit=10',
            ];

            $query = $searchQueries[$index % \count($searchQueries)];
            $response = $this->actingAs($user)->get($query);
            $searchEndTime = microtime(true);

            $searchResults[] = [
                'user_id' => $user->id,
                'query' => $query,
                'response_time' => ($searchEndTime - $searchStartTime) * 1000,
                'status_code' => $response->getStatusCode(),
                'success' => $response->isSuccessful(),
                'result_count' => $response->isSuccessful() ? \count($response->json('data', [])) : 0,
            ];
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Assert overall search performance
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * self::CONCURRENT_USERS,
            $totalTime,
            "Concurrent searches took {$totalTime}ms, exceeding threshold"
        );

        // Analyze search results
        $successfulSearches = array_filter($searchResults, static fn ($result) => $result['success']);
        $searchFailureRate = 1 - (\count($successfulSearches) / \count($searchResults));

        self::assertLessThan(
            self::ACCEPTABLE_FAILURE_RATE,
            $searchFailureRate,
            "Search failure rate {$searchFailureRate} exceeds acceptable threshold"
        );

        // Verify individual search performance
        foreach ($searchResults as $result) {
            self::assertLessThan(
                self::MAX_RESPONSE_TIME_MS,
                $result['response_time'],
                "Search query '{$result['query']}' took {$result['response_time']}ms"
            );

            if ($result['success']) {
                self::assertSame(200, $result['status_code']);
                self::assertGreaterThanOrEqual(0, $result['result_count']);
            }
        }

        // Calculate search performance metrics
        $avgSearchTime = array_sum(array_column($successfulSearches, 'response_time')) / \count($successfulSearches);
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * 0.8,
            $avgSearchTime,
            "Average search time {$avgSearchTime}ms should be reasonable"
        );

        // Verify database query efficiency
        $queries = DB::getQueryLog();
        $avgQueriesPerSearch = \count($queries) / \count($searchResults);
        self::assertLessThan(
            10,
            $avgQueriesPerSearch,
            "Average queries per search ({$avgQueriesPerSearch}) should be optimized"
        );
    }

    #[Test]
    public function testConcurrentPriceComparisonPerformance(): void
    {
        // Create comprehensive test data
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $stores = Store::factory()->count(5)->create();

        $products = Product::factory()->count(30)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Create multiple price offers per product
        foreach ($products as $product) {
            foreach ($stores as $store) {
                PriceOffer::factory()->create([
                    'product_id' => $product->id,
                    'store_id' => $store->id,
                    'price' => fake()->randomFloat(2, 100, 1000),
                ]);
            }
        }

        $users = User::factory()->count(self::CONCURRENT_USERS)->create();

        $startTime = microtime(true);
        $comparisonResults = [];

        // Simulate concurrent price comparison requests
        foreach ($users as $index => $user) {
            $comparisonStartTime = microtime(true);

            $product = $products->random();
            $response = $this->actingAs($user)->get("/api/products/{$product->id}/price-comparison");
            $comparisonEndTime = microtime(true);

            $comparisonResults[] = [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'response_time' => ($comparisonEndTime - $comparisonStartTime) * 1000,
                'status_code' => $response->getStatusCode(),
                'success' => $response->isSuccessful(),
                'offer_count' => $response->isSuccessful() ? \count($response->json('data.price_offers', [])) : 0,
            ];
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Assert price comparison performance
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * self::CONCURRENT_USERS,
            $totalTime,
            "Concurrent price comparisons took {$totalTime}ms, exceeding threshold"
        );

        // Analyze comparison results
        $successfulComparisons = array_filter($comparisonResults, static fn ($result) => $result['success']);
        $comparisonFailureRate = 1 - (\count($successfulComparisons) / \count($comparisonResults));

        self::assertLessThan(
            self::ACCEPTABLE_FAILURE_RATE,
            $comparisonFailureRate,
            "Price comparison failure rate {$comparisonFailureRate} exceeds acceptable threshold"
        );

        // Verify individual comparison performance
        foreach ($comparisonResults as $result) {
            self::assertLessThan(
                self::MAX_RESPONSE_TIME_MS,
                $result['response_time'],
                "Price comparison for product {$result['product_id']} took {$result['response_time']}ms"
            );

            if ($result['success']) {
                self::assertSame(200, $result['status_code']);
                self::assertGreaterThan(
                    0,
                    $result['offer_count'],
                    "Product {$result['product_id']} should have price offers"
                );
                self::assertLessThanOrEqual(
                    \count($stores),
                    $result['offer_count'],
                    'Offer count should not exceed number of stores'
                );
            }
        }

        // Calculate comparison performance metrics
        $avgComparisonTime = array_sum(array_column($successfulComparisons, 'response_time')) / \count($successfulComparisons);
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * 0.75,
            $avgComparisonTime,
            "Average comparison time {$avgComparisonTime}ms should be efficient"
        );
    }

    #[Test]
    public function testStressTestWithHighConcurrentLoad(): void
    {
        // Create extensive test data for stress testing
        $categories = Category::factory()->count(10)->create();
        $brands = Brand::factory()->count(5)->create();
        $stores = Store::factory()->count(8)->create();

        foreach ($categories->take(5) as $category) {
            $products = Product::factory()->count(50)->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
            ]);

            foreach ($products->take(25) as $product) {
                PriceOffer::factory()->count(rand(2, 4))->create([
                    'product_id' => $product->id,
                    'store_id' => $stores->random()->id,
                ]);
            }
        }

        $users = User::factory()->count(self::STRESS_TEST_USERS)->create();

        $startTime = microtime(true);
        $stressResults = [];

        // Simulate high concurrent load with mixed operations
        foreach ($users as $index => $user) {
            $operationStartTime = microtime(true);

            // Mix of different operations to simulate real usage
            $operations = [
                fn () => $this->actingAs($user)->get('/api/products'),
                fn () => $this->actingAs($user)->get('/api/products/search?q=test'),
                fn () => $this->actingAs($user)->get('/api/categories'),
                fn () => $this->actingAs($user)->get('/api/brands'),
                fn () => $this->actingAs($user)->get('/api/user/profile'),
            ];

            $operation = $operations[$index % \count($operations)];
            $response = $operation();
            $operationEndTime = microtime(true);

            $stressResults[] = [
                'user_id' => $user->id,
                'operation_index' => $index % \count($operations),
                'response_time' => ($operationEndTime - $operationStartTime) * 1000,
                'status_code' => $response->getStatusCode(),
                'success' => $response->isSuccessful(),
                'memory_usage' => memory_get_usage(true),
            ];
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Assert stress test performance
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * self::STRESS_TEST_USERS * 1.5,
            $totalTime,
            "Stress test with {$stressResults} users took {$totalTime}ms"
        );

        // Analyze stress test results
        $successfulOperations = array_filter($stressResults, static fn ($result) => $result['success']);
        $stressFailureRate = 1 - (\count($successfulOperations) / \count($stressResults));

        self::assertLessThan(
            self::ACCEPTABLE_FAILURE_RATE * 2,
            $stressFailureRate,
            "Stress test failure rate {$stressFailureRate} exceeds acceptable threshold"
        );

        // Verify system stability under load
        foreach ($stressResults as $result) {
            self::assertLessThan(
                self::MAX_RESPONSE_TIME_MS * 2,
                $result['response_time'],
                "Operation under stress took {$result['response_time']}ms"
            );

            // Check memory usage doesn't spike excessively
            self::assertLessThan(128 * 1024 * 1024, $result['memory_usage'], // 128MB limit
                "Memory usage {$result['memory_usage']} bytes is excessive");
        }

        // Calculate stress test metrics
        $avgStressResponseTime = array_sum(array_column($successfulOperations, 'response_time')) / \count($successfulOperations);
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * 1.5,
            $avgStressResponseTime,
            "Average stress test response time {$avgStressResponseTime}ms should be reasonable"
        );

        // Verify database performance under stress
        $queries = DB::getQueryLog();
        $avgQueriesPerOperation = \count($queries) / \count($stressResults);
        self::assertLessThan(
            15,
            $avgQueriesPerOperation,
            "Average queries per operation under stress ({$avgQueriesPerOperation}) should be optimized"
        );
    }

    #[Test]
    public function testConcurrentUserSessionManagement(): void
    {
        $users = User::factory()->count(self::CONCURRENT_USERS)->create();

        $startTime = microtime(true);
        $sessionResults = [];

        // Test concurrent session creation and management
        foreach ($users as $index => $user) {
            $sessionStartTime = microtime(true);

            // Simulate session operations
            $loginResponse = $this->post('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            if ($loginResponse->isSuccessful()) {
                $token = $loginResponse->json('token');

                // Test authenticated request
                $profileResponse = $this->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                ])->get('/api/user/profile');

                // Test logout
                $logoutResponse = $this->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                ])->post('/api/auth/logout');
            }

            $sessionEndTime = microtime(true);

            $sessionResults[] = [
                'user_id' => $user->id,
                'response_time' => ($sessionEndTime - $sessionStartTime) * 1000,
                'login_success' => $loginResponse->isSuccessful(),
                'profile_success' => isset($profileResponse) ? $profileResponse->isSuccessful() : false,
                'logout_success' => isset($logoutResponse) ? $logoutResponse->isSuccessful() : false,
            ];
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Assert session management performance
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * self::CONCURRENT_USERS * 3,
            $totalTime,
            "Concurrent session management took {$totalTime}ms"
        );

        // Verify session operations
        $successfulSessions = array_filter($sessionResults, static fn ($result) => $result['login_success'] && $result['profile_success'] && $result['logout_success']);

        $sessionFailureRate = 1 - (\count($successfulSessions) / \count($sessionResults));
        self::assertLessThan(
            self::ACCEPTABLE_FAILURE_RATE,
            $sessionFailureRate,
            "Session management failure rate {$sessionFailureRate} exceeds acceptable threshold"
        );

        // Check individual session performance
        foreach ($sessionResults as $result) {
            self::assertLessThan(
                self::MAX_RESPONSE_TIME_MS * 3,
                $result['response_time'],
                "Session operations for user {$result['user_id']} took {$result['response_time']}ms"
            );
        }

        // Calculate session performance metrics
        $avgSessionTime = array_sum(array_column($successfulSessions, 'response_time')) / \count($successfulSessions);
        self::assertLessThan(
            self::MAX_RESPONSE_TIME_MS * 2,
            $avgSessionTime,
            "Average session management time {$avgSessionTime}ms should be efficient"
        );
    }
}
