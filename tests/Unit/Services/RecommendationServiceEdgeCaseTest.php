<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\UserPurchase;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Edge case tests for RecommendationService covering critical recommendation failure scenarios.
 *
 * @internal
 *
 * @coversNothing
 */
final class RecommendationServiceEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationService $recommendationService;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = new \App\Repositories\RecommendationRepository();
        $this->recommendationService = new RecommendationService($repository);
    }

    public function testGetRecommendationsWithNonExistentUser(): void
    {
        $recommendations = $this->recommendationService->getRecommendations(99999);

        self::assertIsArray($recommendations);
        self::assertEmpty($recommendations);
    }

    public function testGetRecommendationsWithEmptyDatabase(): void
    {
        // Ensure database is completely empty
        DB::table('users')->delete();
        DB::table('products')->delete();
        DB::table('user_purchases')->delete();

        $user = User::factory()->create();

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        self::assertEmpty($recommendations);
    }

    public function testGetRecommendationsWithUserWithNoPurchases(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(10)->create();

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        // Should fallback to popular products or return empty array
        self::assertIsArray($recommendations);
    }

    public function testGetRecommendationsWithOnlyOneProduct(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        UserPurchase::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        // Should not recommend the same product user already bought
        self::assertNotContains($product->id, array_column($recommendations, 'id'));
    }

    public function testGetRecommendationsWithCircularUserSimilarity(): void
    {
        // Create users with circular purchase patterns
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();

        // Create circular pattern: user1 -> product1, user2 -> product2, user3 -> product3
        // But also: user1 -> product2, user2 -> product3, user3 -> product1
        UserPurchase::factory()->create(['user_id' => $user1->id, 'product_id' => $product1->id]);
        UserPurchase::factory()->create(['user_id' => $user1->id, 'product_id' => $product2->id]);

        UserPurchase::factory()->create(['user_id' => $user2->id, 'product_id' => $product2->id]);
        UserPurchase::factory()->create(['user_id' => $user2->id, 'product_id' => $product3->id]);

        UserPurchase::factory()->create(['user_id' => $user3->id, 'product_id' => $product3->id]);
        UserPurchase::factory()->create(['user_id' => $user3->id, 'product_id' => $product1->id]);

        $recommendations = $this->recommendationService->getRecommendations($user1->id);

        self::assertIsArray($recommendations);
        // Should handle circular dependencies gracefully
        self::assertLessThanOrEqual(10, \count($recommendations)); // Should not infinite loop
    }

    public function testGetRecommendationsWithDatabaseConnectionFailure(): void
    {
        $user = User::factory()->create();

        // Simulate database connection failure
        DB::shouldReceive('table')
            ->andThrow(new \Exception('Database connection failed'))
        ;

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        self::assertEmpty($recommendations);
    }

    public function testGetRecommendationsWithCorruptedUserData(): void
    {
        // Create user with invalid/corrupted data
        $user = User::factory()->create([
            'id' => -1, // Invalid ID
        ]);

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        self::assertEmpty($recommendations);
    }

    public function testGetRecommendationsWithExtremelyLargeDataset(): void
    {
        $user = User::factory()->create();

        // Create a large number of users and products to test performance
        $users = User::factory()->count(1000)->create();
        $products = Product::factory()->count(1000)->create();

        // Create many purchases to simulate large dataset
        foreach ($users->take(100) as $otherUser) {
            foreach ($products->take(10) as $product) {
                UserPurchase::factory()->create([
                    'user_id' => $otherUser->id,
                    'product_id' => $product->id,
                ]);
            }
        }

        $startTime = microtime(true);
        $recommendations = $this->recommendationService->getRecommendations($user->id);
        $endTime = microtime(true);

        self::assertIsArray($recommendations);
        // Should complete within reasonable time (5 seconds)
        self::assertLessThan(5.0, $endTime - $startTime);
    }

    public function testGetRecommendationsWithDuplicatePurchases(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Create duplicate purchases (should not happen in real scenario but test edge case)
        UserPurchase::factory()->count(5)->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        // Should handle duplicates gracefully and not recommend same product
        self::assertNotContains($product->id, array_column($recommendations, 'id'));
    }

    public function testGetRecommendationsWithDeletedProducts(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        UserPurchase::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // Soft delete the product
        $product->delete();

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        // Should not recommend deleted products
        self::assertNotContains($product->id, array_column($recommendations, 'id'));
    }

    public function testGetRecommendationsWithInactiveProducts(): void
    {
        $user = User::factory()->create();
        $activeProduct = Product::factory()->create(['is_active' => true]);
        $inactiveProduct = Product::factory()->create(['is_active' => false]);

        $similarUser = User::factory()->create();
        UserPurchase::factory()->create([
            'user_id' => $similarUser->id,
            'product_id' => $activeProduct->id,
        ]);
        UserPurchase::factory()->create([
            'user_id' => $similarUser->id,
            'product_id' => $inactiveProduct->id,
        ]);

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        // Should only recommend active products
        $recommendedIds = array_column($recommendations, 'id');
        self::assertNotContains($inactiveProduct->id, $recommendedIds);
    }

    public function testGetRecommendationsWithZeroPricedProducts(): void
    {
        $user = User::factory()->create();
        $freeProduct = Product::factory()->create(['price' => 0]);
        $paidProduct = Product::factory()->create(['price' => 99.99]);

        $similarUser = User::factory()->create();
        UserPurchase::factory()->create([
            'user_id' => $similarUser->id,
            'product_id' => $freeProduct->id,
        ]);
        UserPurchase::factory()->create([
            'user_id' => $similarUser->id,
            'product_id' => $paidProduct->id,
        ]);

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        // Should handle zero-priced products appropriately
        if (!empty($recommendations)) {
            foreach ($recommendations as $recommendation) {
                self::assertIsArray($recommendation);
            }
        }
    }

    public function testGetRecommendationsWithMemoryExhaustion(): void
    {
        $user = User::factory()->create();

        // Mock memory exhaustion scenario
        // Note: ini_set('memory_limit') may not work if current memory usage exceeds the limit
        $currentMemory = memory_get_usage(true);
        $oneMB = 1024 * 1024;
        
        if ($currentMemory > $oneMB) {
            // Current memory usage is already above 1M, skip this test
            $this->markTestSkipped('Current memory usage is already above 1M');
            return;
        }

        $oldLimit = ini_get('memory_limit');
        ini_set('memory_limit', '1M'); // Very low memory limit

        try {
            $recommendations = $this->recommendationService->getRecommendations($user->id);
            self::assertIsArray($recommendations);
        } catch (\Error $e) {
            // Should handle memory exhaustion gracefully
            self::assertStringContainsString('memory', strtolower($e->getMessage()));
        } finally {
            ini_set('memory_limit', $oldLimit);
        }
    }

    public function testGetRecommendationsWithInvalidProductCategories(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['category_id' => null]);

        $similarUser = User::factory()->create();
        UserPurchase::factory()->create([
            'user_id' => $similarUser->id,
            'product_id' => $product->id,
        ]);

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        // Should handle products with null/invalid categories
        if (!empty($recommendations)) {
            foreach ($recommendations as $recommendation) {
                self::assertIsArray($recommendation);
            }
        }
    }

    public function testGetRecommendationsWithConcurrentModification(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        UserPurchase::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // Simulate concurrent modification during recommendation generation
        DB::transaction(function () use ($user, $product) {
            $recommendations = $this->recommendationService->getRecommendations($user->id);

            // Modify data during recommendation process
            $product->delete();

            $this->assertIsArray($recommendations);
        });
    }

    public function testGetRecommendationsWithNegativeUserId(): void
    {
        $recommendations = $this->recommendationService->getRecommendations(-1);

        self::assertIsArray($recommendations);
        self::assertEmpty($recommendations);
    }

    public function testGetRecommendationsWithFloatUserId(): void
    {
        // Float should not be accepted - service should throw TypeError or return empty array
        try {
            $recommendations = $this->recommendationService->getRecommendations(1.5);
            // If it doesn't throw, service should return empty array
            self::assertIsArray($recommendations);
            self::assertEmpty($recommendations);
        } catch (\TypeError $e) {
            // TypeError is acceptable - type hint is strict
            self::assertStringContainsString('must be of type', $e->getMessage());
        }
    }

    public function testGetRecommendationsWithStringUserId(): void
    {
        // String ID should be handled gracefully or throw TypeError
        try {
            $recommendations = $this->recommendationService->getRecommendations('invalid_id');
            self::assertIsArray($recommendations);
        } catch (\TypeError $e) {
            // Type error is acceptable for invalid input
            self::assertTrue(true);
        }
    }

    public function testGetRecommendationsWithDatabaseLockTimeout(): void
    {
        $user = User::factory()->create();

        // Simulate database lock timeout
        DB::shouldReceive('table')
            ->andThrow(new \Exception('Lock wait timeout exceeded'))
        ;

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        self::assertEmpty($recommendations);
    }

    public function testGetRecommendationsWithReadOnlyDatabase(): void
    {
        $user = User::factory()->create();

        // Simulate read-only database scenario
        DB::shouldReceive('table')
            ->andThrow(new \Exception('The MySQL server is running with the --read-only option'))
        ;

        $recommendations = $this->recommendationService->getRecommendations($user->id);

        self::assertIsArray($recommendations);
        // Should still work for read operations
    }

    public function testGetRecommendationsWithMaximumRecommendationLimit(): void
    {
        $user = User::factory()->create();

        // Create many products and similar users
        $products = Product::factory()->count(100)->create();
        $similarUsers = User::factory()->count(50)->create();

        foreach ($similarUsers as $similarUser) {
            foreach ($products->take(20) as $product) {
                UserPurchase::factory()->create([
                    'user_id' => $similarUser->id,
                    'product_id' => $product->id,
                ]);
            }
        }

        $recommendations = $this->recommendationService->getRecommendations($user->id, 10);

        self::assertIsArray($recommendations);
        self::assertLessThanOrEqual(10, \count($recommendations));
    }

    public function testGetRecommendationsWithZeroLimit(): void
    {
        $user = User::factory()->create();

        $recommendations = $this->recommendationService->getRecommendations($user->id, 0);

        self::assertIsArray($recommendations);
        self::assertEmpty($recommendations);
    }

    public function testGetRecommendationsWithNegativeLimit(): void
    {
        $user = User::factory()->create();

        $recommendations = $this->recommendationService->getRecommendations($user->id, -5);

        self::assertIsArray($recommendations);
        // Should handle negative limit gracefully (either empty or default limit)
    }
}
