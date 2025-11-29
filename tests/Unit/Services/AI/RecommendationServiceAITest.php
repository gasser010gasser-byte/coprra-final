<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Repositories\RecommendationRepository;
use App\Services\RecommendationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RecommendationServiceAITest extends TestCase
{
    use RefreshDatabase;

    private RecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $repository = new RecommendationRepository();
        $this->service = new RecommendationService($repository);
    }

    // Collaborative Filtering Algorithm Tests

    public function testCollaborativeFilteringWithSimilarUsers(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $product1 = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id]);
        $product2 = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id]);
        $product3 = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id]);

        // Create similar purchase patterns for user1 and user2
        $this->createOrderWithProducts($user1, [$product1, $product2]);
        $this->createOrderWithProducts($user2, [$product1, $product2, $product3]);

        // Clear cache to ensure fresh recommendations
        Cache::flush();

        // Act
        $recommendations = $this->service->getRecommendations($user1, 5);

        // Assert
        self::assertIsArray($recommendations);
        self::assertNotEmpty($recommendations);

        // Should recommend product3 since similar user (user2) bought it
        $recommendedIds = array_column($recommendations, 'id');
        self::assertContains($product3->id, $recommendedIds);
    }

    public function testCollaborativeFilteringWithNoSimilarUsers(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        // User has unique purchase pattern
        $this->createOrderWithProducts($user, [$product]);

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);
        // Should fall back to content-based or trending recommendations
    }

    public function testCollaborativeFilteringWithLargeUserBase(): void
    {
        // Arrange
        $targetUser = User::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        // Create 100 users with various purchase patterns
        $products = Product::factory()->count(20)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        $commonProduct = $products->first();
        $this->createOrderWithProducts($targetUser, [$commonProduct]);

        // Create similar users
        for ($i = 0; $i < 50; ++$i) {
            $user = User::factory()->create();
            $userProducts = $products->random(rand(2, 5))->toArray();
            $userProducts[] = $commonProduct; // Ensure similarity
            $this->createOrderWithProducts($user, $userProducts);
        }

        // Act
        $recommendations = $this->service->getRecommendations($targetUser, 10);

        // Assert
        self::assertIsArray($recommendations);
        self::assertLessThanOrEqual(10, \count($recommendations));

        // Should not recommend already purchased products
        $recommendedIds = array_column($recommendations, 'id');
        self::assertNotContains($commonProduct->id, $recommendedIds);
    }

    // Content-Based Filtering Algorithm Tests

    public function testContentBasedRecommendationsWithCategoryPreference(): void
    {
        // Arrange
        $user = User::factory()->create();
        $preferredCategory = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $brand = Brand::factory()->create();

        // User has strong preference for specific category
        $purchasedProducts = Product::factory()->count(5)->create([
            'category_id' => $preferredCategory->id,
            'brand_id' => $brand->id,
        ]);

        $this->createOrderWithProducts($user, $purchasedProducts->toArray());

        // Create products in preferred category (should be recommended)
        $recommendableProducts = Product::factory()->count(3)->create([
            'category_id' => $preferredCategory->id,
            'brand_id' => $brand->id,
            'rating' => 4.5,
        ]);

        // Create products in other category (should not be recommended)
        Product::factory()->count(3)->create([
            'category_id' => $otherCategory->id,
            'brand_id' => $brand->id,
            'rating' => 4.8,
        ]);

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);
        $recommendedIds = array_column($recommendations, 'id');

        // Should recommend products from preferred category
        foreach ($recommendableProducts as $product) {
            self::assertContains($product->id, $recommendedIds);
        }
    }

    public function testContentBasedRecommendationsWithBrandPreference(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $preferredBrand = Brand::factory()->create();
        $otherBrand = Brand::factory()->create();

        // User has strong brand preference
        $purchasedProducts = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'brand_id' => $preferredBrand->id,
        ]);

        $this->createOrderWithProducts($user, $purchasedProducts->toArray());

        // Create products from preferred brand
        $recommendableProducts = Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'brand_id' => $preferredBrand->id,
            'rating' => 4.0,
        ]);

        // Create products from other brand
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'brand_id' => $otherBrand->id,
            'rating' => 4.9,
        ]);

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);
        $recommendedIds = array_column($recommendations, 'id');

        // Should prioritize preferred brand
        foreach ($recommendableProducts as $product) {
            self::assertContains($product->id, $recommendedIds);
        }
    }

    public function testContentBasedRecommendationsWithPriceRangePreference(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        // User purchases products in $50-$100 range
        $purchasedProducts = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'price' => rand(50, 100),
        ]);

        $this->createOrderWithProducts($user, $purchasedProducts->toArray());

        // Create products in user's price range
        $affordableProducts = Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'price' => rand(60, 90),
        ]);

        // Create expensive products outside range
        Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'price' => rand(200, 300),
        ]);

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);
        $recommendedIds = array_column($recommendations, 'id');

        // Should recommend products in user's price range
        foreach ($affordableProducts as $product) {
            self::assertContains($product->id, $recommendedIds);
        }
    }

    // Machine Learning Algorithm Edge Cases

    public function testRecommendationAlgorithmWithColdStartProblem(): void
    {
        // Arrange - New user with no purchase history
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        // Create trending products
        $trendingProducts = Product::factory()->count(5)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'rating' => 4.5,
        ]);

        // Simulate recent purchases for trending
        foreach ($trendingProducts as $product) {
            $otherUser = User::factory()->create();
            $this->createOrderWithProducts($otherUser, [$product], now()->subDays(2));
        }

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);
        self::assertNotEmpty($recommendations);

        // Should fall back to trending recommendations for new users
        $recommendedIds = array_column($recommendations, 'id');
        foreach ($trendingProducts as $product) {
            self::assertContains($product->id, $recommendedIds);
        }
    }

    public function testRecommendationAlgorithmWithDataSparsity(): void
    {
        // Arrange - User with very limited purchase history
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        // User bought only one product
        $singleProduct = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        $this->createOrderWithProducts($user, [$singleProduct]);

        // Create similar products
        $similarProducts = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);
        self::assertNotEmpty($recommendations);

        // Should still provide recommendations despite sparse data
        $recommendedIds = array_column($recommendations, 'id');
        self::assertNotContains($singleProduct->id, $recommendedIds);
    }

    public function testRecommendationAlgorithmWithBiasedData(): void
    {
        // Arrange - Simulate biased data where one product is overly popular
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $popularProduct = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'rating' => 3.0, // Lower rating but very popular
        ]);

        $qualityProduct = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'rating' => 4.8, // High rating but less popular
        ]);

        // Create bias: 90% of users bought the popular product
        for ($i = 0; $i < 50; ++$i) {
            $otherUser = User::factory()->create();
            $this->createOrderWithProducts($otherUser, [$popularProduct]);
        }

        // Only 5 users bought the quality product
        for ($i = 0; $i < 5; ++$i) {
            $otherUser = User::factory()->create();
            $this->createOrderWithProducts($otherUser, [$qualityProduct]);
        }

        // Target user bought the popular product
        $this->createOrderWithProducts($user, [$popularProduct]);

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);

        // Algorithm should handle bias and potentially recommend quality product
        $recommendedIds = array_column($recommendations, 'id');
        self::assertNotContains($popularProduct->id, $recommendedIds); // Already purchased
    }

    // Recommendation Quality and Diversity Tests

    public function testRecommendationDiversityAcrossCategories(): void
    {
        // Arrange
        $user = User::factory()->create();
        $categories = Category::factory()->count(3)->create();
        $brand = Brand::factory()->create();

        // User has diverse purchase history
        foreach ($categories as $category) {
            $product = Product::factory()->create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
            ]);
            $this->createOrderWithProducts($user, [$product]);
        }

        // Create recommendable products in each category
        foreach ($categories as $category) {
            Product::factory()->count(2)->create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
            ]);
        }

        // Act
        $recommendations = $this->service->getRecommendations($user, 6);

        // Assert
        self::assertIsArray($recommendations);

        // Should provide diverse recommendations across categories
        $recommendedCategories = array_unique(array_column($recommendations, 'category_id'));
        self::assertGreaterThan(1, \count($recommendedCategories));
    }

    public function testRecommendationQualityWithRatingBias(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        // User bought high-rated products
        $highRatedProducts = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'rating' => 4.5,
        ]);

        $this->createOrderWithProducts($user, $highRatedProducts->toArray());

        // Create mix of high and low rated products
        $highRatedRecommendable = Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'rating' => 4.8,
        ]);

        $lowRatedProducts = Product::factory()->count(2)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'rating' => 2.0,
        ]);

        // Act
        $recommendations = $this->service->getRecommendations($user, 4);

        // Assert
        self::assertIsArray($recommendations);

        // Should prioritize high-rated products
        $recommendedIds = array_column($recommendations, 'id');
        foreach ($highRatedRecommendable as $product) {
            self::assertContains($product->id, $recommendedIds);
        }

        // Should avoid low-rated products
        foreach ($lowRatedProducts as $product) {
            self::assertNotContains($product->id, $recommendedIds);
        }
    }

    // Performance and Scalability Tests

    public function testRecommendationCachingMechanism(): void
    {
        // Arrange
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->createOrderWithProducts($user, [$product]);

        Cache::shouldReceive('remember')
            ->once()
            ->with("recommendations_user_{$user->id}", 3600, \Mockery::type('callable'))
            ->andReturn([])
        ;

        // Act
        $recommendations = $this->service->getRecommendations($user, 5);

        // Assert
        self::assertIsArray($recommendations);
    }

    public function testRecommendationPerformanceWithLargeDataset(): void
    {
        // Arrange
        $user = User::factory()->create();
        $categories = Category::factory()->count(10)->create();
        $brands = Brand::factory()->count(5)->create();

        // Create large dataset
        $products = [];
        foreach ($categories as $category) {
            foreach ($brands as $brand) {
                $products = array_merge($products, Product::factory()->count(20)->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                ])->toArray());
            }
        }

        // User has diverse purchase history
        $purchasedProducts = \array_slice($products, 0, 50);
        $this->createOrderWithProducts($user, $purchasedProducts);

        // Act
        $startTime = microtime(true);
        $recommendations = $this->service->getRecommendations($user, 10);
        $endTime = microtime(true);

        // Assert
        self::assertIsArray($recommendations);
        self::assertLessThanOrEqual(10, \count($recommendations));

        // Performance should be reasonable (less than 2 seconds)
        $executionTime = $endTime - $startTime;
        self::assertLessThan(2.0, $executionTime);
    }

    // Algorithm Accuracy Tests

    public function testSimilarProductsAccuracy(): void
    {
        // Arrange
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $otherCategory = Category::factory()->create();

        $targetProduct = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Create similar products (same category)
        $similarProducts = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'rating' => 4.5,
        ]);

        // Create dissimilar products (different category)
        Product::factory()->count(2)->create([
            'category_id' => $otherCategory->id,
            'brand_id' => $brand->id,
            'rating' => 4.8,
        ]);

        // Act
        $similar = $this->service->getSimilarProducts($targetProduct, 5);

        // Assert
        self::assertIsArray($similar);
        self::assertCount(3, $similar);

        // All similar products should be from same category
        foreach ($similar as $product) {
            self::assertSame($category->id, $product->category_id);
            self::assertNotSame($targetProduct->id, $product->id);
        }
    }

    public function testFrequentlyBoughtTogetherAccuracy(): void
    {
        // Arrange
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $mainProduct = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        $complementaryProduct1 = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        $complementaryProduct2 = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        $unrelatedProduct = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Create purchase patterns
        for ($i = 0; $i < 10; ++$i) {
            $user = User::factory()->create();
            $this->createOrderWithProducts($user, [$mainProduct, $complementaryProduct1]);
        }

        for ($i = 0; $i < 8; ++$i) {
            $user = User::factory()->create();
            $this->createOrderWithProducts($user, [$mainProduct, $complementaryProduct2]);
        }

        // Only 2 users bought unrelated product with main product
        for ($i = 0; $i < 2; ++$i) {
            $user = User::factory()->create();
            $this->createOrderWithProducts($user, [$mainProduct, $unrelatedProduct]);
        }

        // Act
        $frequentlyBought = $this->service->getFrequentlyBoughtTogether($mainProduct, 5);

        // Assert
        self::assertIsArray($frequentlyBought);

        $frequentlyBoughtIds = array_column($frequentlyBought, 'id');

        // Should include frequently bought products
        self::assertContains($complementaryProduct1->id, $frequentlyBoughtIds);
        self::assertContains($complementaryProduct2->id, $frequentlyBoughtIds);

        // Should not include main product itself
        self::assertNotContains($mainProduct->id, $frequentlyBoughtIds);
    }

    // Helper Methods

    private function createOrderWithProducts(User $user, array $products, ?Carbon $createdAt = null): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'created_at' => $createdAt ?? now(),
        ]);

        foreach ($products as $product) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $product->id ?? $product['id'],
                'quantity' => rand(1, 3),
                'unit_price' => $product->price ?? $product['price'] ?? rand(10, 100),
            ]);
        }

        return $order;
    }
}
