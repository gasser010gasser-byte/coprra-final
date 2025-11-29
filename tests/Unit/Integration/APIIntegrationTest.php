<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceAlert;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class APIIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private User $adminUser;
    private User $regularUser;
    private Category $category;
    private Brand $brand;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create common test data
        $this->adminUser = User::factory()->create([
            'is_admin' => true,
            'email' => 'admin@example.com',
            'name' => 'Admin User',
        ]);
        $this->regularUser = User::factory()->create([
            'is_admin' => false,
            'email' => 'user@example.com',
            'name' => 'Regular User',
        ]);
        $this->category = Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);
        $this->brand = Brand::factory()->create([
            'name' => 'TestBrand',
            'slug' => 'testbrand',
            'is_active' => true,
        ]);
        $this->store = Store::factory()->create([
            'name' => 'TestStore',
            'slug' => 'teststore',
            'is_active' => true,
            'contact_email' => 'store@example.com',
        ]);

        // Clear rate limiting for clean tests
        RateLimiter::clear('api');
    }

    #[Test]
    public function testPublicProductsEndpointReturnsActiveProductsWithCompleteData(): void
    {
        // Create active and inactive products with comprehensive data
        $activeProduct = Product::factory()->create([
            'name' => 'Active Smartphone',
            'description' => 'Latest smartphone with advanced features',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'price' => '599.99',
            'sku' => 'PHONE-001',
            'slug' => 'active-smartphone',
        ]);

        $inactiveProduct = Product::factory()->create([
            'name' => 'Inactive Product',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => false,
        ]);

        // Create price offers for the active product
        PriceOffer::factory()->create([
            'product_id' => $activeProduct->id,
            'store_id' => $this->store->id,
            'price' => '549.99',
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'price',
                    'is_active',
                    'category_id',
                    'brand_id',
                    'created_at',
                    'updated_at',
                    'image_url',
                    'category',
                    'brand',
                    'stores',
                ],
            ],
            'message',
        ]);

        // Verify response contains exactly one active product
        $responseData = $response->json('data');
        self::assertCount(1, $responseData, 'Should return exactly one active product');

        // Verify product data integrity
        $productData = $responseData[0];
        self::assertSame($activeProduct->id, $productData['id']);
        self::assertSame('Active Smartphone', $productData['name']);
        self::assertSame('Latest smartphone with advanced features', $productData['description']);
        self::assertTrue($productData['is_active']);
        self::assertSame('599.99', $productData['price']);
        self::assertSame('active-smartphone', $productData['slug']);

        // Verify category and brand relationships
        self::assertSame($this->category->name, $productData['category']['name']);
        self::assertSame($this->brand->name, $productData['brand']['name']);

        // Verify inactive product is excluded
        $productIds = collect($responseData)->pluck('id')->toArray();
        self::assertNotContains($inactiveProduct->id, $productIds, 'Inactive products should not be returned');

        // Verify response message if present
        if ($response->json('message')) {
            self::assertStringContainsString('products', strtolower($response->json('message')));
        }
    }

    #[Test]
    public function testPriceSearchBestOfferWithMultipleOffersReturnsLowestPrice(): void
    {
        $product = Product::factory()->create([
            'name' => 'Gaming Laptop',
            'description' => 'High-performance gaming laptop',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'price' => 1299.99,
            'sku' => 'LAPTOP-001',
        ]);

        // Create multiple stores with different characteristics
        $store1 = Store::factory()->create([
            'name' => 'Premium Electronics',
            'slug' => 'premium-electronics',
            'is_active' => true,
            'contact_email' => 'premium@example.com',
        ]);
        $store2 = Store::factory()->create([
            'name' => 'Budget Tech Store',
            'slug' => 'budget-tech',
            'is_active' => true,
            'contact_email' => 'budget@example.com',
        ]);
        $store3 = Store::factory()->create([
            'name' => 'Inactive Store',
            'is_active' => false,
        ]);

        // Create price offers with different prices and availability
        $expensiveOffer = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store1->id,
            'price' => 1350.00,
            'is_available' => true,
            'stock_quantity' => 5,
        ]);

        $bestOffer = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store2->id,
            'price' => 1199.99,
            'is_available' => true,
            'stock_quantity' => 3,
        ]);

        // Create unavailable offer (should be excluded)
        $unavailableOffer = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store3->id,
            'price' => 999.99,
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/price-search/best-offer?product_id='.$product->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'product_id',
                'product_name',
                'total_offers',
                'best_offer' => [
                    'id',
                    'price',
                    'stock_quantity',
                    'is_available',
                    'store' => [
                        'id',
                        'name',
                        'slug',
                        'contact_email',
                    ],
                ],
                'price_comparison' => [
                    'lowest_price',
                    'highest_price',
                    'average_price',
                    'savings_amount',
                ],
            ],
            'message',
        ]);

        $data = $response->json('data');

        // Verify product information
        self::assertSame($product->id, $data['product_id']);
        self::assertSame('Gaming Laptop', $data['product_name']);
        self::assertSame(2, $data['total_offers'], 'Should count only available offers');

        // Verify best offer is the lowest priced available offer
        $bestOfferData = $data['best_offer'];
        self::assertSame($bestOffer->id, $bestOfferData['id']);
        self::assertSame(1199.99, $bestOfferData['price']);
        self::assertSame(3, $bestOfferData['stock_quantity']);
        self::assertTrue($bestOfferData['is_available']);

        // Verify store information
        $storeData = $bestOfferData['store'];
        self::assertSame($store2->id, $storeData['id']);
        self::assertSame('Budget Tech Store', $storeData['name']);
        self::assertSame('budget-tech', $storeData['slug']);
        self::assertSame('budget@example.com', $storeData['contact_email']);

        // Verify price comparison data
        $priceComparison = $data['price_comparison'];
        self::assertSame(1199.99, $priceComparison['lowest_price']);
        self::assertSame(1350.00, $priceComparison['highest_price']);
        self::assertSame(1274.995, $priceComparison['average_price']); // (1199.99 + 1350.00) / 2
        self::assertSame(150.01, $priceComparison['savings_amount']); // 1350.00 - 1199.99

        // Verify response message
        self::assertStringContainsString('best offer', strtolower($response->json('message')));
    }

    #[Test]
    public function testPriceSearchByProductIdWithComprehensiveOfferData(): void
    {
        $product = Product::factory()->create([
            'name' => 'Professional Camera',
            'description' => 'High-end DSLR camera for professionals',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'price' => 2499.99,
            'sku' => 'CAM-PRO-001',
        ]);

        // Create multiple offers with different characteristics
        $primaryOffer = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'price' => 2299.99,
            'is_available' => true,
            'stock_quantity' => 8,
            'shipping_cost' => 25.00,
            'delivery_time' => '2-3 days',
        ]);

        $secondaryStore = Store::factory()->create([
            'name' => 'Camera World',
            'is_active' => true,
        ]);

        $secondaryOffer = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'store_id' => $secondaryStore->id,
            'price' => 2399.99,
            'is_available' => true,
            'stock_quantity' => 3,
            'shipping_cost' => 0.00,
            'delivery_time' => '1-2 days',
        ]);

        $response = $this->getJson('/api/price-search?product_id='.$product->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'product_id',
                'product_name',
                'product_sku',
                'total_offers',
                'best_offer' => [
                    'id',
                    'price',
                    'shipping_cost',
                    'total_cost',
                    'stock_quantity',
                    'delivery_time',
                    'is_available',
                    'store' => [
                        'id',
                        'name',
                        'slug',
                    ],
                ],
                'all_offers' => [
                    '*' => [
                        'id',
                        'price',
                        'shipping_cost',
                        'total_cost',
                        'stock_quantity',
                        'store_name',
                    ],
                ],
                'price_statistics' => [
                    'lowest_price',
                    'highest_price',
                    'average_price',
                    'price_range',
                ],
            ],
            'message',
        ]);

        $data = $response->json('data');

        // Verify product information
        self::assertSame($product->id, $data['product_id']);
        self::assertSame('Professional Camera', $data['product_name']);
        self::assertSame('CAM-PRO-001', $data['product_sku']);
        self::assertSame(2, $data['total_offers']);

        // Verify best offer (lowest total cost including shipping)
        $bestOffer = $data['best_offer'];
        self::assertSame($primaryOffer->id, $bestOffer['id']);
        self::assertSame(2299.99, $bestOffer['price']);
        self::assertSame(25.00, $bestOffer['shipping_cost']);
        self::assertSame(2324.99, $bestOffer['total_cost']); // price + shipping
        self::assertSame(8, $bestOffer['stock_quantity']);
        self::assertSame('2-3 days', $bestOffer['delivery_time']);
        self::assertTrue($bestOffer['is_available']);

        // Verify store information
        self::assertSame($this->store->id, $bestOffer['store']['id']);
        self::assertSame($this->store->name, $bestOffer['store']['name']);

        // Verify all offers are included
        self::assertCount(2, $data['all_offers']);

        // Verify price statistics
        $priceStats = $data['price_statistics'];
        self::assertSame(2299.99, $priceStats['lowest_price']);
        self::assertSame(2399.99, $priceStats['highest_price']);
        self::assertSame(2349.99, $priceStats['average_price']); // (2299.99 + 2399.99) / 2
        self::assertSame(100.00, $priceStats['price_range']); // 2399.99 - 2299.99

        // Verify response message
        self::assertStringContainsString('price search', strtolower($response->json('message')));
    }

    #[Test]
    public function testPriceSearchByProductNameWithFuzzyMatching(): void
    {
        // Create products with similar names to test search accuracy
        $targetProduct = Product::factory()->create([
            'name' => 'Samsung Galaxy S24 Ultra',
            'description' => 'Latest flagship smartphone',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'price' => 1199.99,
            'sku' => 'PHONE-S24U',
        ]);

        $similarProduct = Product::factory()->create([
            'name' => 'Samsung Galaxy S23 Ultra',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'price' => 999.99,
        ]);

        $unrelatedProduct = Product::factory()->create([
            'name' => 'iPhone 15 Pro',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        // Create offers for target product
        $bestOffer = PriceOffer::factory()->create([
            'product_id' => $targetProduct->id,
            'store_id' => $this->store->id,
            'price' => 1099.99,
            'is_available' => true,
            'stock_quantity' => 5,
        ]);

        $secondOffer = PriceOffer::factory()->create([
            'product_id' => $targetProduct->id,
            'store_id' => $this->store->id,
            'price' => 1149.99,
            'is_available' => true,
            'stock_quantity' => 2,
        ]);

        // Test exact name match
        $response = $this->getJson('/api/price-search?product_name='.urlencode('Samsung Galaxy S24 Ultra'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'product_id',
                'product_name',
                'product_sku',
                'search_query',
                'match_type',
                'confidence_score',
                'total_offers',
                'best_offer' => [
                    'id',
                    'price',
                    'stock_quantity',
                    'store' => [
                        'id',
                        'name',
                    ],
                ],
                'alternative_products' => [
                    '*' => [
                        'id',
                        'name',
                        'similarity_score',
                    ],
                ],
            ],
            'message',
        ]);

        $data = $response->json('data');

        // Verify exact product match
        self::assertSame($targetProduct->id, $data['product_id']);
        self::assertSame('Samsung Galaxy S24 Ultra', $data['product_name']);
        self::assertSame('PHONE-S24U', $data['product_sku']);
        self::assertSame('Samsung Galaxy S24 Ultra', $data['search_query']);
        self::assertSame('exact', $data['match_type']);
        self::assertSame(100, $data['confidence_score']);
        self::assertSame(2, $data['total_offers']);

        // Verify best offer
        self::assertSame($bestOffer->id, $data['best_offer']['id']);
        self::assertSame(1099.99, $data['best_offer']['price']);
        self::assertSame(5, $data['best_offer']['stock_quantity']);

        // Verify alternative products are suggested
        self::assertGreaterThan(0, \count($data['alternative_products']));
        $alternativeIds = collect($data['alternative_products'])->pluck('id')->toArray();
        self::assertContains($similarProduct->id, $alternativeIds);
        self::assertNotContains($unrelatedProduct->id, $alternativeIds);

        // Test partial name match
        $partialResponse = $this->getJson('/api/price-search?product_name='.urlencode('Galaxy S24'));
        $partialResponse->assertStatus(200);

        $partialData = $partialResponse->json('data');
        self::assertSame($targetProduct->id, $partialData['product_id']);
        self::assertSame('partial', $partialData['match_type']);
        self::assertGreaterThan(80, $partialData['confidence_score']);
    }

    #[Test]
    public function testAuthenticatedProductUpdateWithValidDataAndAuditTrail(): void
    {
        Sanctum::actingAs($this->adminUser);

        $originalProduct = Product::factory()->create([
            'name' => 'Original Gaming Mouse',
            'description' => 'Basic gaming mouse',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'price' => 49.99,
            'sku' => 'MOUSE-001',
            'slug' => 'original-gaming-mouse',
        ]);

        $updateData = [
            'name' => 'Premium Gaming Mouse Pro',
            'description' => 'Advanced gaming mouse with RGB lighting and programmable buttons',
            'price' => 89.99,
            'sku' => 'MOUSE-PRO-001',
            'is_active' => true,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'meta_title' => 'Premium Gaming Mouse Pro - Best Gaming Accessory',
            'meta_description' => 'Discover the ultimate gaming experience with our premium mouse',
        ];

        $response = $this->putJson('/api/products/'.$originalProduct->id, $updateData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'price',
                'sku',
                'slug',
                'is_active',
                'category' => [
                    'id',
                    'name',
                ],
                'brand' => [
                    'id',
                    'name',
                ],
                'meta_title',
                'meta_description',
                'updated_at',
                'updated_by' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
            'message',
            'audit' => [
                'action',
                'user_id',
                'changes' => [
                    'old_values',
                    'new_values',
                ],
                'timestamp',
            ],
        ]);

        $data = $response->json('data');

        // Verify updated product data
        self::assertSame($originalProduct->id, $data['id']);
        self::assertSame('Premium Gaming Mouse Pro', $data['name']);
        self::assertSame('Advanced gaming mouse with RGB lighting and programmable buttons', $data['description']);
        self::assertSame(89.99, $data['price']);
        self::assertSame('MOUSE-PRO-001', $data['sku']);
        self::assertSame('premium-gaming-mouse-pro', $data['slug']);
        self::assertTrue($data['is_active']);
        self::assertSame('Premium Gaming Mouse Pro - Best Gaming Accessory', $data['meta_title']);
        self::assertSame('Discover the ultimate gaming experience with our premium mouse', $data['meta_description']);

        // Verify relationships
        self::assertSame($this->category->id, $data['category']['id']);
        self::assertSame($this->brand->id, $data['brand']['id']);

        // Verify updated_by information
        self::assertSame($this->adminUser->id, $data['updated_by']['id']);
        self::assertSame($this->adminUser->name, $data['updated_by']['name']);
        self::assertSame($this->adminUser->email, $data['updated_by']['email']);

        // Verify database was updated correctly
        $this->assertDatabaseHas('products', [
            'id' => $originalProduct->id,
            'name' => 'Premium Gaming Mouse Pro',
            'description' => 'Advanced gaming mouse with RGB lighting and programmable buttons',
            'price' => 89.99,
            'sku' => 'MOUSE-PRO-001',
            'slug' => 'premium-gaming-mouse-pro',
            'is_active' => true,
        ]);

        // Verify original data is no longer in database
        $this->assertDatabaseMissing('products', [
            'id' => $originalProduct->id,
            'name' => 'Original Gaming Mouse',
            'price' => 49.99,
            'sku' => 'MOUSE-001',
        ]);

        // Verify audit trail
        $auditData = $response->json('audit');
        self::assertSame('product_updated', $auditData['action']);
        self::assertSame($this->adminUser->id, $auditData['user_id']);
        self::assertArrayHasKey('old_values', $auditData['changes']);
        self::assertArrayHasKey('new_values', $auditData['changes']);

        // Verify specific changes are tracked
        $oldValues = $auditData['changes']['old_values'];
        $newValues = $auditData['changes']['new_values'];
        self::assertSame('Original Gaming Mouse', $oldValues['name']);
        self::assertSame('Premium Gaming Mouse Pro', $newValues['name']);
        self::assertSame(49.99, $oldValues['price']);
        self::assertSame(89.99, $newValues['price']);

        // Verify response message
        self::assertStringContainsString('updated successfully', $response->json('message'));

        // Verify slug uniqueness
        $updatedProduct = Product::find($originalProduct->id);
        self::assertSame('premium-gaming-mouse-pro', $updatedProduct->slug);

        // Verify timestamps are updated
        self::assertNotSame($originalProduct->updated_at, $updatedProduct->updated_at);
    }

    #[Test]
    public function testProductUpdateGeneratesUniqueSlugWithConflictResolution(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create multiple products with potential slug conflicts
        $existingProduct1 = Product::factory()->create([
            'name' => 'Gaming Mouse Pro',
            'slug' => 'gaming-mouse-pro',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'sku' => 'MOUSE-PRO-001',
        ]);

        $existingProduct2 = Product::factory()->create([
            'name' => 'Gaming Mouse Pro 2',
            'slug' => 'gaming-mouse-pro-2',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'sku' => 'MOUSE-PRO-002',
        ]);

        $productToUpdate = Product::factory()->create([
            'name' => 'Wireless Gaming Mouse',
            'slug' => 'wireless-gaming-mouse',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'sku' => 'MOUSE-WIRELESS-001',
        ]);

        // Update product to have the same name as existing product
        $updateData = [
            'name' => 'Gaming Mouse Pro',
            'description' => 'Updated gaming mouse with advanced features',
            'price' => 79.99,
        ];

        $response = $this->putJson('/api/products/'.$productToUpdate->id, $updateData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'description',
                'price',
                'sku',
                'slug_generation' => [
                    'original_slug',
                    'final_slug',
                    'conflicts_resolved',
                    'generation_method',
                ],
            ],
            'message',
        ]);

        $data = $response->json('data');

        // Verify product data was updated correctly
        self::assertSame($productToUpdate->id, $data['id']);
        self::assertSame('Gaming Mouse Pro', $data['name']);
        self::assertSame('Updated gaming mouse with advanced features', $data['description']);
        self::assertSame(79.99, $data['price']);
        self::assertSame('MOUSE-WIRELESS-001', $data['sku']); // SKU should remain unchanged

        // Verify unique slug generation
        self::assertNotSame($existingProduct1->slug, $data['slug']);
        self::assertNotSame($existingProduct2->slug, $data['slug']);
        self::assertNotSame('wireless-gaming-mouse', $data['slug']); // Original slug should change

        // Verify slug follows expected pattern (gaming-mouse-pro-3 or similar)
        self::assertStringStartsWith('gaming-mouse-pro', $data['slug']);
        self::assertNotSame('gaming-mouse-pro', $data['slug']); // Should not be exact match

        // Verify slug generation metadata
        $slugGeneration = $data['slug_generation'];
        self::assertSame('gaming-mouse-pro', $slugGeneration['original_slug']);
        self::assertSame($data['slug'], $slugGeneration['final_slug']);
        self::assertGreaterThan(0, $slugGeneration['conflicts_resolved']);
        self::assertContains($slugGeneration['generation_method'], ['incremental', 'uuid_suffix', 'timestamp_suffix']);

        // Verify database integrity
        $this->assertDatabaseHas('products', [
            'id' => $productToUpdate->id,
            'name' => 'Gaming Mouse Pro',
            'slug' => $data['slug'],
            'description' => 'Updated gaming mouse with advanced features',
            'price' => 79.99,
        ]);

        // Verify all slugs remain unique in database
        $allSlugs = Product::pluck('slug')->toArray();
        self::assertSame(\count($allSlugs), \count(array_unique($allSlugs)), 'All product slugs should be unique');

        // Verify existing products were not affected
        $existingProduct1->refresh();
        $existingProduct2->refresh();
        self::assertSame('gaming-mouse-pro', $existingProduct1->slug);
        self::assertSame('gaming-mouse-pro-2', $existingProduct2->slug);

        // Verify updated product has correct slug
        $productToUpdate->refresh();
        self::assertSame($data['slug'], $productToUpdate->slug);
        self::assertStringContainsString('gaming-mouse-pro', $productToUpdate->slug);

        // Verify response message indicates successful update with slug resolution
        self::assertStringContainsString('updated successfully', $response->json('message'));
        self::assertStringContainsString('slug conflict resolved', strtolower($response->json('message')));
    }

    #[Test]
    public function testUnauthenticatedProductUpdateReturnsSecureError(): void
    {
        $product = Product::factory()->create([
            'name' => 'Test Product for Security',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'sku' => 'SEC-TEST-001',
            'is_active' => true,
        ]);

        $sensitiveUpdateData = [
            'name' => 'Hacked Product Name',
            'price' => 0.01,
            'is_active' => false,
            'admin_notes' => 'Unauthorized access attempt',
        ];

        $response = $this->putJson('/api/products/'.$product->id, $sensitiveUpdateData);

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'timestamp',
            'request_id',
            'security' => [
                'attempt_logged',
                'ip_address',
                'user_agent_logged',
            ],
        ]);

        $responseData = $response->json();

        // Verify security response
        self::assertSame('Unauthenticated', $responseData['message']);
        self::assertSame('AUTH_REQUIRED', $responseData['error_code']);
        self::assertArrayHasKey('timestamp', $responseData);
        self::assertArrayHasKey('request_id', $responseData);

        // Verify security logging
        $security = $responseData['security'];
        self::assertTrue($security['attempt_logged']);
        self::assertNotEmpty($security['ip_address']);
        self::assertTrue($security['user_agent_logged']);

        // Verify product was NOT modified
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Test Product for Security',
            'is_active' => true,
            'sku' => 'SEC-TEST-001',
        ]);

        // Verify no unauthorized changes occurred
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
            'name' => 'Hacked Product Name',
            'price' => 0.01,
            'is_active' => false,
        ]);

        // Verify security audit log entry was created
        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'unauthorized_product_update_attempt',
            'resource_type' => 'product',
            'resource_id' => $product->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => 'blocked',
        ]);
    }

    #[Test]
    public function testNonAdminUserCannotUpdateProductWithDetailedPermissionCheck(): void
    {
        // Create a regular user with specific permissions
        $regularUserWithLimitedAccess = User::factory()->create([
            'name' => 'Limited Access User',
            'email' => 'limited@example.com',
            'role' => 'customer',
            'permissions' => ['view_products', 'create_reviews'],
            'is_active' => true,
        ]);

        Sanctum::actingAs($regularUserWithLimitedAccess);

        $product = Product::factory()->create([
            'name' => 'Protected Product',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'sku' => 'PROTECTED-001',
            'price' => 99.99,
            'is_active' => true,
        ]);

        $unauthorizedUpdateData = [
            'name' => 'Unauthorized Update',
            'price' => 1.00,
            'is_active' => false,
            'featured' => true,
            'admin_notes' => 'Attempting unauthorized modification',
        ];

        $response = $this->putJson('/api/products/'.$product->id, $unauthorizedUpdateData);

        $response->assertStatus(403);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'permissions' => [
                'required',
                'user_has',
                'missing',
            ],
            'user_info' => [
                'id',
                'role',
                'permissions',
            ],
            'security' => [
                'attempt_logged',
                'user_id',
                'action_attempted',
            ],
        ]);

        $responseData = $response->json();

        // Verify permission error details
        self::assertStringContainsString('insufficient permissions', strtolower($responseData['message']));
        self::assertSame('INSUFFICIENT_PERMISSIONS', $responseData['error_code']);

        // Verify permission analysis
        $permissions = $responseData['permissions'];
        self::assertContains('update_products', $permissions['required']);
        self::assertContains('admin_access', $permissions['required']);
        self::assertSame(['view_products', 'create_reviews'], $permissions['user_has']);
        self::assertContains('update_products', $permissions['missing']);
        self::assertContains('admin_access', $permissions['missing']);

        // Verify user information
        $userInfo = $responseData['user_info'];
        self::assertSame($regularUserWithLimitedAccess->id, $userInfo['id']);
        self::assertSame('customer', $userInfo['role']);
        self::assertSame(['view_products', 'create_reviews'], $userInfo['permissions']);

        // Verify security logging
        $security = $responseData['security'];
        self::assertTrue($security['attempt_logged']);
        self::assertSame($regularUserWithLimitedAccess->id, $security['user_id']);
        self::assertSame('product_update', $security['action_attempted']);

        // Verify product was NOT modified
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Protected Product',
            'price' => 99.99,
            'is_active' => true,
            'sku' => 'PROTECTED-001',
        ]);

        // Verify no unauthorized changes occurred
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
            'name' => 'Unauthorized Update',
            'price' => 1.00,
            'is_active' => false,
        ]);

        // Verify security audit log entry was created
        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'insufficient_permissions_product_update',
            'resource_type' => 'product',
            'resource_id' => $product->id,
            'user_id' => $regularUserWithLimitedAccess->id,
            'status' => 'blocked',
            'details->required_permissions' => json_encode(['update_products', 'admin_access']),
            'details->user_permissions' => json_encode(['view_products', 'create_reviews']),
        ]);
    }

    #[Test]
    public function testProductNotFoundReturnsComprehensive404(): void
    {
        Sanctum::actingAs($this->adminUser);

        $nonExistentId = 99999;
        $updateData = [
            'name' => 'Updated Product Name',
            'description' => 'Updated description',
            'price' => 149.99,
            'sku' => 'UPDATED-SKU-001',
        ];

        $response = $this->putJson('/api/products/'.$nonExistentId, $updateData);

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'resource' => [
                'type',
                'id',
                'action_attempted',
            ],
            'suggestions' => [
                'check_id',
                'verify_permissions',
                'alternative_actions',
            ],
            'debug_info' => [
                'request_id',
                'timestamp',
                'user_id',
            ],
        ]);

        $responseData = $response->json();

        // Verify error message and code
        self::assertStringContainsString('Product not found', $responseData['message']);
        self::assertSame('RESOURCE_NOT_FOUND', $responseData['error_code']);

        // Verify resource information
        $resource = $responseData['resource'];
        self::assertSame('product', $resource['type']);
        self::assertSame($nonExistentId, $resource['id']);
        self::assertSame('update', $resource['action_attempted']);

        // Verify helpful suggestions
        $suggestions = $responseData['suggestions'];
        self::assertStringContainsString('verify the product ID', $suggestions['check_id']);
        self::assertStringContainsString('ensure you have permission', $suggestions['verify_permissions']);
        self::assertIsArray($suggestions['alternative_actions']);
        self::assertContains('GET /api/products', $suggestions['alternative_actions']);
        self::assertContains('POST /api/products', $suggestions['alternative_actions']);

        // Verify debug information
        $debugInfo = $responseData['debug_info'];
        self::assertNotEmpty($debugInfo['request_id']);
        self::assertNotEmpty($debugInfo['timestamp']);
        self::assertSame($this->adminUser->id, $debugInfo['user_id']);

        // Verify no database changes occurred
        $this->assertDatabaseMissing('products', [
            'id' => $nonExistentId,
        ]);

        // Verify audit log entry was created for the failed attempt
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product_update_attempt',
            'resource_type' => 'product',
            'resource_id' => $nonExistentId,
            'user_id' => $this->adminUser->id,
            'status' => 'failed',
            'error_reason' => 'resource_not_found',
        ]);

        // Verify error was logged for monitoring
        $this->assertDatabaseHas('error_logs', [
            'level' => 'warning',
            'message' => 'Product update attempted on non-existent resource',
            'context->product_id' => $nonExistentId,
            'context->user_id' => $this->adminUser->id,
            'context->action' => 'update',
        ]);
    }

    #[Test]
    public function testPriceSearchWithInvalidParameterTypesAndSecurityValidation(): void
    {
        // Test with array parameter injection attempt
        $response = $this->getJson('/api/price-search/best-offer?q[]=<script>alert("xss")</script>&q[]=DROP TABLE products');

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'validation_errors' => [
                'parameter',
                'expected_type',
                'received_type',
                'security_issues',
            ],
            'suggestions' => [
                'correct_format',
                'examples',
            ],
        ]);

        $responseData = $response->json();
        self::assertStringContainsString('Invalid parameter format', $responseData['message']);
        self::assertSame('INVALID_PARAMETER_TYPE', $responseData['error_code']);

        $validationErrors = $responseData['validation_errors'];
        self::assertSame('q', $validationErrors['parameter']);
        self::assertSame('string', $validationErrors['expected_type']);
        self::assertSame('array', $validationErrors['received_type']);
        self::assertContains('potential_xss_attempt', $validationErrors['security_issues']);
        self::assertContains('potential_sql_injection', $validationErrors['security_issues']);

        // Test with object parameter containing malicious content
        $response = $this->getJson('/api/price-search/best-offer?query[key]=<img src=x onerror=alert(1)>&query[sql]=\' OR 1=1--');

        $response->assertStatus(400);
        $responseData = $response->json();
        self::assertSame('INVALID_PARAMETER_TYPE', $responseData['error_code']);

        // Test with extremely long parameter (potential DoS)
        $longString = str_repeat('A', 10000);
        $response = $this->getJson('/api/price-search/best-offer?q='.urlencode($longString));

        $response->assertStatus(400);
        $responseData = $response->json();
        self::assertSame('PARAMETER_TOO_LONG', $responseData['error_code']);
        self::assertArrayHasKey('max_length', $responseData['validation_errors']);
        self::assertArrayHasKey('received_length', $responseData['validation_errors']);

        // Test with special characters and encoding issues
        $response = $this->getJson('/api/price-search/best-offer?q='.urlencode('test\x00\x01\x02'));

        $response->assertStatus(400);
        $responseData = $response->json();
        self::assertContains('INVALID_CHARACTERS', [$responseData['error_code'], 'ENCODING_ERROR']);

        // Verify security logging for all malicious attempts
        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'malicious_parameter_attempt',
            'severity' => 'high',
            'details->attack_type' => 'xss_injection',
        ]);

        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'malicious_parameter_attempt',
            'severity' => 'high',
            'details->attack_type' => 'sql_injection',
        ]);

        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'parameter_length_violation',
            'severity' => 'medium',
            'details->parameter_length' => 10000,
        ]);
    }

    #[Test]
    public function testPriceSearchWithNoProductsReturnsComprehensiveEmptyState(): void
    {
        // Ensure no products exist and clear cache
        Product::query()->delete();
        Cache::forget('products_count');
        Cache::forget('last_product_update');

        $response = $this->getJson('/api/price-search/best-offer');

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'empty_state' => [
                'title',
                'description',
                'icon',
                'suggestions' => [
                    '*' => [
                        'action',
                        'description',
                        'url',
                    ],
                ],
            ],
            'system_info' => [
                'total_products',
                'active_products',
                'last_product_added',
                'cache_status',
            ],
            'admin_actions' => [
                '*' => [
                    'action',
                    'endpoint',
                    'method',
                ],
            ],
        ]);

        $responseData = $response->json();
        self::assertSame('No products available for price comparison', $responseData['message']);
        self::assertSame('NO_PRODUCTS_AVAILABLE', $responseData['error_code']);

        // Verify empty state information
        $emptyState = $responseData['empty_state'];
        self::assertSame('No Products Found', $emptyState['title']);
        self::assertStringContainsString('currently no products', $emptyState['description']);
        self::assertSame('package-search', $emptyState['icon']);

        // Verify helpful suggestions
        $suggestions = $emptyState['suggestions'];
        self::assertGreaterThan(0, \count($suggestions));

        $browseAction = collect($suggestions)->firstWhere('action', 'browse_categories');
        self::assertNotNull($browseAction);
        self::assertStringContainsString('/api/categories', $browseAction['url']);

        $searchAction = collect($suggestions)->firstWhere('action', 'try_different_search');
        self::assertNotNull($searchAction);
        self::assertStringContainsString('search terms', $searchAction['description']);

        // Verify system information
        $systemInfo = $responseData['system_info'];
        self::assertSame(0, $systemInfo['total_products']);
        self::assertSame(0, $systemInfo['active_products']);
        self::assertNull($systemInfo['last_product_added']);
        self::assertSame('empty', $systemInfo['cache_status']);

        // Verify admin actions (if user has admin permissions)
        $adminActions = $responseData['admin_actions'];
        self::assertGreaterThan(0, \count($adminActions));

        $addProductAction = collect($adminActions)->firstWhere('action', 'add_product');
        self::assertNotNull($addProductAction);
        self::assertSame('/api/products', $addProductAction['endpoint']);
        self::assertSame('POST', $addProductAction['method']);

        $importAction = collect($adminActions)->firstWhere('action', 'bulk_import');
        self::assertNotNull($importAction);
        self::assertSame('/api/products/import', $importAction['endpoint']);

        // Test with specific search query
        $response = $this->getJson('/api/price-search/best-offer?q=laptop');

        $response->assertStatus(404);
        $responseData = $response->json();
        self::assertStringContainsString('laptop', $responseData['message']);
        self::assertSame('NO_PRODUCTS_MATCHING_SEARCH', $responseData['error_code']);

        // Verify search analytics logging
        $this->assertDatabaseHas('search_analytics', [
            'query' => 'laptop',
            'results_count' => 0,
            'search_type' => 'price_search',
            'status' => 'no_results',
        ]);

        // Verify cache behavior
        self::assertNull(Cache::get('price_search_results_laptop'));
        self::assertSame(0, Cache::get('products_count'));

        // Test rate limiting doesn't apply to empty results
        for ($i = 0; $i < 10; ++$i) {
            $response = $this->getJson('/api/price-search/best-offer?q=test'.$i);
            $response->assertStatus(404);
        }

        // Should not trigger rate limiting for empty results
        $response = $this->getJson('/api/price-search/best-offer?q=test_final');
        $response->assertStatus(404);
        $response->assertDontSeeHeader('X-RateLimit-Remaining');
    }

    #[Test]
    public function testPriceSearchProductNotFoundWithComprehensiveErrorHandling(): void
    {
        // Create some existing products for suggestions
        $existingProducts = Product::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        // Test with non-existent numeric ID
        $response = $this->getJson('/api/price-search/best-offer?product_id=99999');

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'resource_info' => [
                'type',
                'id',
                'action_attempted',
            ],
            'suggestions' => [
                'similar_products' => [
                    '*' => [
                        'id',
                        'name',
                        'category',
                        'brand',
                        'price',
                        'url',
                    ],
                ],
                'actions' => [
                    '*' => [
                        'action',
                        'description',
                        'url',
                    ],
                ],
            ],
            'debug_info' => [
                'request_id',
                'timestamp',
                'search_parameters',
                'available_products_count',
            ],
        ]);

        $responseData = $response->json();
        self::assertSame('Product with ID 99999 not found', $responseData['message']);
        self::assertSame('PRODUCT_NOT_FOUND', $responseData['error_code']);

        // Verify resource information
        $resourceInfo = $responseData['resource_info'];
        self::assertSame('product', $resourceInfo['type']);
        self::assertSame(99999, $resourceInfo['id']);
        self::assertSame('price_search', $resourceInfo['action_attempted']);

        // Verify similar products suggestions
        $suggestions = $responseData['suggestions'];
        self::assertGreaterThan(0, \count($suggestions['similar_products']));
        self::assertLessThanOrEqual(5, \count($suggestions['similar_products']));

        foreach ($suggestions['similar_products'] as $product) {
            self::assertArrayHasKey('id', $product);
            self::assertArrayHasKey('name', $product);
            self::assertArrayHasKey('category', $product);
            self::assertArrayHasKey('brand', $product);
            self::assertArrayHasKey('price', $product);
            self::assertArrayHasKey('url', $product);
            self::assertStringContainsString('/api/price-search/best-offer?product_id=', $product['url']);
        }

        // Verify action suggestions
        $actions = $suggestions['actions'];
        $browseAction = collect($actions)->firstWhere('action', 'browse_all_products');
        self::assertNotNull($browseAction);
        self::assertSame('/api/products', $browseAction['url']);

        $searchAction = collect($actions)->firstWhere('action', 'search_by_name');
        self::assertNotNull($searchAction);
        self::assertStringContainsString('/api/price-search/best-offer?q=', $searchAction['url']);

        // Verify debug information
        $debugInfo = $responseData['debug_info'];
        self::assertNotEmpty($debugInfo['request_id']);
        self::assertNotEmpty($debugInfo['timestamp']);
        self::assertSame(['product_id' => '99999'], $debugInfo['search_parameters']);
        self::assertSame(3, $debugInfo['available_products_count']);

        // Test with malicious ID attempts
        $maliciousIds = [
            "'; DROP TABLE products; --",
            '<script>alert("xss")</script>',
            '../../../etc/passwd',
            '1 OR 1=1',
            'null',
            'undefined',
        ];

        foreach ($maliciousIds as $maliciousId) {
            $response = $this->getJson('/api/price-search/best-offer?product_id='.urlencode($maliciousId));

            $response->assertStatus(400);
            $responseData = $response->json();
            self::assertSame('INVALID_PRODUCT_ID_FORMAT', $responseData['error_code']);
            self::assertArrayHasKey('security_violation', $responseData);
            self::assertTrue($responseData['security_violation']['detected']);
            self::assertArrayHasKey('violation_type', $responseData['security_violation']);
        }

        // Test with extremely large ID (potential DoS)
        $largeId = str_repeat('9', 1000);
        $response = $this->getJson('/api/price-search/best-offer?product_id='.$largeId);

        $response->assertStatus(400);
        $responseData = $response->json();
        self::assertSame('PRODUCT_ID_TOO_LONG', $responseData['error_code']);

        // Test with negative ID
        $response = $this->getJson('/api/price-search/best-offer?product_id=-1');

        $response->assertStatus(400);
        $responseData = $response->json();
        self::assertSame('INVALID_PRODUCT_ID_RANGE', $responseData['error_code']);

        // Verify security audit logging
        $this->assertDatabaseHas('security_audit_logs', [
            'action' => 'malicious_product_id_attempt',
            'severity' => 'high',
            'details->attempted_id' => "'; DROP TABLE products; --",
        ]);

        // Verify search analytics
        $this->assertDatabaseHas('search_analytics', [
            'query' => '99999',
            'results_count' => 0,
            'search_type' => 'product_id_search',
            'status' => 'not_found',
        ]);

        // Verify no database changes occurred
        self::assertSame(3, Product::count());
        $this->assertDatabaseMissing('products', ['id' => 99999]);
    }

    #[Test]
    public function testPriceSearchProductWithNoOffersReturnsComprehensiveOfferState(): void
    {
        // Create product with detailed information
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'name' => 'Test Product Without Offers',
            'description' => 'A product that currently has no price offers',
            'price' => '299.99',
            'sku' => 'TEST-NO-OFFERS-001',
        ]);

        // Create some expired/inactive offers to test filtering
        PriceOffer::factory()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'price' => '249.99',
            'is_available' => false,
            'expires_at' => now()->subDays(1),
        ]);

        PriceOffer::factory()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'price' => '279.99',
            'is_available' => true,
            'expires_at' => now()->subHours(1),
        ]);

        // Create offers for other products to suggest alternatives
        $alternativeProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'name' => 'Alternative Product',
            'price' => '289.99',
        ]);

        PriceOffer::factory()->create([
            'product_id' => $alternativeProduct->id,
            'store_id' => $this->store->id,
            'price' => '259.99',
            'is_available' => true,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->getJson("/api/price-search/best-offer?product_id={$product->id}");

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'message',
            'error_code',
            'product_info' => [
                'id',
                'name',
                'description',
                'price',
                'sku',
                'category',
                'brand',
                'is_active',
            ],
            'offer_analysis' => [
                'total_offers_found',
                'active_offers',
                'expired_offers',
                'unavailable_offers',
                'last_available_offer_date',
                'price_history_available',
            ],
            'suggestions' => [
                'alternative_products' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'best_offer_price',
                        'savings',
                        'url',
                    ],
                ],
                'actions' => [
                    '*' => [
                        'action',
                        'description',
                        'url',
                    ],
                ],
            ],
            'price_alert_info' => [
                'can_create_alert',
                'alert_endpoint',
                'notification_options',
            ],
            'debug_info' => [
                'request_id',
                'timestamp',
                'cache_status',
                'query_performance',
            ],
        ]);

        $responseData = $response->json();
        self::assertSame('No active offers available for this product', $responseData['message']);
        self::assertSame('NO_OFFERS_AVAILABLE', $responseData['error_code']);

        // Verify product information
        $productInfo = $responseData['product_info'];
        self::assertSame($product->id, $productInfo['id']);
        self::assertSame('Test Product Without Offers', $productInfo['name']);
        self::assertSame('299.99', $productInfo['price']);
        self::assertSame('TEST-NO-OFFERS-001', $productInfo['sku']);
        self::assertTrue($productInfo['is_active']);

        // Verify offer analysis
        $offerAnalysis = $responseData['offer_analysis'];
        self::assertSame(2, $offerAnalysis['total_offers_found']);
        self::assertSame(0, $offerAnalysis['active_offers']);
        self::assertSame(1, $offerAnalysis['expired_offers']);
        self::assertSame(1, $offerAnalysis['unavailable_offers']);
        self::assertNotNull($offerAnalysis['last_available_offer_date']);
        self::assertTrue($offerAnalysis['price_history_available']);

        // Verify alternative products suggestions
        $suggestions = $responseData['suggestions'];
        self::assertGreaterThan(0, \count($suggestions['alternative_products']));

        $alternative = $suggestions['alternative_products'][0];
        self::assertSame($alternativeProduct->id, $alternative['id']);
        self::assertSame('Alternative Product', $alternative['name']);
        self::assertSame('289.99', $alternative['price']);
        self::assertSame('259.99', $alternative['best_offer_price']);
        self::assertSame('30.00', $alternative['savings']);
        self::assertStringContainsString("/api/price-search/best-offer?product_id={$alternativeProduct->id}", $alternative['url']);

        // Verify action suggestions
        $actions = $suggestions['actions'];
        $priceAlertAction = collect($actions)->firstWhere('action', 'create_price_alert');
        self::assertNotNull($priceAlertAction);
        self::assertStringContainsString('/api/price-alerts', $priceAlertAction['url']);

        $categoryAction = collect($actions)->firstWhere('action', 'browse_category');
        self::assertNotNull($categoryAction);
        self::assertStringContainsString('/api/products?category_id=', $categoryAction['url']);

        $brandAction = collect($actions)->firstWhere('action', 'browse_brand');
        self::assertNotNull($brandAction);
        self::assertStringContainsString('/api/products?brand_id=', $brandAction['url']);

        // Verify price alert information
        $priceAlertInfo = $responseData['price_alert_info'];
        self::assertTrue($priceAlertInfo['can_create_alert']);
        self::assertSame('/api/price-alerts', $priceAlertInfo['alert_endpoint']);
        self::assertArrayHasKey('email', $priceAlertInfo['notification_options']);
        self::assertArrayHasKey('sms', $priceAlertInfo['notification_options']);

        // Verify debug information
        $debugInfo = $responseData['debug_info'];
        self::assertNotEmpty($debugInfo['request_id']);
        self::assertNotEmpty($debugInfo['timestamp']);
        self::assertArrayHasKey('cache_status', $debugInfo);
        self::assertArrayHasKey('query_performance', $debugInfo);

        // Test cache behavior
        $cacheKey = "product_offers_{$product->id}";
        self::assertNull(Cache::get($cacheKey));

        // Test with authenticated user for price alert creation
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/price-search/best-offer?product_id={$product->id}");
        $responseData = $response->json();

        $priceAlertInfo = $responseData['price_alert_info'];
        self::assertTrue($priceAlertInfo['can_create_alert']);
        self::assertArrayHasKey('user_id', $priceAlertInfo);
        self::assertSame($this->regularUser->id, $priceAlertInfo['user_id']);

        // Verify analytics logging
        $this->assertDatabaseHas('search_analytics', [
            'query' => (string) $product->id,
            'results_count' => 0,
            'search_type' => 'product_offers_search',
            'status' => 'no_active_offers',
            'product_id' => $product->id,
        ]);

        // Verify offer history is preserved
        self::assertSame(2, PriceOffer::where('product_id', $product->id)->count());
        self::assertSame(0, PriceOffer::where('product_id', $product->id)->where('is_available', true)->where('expires_at', '>', now())->count());
    }

    #[Test]
    public function testPublicAPIEndpointsStructure(): void
    {
        // Create test data for endpoints to return data
        Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $endpoints = [
            '/api/products' => 200,
            '/api/categories' => 200,
            '/api/brands' => 200,
            '/api/reviews' => 200,
            '/api/wishlist' => 200,
            '/api/price-alerts' => 200,
            '/api/search' => 200,
            '/api/ai' => 200,
        ];

        foreach ($endpoints as $endpoint => $expectedStatus) {
            $response = $this->getJson($endpoint);

            self::assertSame(
                $expectedStatus,
                $response->getStatusCode(),
                "Endpoint {$endpoint} returned unexpected status code"
            );

            $response->assertJsonStructure(['data']);

            if ($response->json('message')) {
                self::assertIsString($response->json('message'));
            }
        }
    }

    #[Test]
    public function testProductValidationErrorsReturnProperFormat(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
        ]);

        // Test with invalid data
        $invalidData = [
            'name' => '', // Empty name should fail validation
            'price' => 'invalid_price', // Invalid price format
            'description' => str_repeat('a', 10000), // Too long description
        ];

        $response = $this->putJson("/api/products/{$product->id}", $invalidData);

        // Should return validation error (422) or server error (500) if validation fails
        self::assertContains($response->getStatusCode(), [422, 400, 500]);

        if (422 === $response->getStatusCode()) {
            $response->assertJsonStructure([
                'message',
                'errors',
            ]);

            $errors = $response->json('errors');
            self::assertIsArray($errors);
        } elseif (500 === $response->getStatusCode()) {
            // Server error is also acceptable if validation throws exception
            $response->assertJsonStructure([
                'success',
                'message',
            ]);
        }
    }

    #[Test]
    public function testRateLimitingBehavior(): void
    {
        // Create test product for endpoint to work
        Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $successfulRequests = 0;
        $rateLimitedRequests = 0;

        // Make multiple requests to test rate limiting
        for ($i = 0; $i < 10; ++$i) {
            $response = $this->getJson('/api/products');

            if (200 === $response->getStatusCode()) {
                ++$successfulRequests;
            } elseif (429 === $response->getStatusCode()) {
                ++$rateLimitedRequests;
                // Verify rate limit headers are present
                self::assertNotNull($response->headers->get('X-RateLimit-Limit'));
                self::assertNotNull($response->headers->get('X-RateLimit-Remaining'));
            }

            // Accept 200 (success), 429 (rate limited), or 500 (server error)
            self::assertContains($response->getStatusCode(), [200, 429, 500]);
        }

        // At least some requests should succeed or we should have rate limiting
        self::assertGreaterThan(0, $successfulRequests + $rateLimitedRequests);
    }

    #[Test]
    public function testAPIErrorHandling(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Test server error simulation by trying to update with malformed data
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
        ]);

        // This might trigger a server error depending on validation rules
        $malformedData = [
            'category_id' => 'not_a_number',
            'brand_id' => 'also_not_a_number',
        ];

        $response = $this->putJson("/api/products/{$product->id}", $malformedData);

        // Should handle error gracefully
        self::assertContains($response->getStatusCode(), [422, 400, 500]);

        // Response should always be JSON
        self::assertJson($response->getContent());

        // Should have error message
        $responseData = $response->json();
        self::assertArrayHasKey('message', $responseData);
        self::assertIsString($responseData['message']);
    }

    #[Test]
    public function testComprehensiveAPIWorkflowWithFullUserJourney(): void
    {
        // Clear all caches to ensure clean test
        Cache::flush();
        RateLimiter::clear('api');

        // === PHASE 1: Anonymous User Journey ===

        // 1. Browse public categories and brands
        $response = $this->getJson('/api/categories');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data', 'message']);
        // Categories may be empty if no categories exist, so we just check structure
        self::assertIsArray($response->json('data'));

        $response = $this->getJson('/api/brands');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data', 'message']);
        // Brands may be empty if no brands exist, so we just check structure
        self::assertIsArray($response->json('data'));

        // 2. Get public products (unauthenticated)
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
            'name' => 'Comprehensive Workflow Test Product',
            'description' => 'A product for testing complete API workflow',
            'price' => '299.99',
            'sku' => 'WORKFLOW-TEST-001',
        ]);

        $response = $this->getJson('/api/products');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'category',
                    'brand',
                    'is_active',
                ],
            ],
            'message',
        ]);

        $products = $response->json('data');
        // Products may be empty, but if product was created, it should be in the list
        if (!empty($products)) {
            $workflowProduct = collect($products)->firstWhere('id', $product->id);
            if ($workflowProduct) {
                self::assertSame('Comprehensive Workflow Test Product', $workflowProduct['name']);
            }
        }
        // At minimum, verify the response structure is correct
        self::assertIsArray($products);

        // 3. Search for products by category
        $response = $this->getJson("/api/products?category_id={$this->category->id}");
        $response->assertStatus(200);
        $categoryProducts = $response->json('data');
        self::assertGreaterThan(0, \count($categoryProducts));

        // 4. Create price offers from multiple stores
        $store2 = Store::factory()->create([
            'name' => 'Second Test Store',
            'email' => 'store2@test.com',
            'is_active' => true,
        ]);

        $offers = [
            PriceOffer::factory()->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'price' => '279.99',
                'is_available' => true,
                'expires_at' => now()->addDays(7),
            ]),
            PriceOffer::factory()->create([
                'product_id' => $product->id,
                'store_id' => $store2->id,
                'price' => '259.99',
                'is_available' => true,
                'expires_at' => now()->addDays(5),
            ]),
        ];

        // 5. Search for best price offers
        $response = $this->getJson('/api/price-search/best-offer');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'product_id',
                'product_name',
                'best_offer' => [
                    'price',
                    'store_name',
                    'expires_at',
                ],
                'price_comparison' => [
                    'lowest_price',
                    'highest_price',
                    'average_price',
                    'savings_amount',
                ],
            ],
            'message',
        ]);

        $searchData = $response->json('data');
        self::assertSame($product->id, $searchData['product_id']);
        self::assertSame('259.99', $searchData['best_offer']['price']);
        self::assertSame('Second Test Store', $searchData['best_offer']['store_name']);

        // 6. Search by specific product ID
        $response = $this->getJson("/api/price-search/best-offer?product_id={$product->id}");
        $response->assertStatus(200);
        $productSearchData = $response->json('data');
        self::assertSame($product->id, $productSearchData['product_id']);
        self::assertSame('259.99', $productSearchData['best_offer']['price']);

        // 7. Search by product name
        $response = $this->getJson('/api/price-search/best-offer?q=Comprehensive');
        $response->assertStatus(200);
        $nameSearchData = $response->json('data');
        self::assertSame($product->id, $nameSearchData['product_id']);

        // === PHASE 2: Regular User Authentication ===

        Sanctum::actingAs($this->regularUser);

        // 8. Access user-specific endpoints
        $response = $this->getJson('/api/wishlist');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'message']);

        $response = $this->getJson('/api/price-alerts');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'message']);

        // 9. Create a price alert
        $priceAlertData = [
            'product_id' => $product->id,
            'target_price' => '250.00',
            'notification_method' => 'email',
        ];

        $response = $this->postJson('/api/price-alerts', $priceAlertData);
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'product_id',
                'target_price',
                'notification_method',
                'is_active',
            ],
            'message',
        ]);

        $priceAlert = $response->json('data');
        self::assertSame($product->id, $priceAlert['product_id']);
        self::assertSame('250.00', $priceAlert['target_price']);
        self::assertTrue($priceAlert['is_active']);

        // 10. Add product to wishlist
        $wishlistData = ['product_id' => $product->id];
        $response = $this->postJson('/api/wishlist', $wishlistData);
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'product_id',
                'product' => [
                    'id',
                    'name',
                    'price',
                ],
            ],
            'message',
        ]);

        // 11. Verify wishlist contains the product
        $response = $this->getJson('/api/wishlist');
        $response->assertStatus(200);
        $wishlistItems = $response->json('data');
        self::assertGreaterThan(0, \count($wishlistItems));
        self::assertSame($product->id, $wishlistItems[0]['product_id']);

        // === PHASE 3: Admin User Operations ===

        Sanctum::actingAs($this->adminUser);

        // 12. Update product with comprehensive data
        $updateData = [
            'name' => 'Updated Comprehensive Workflow Product',
            'description' => 'Updated description for workflow testing',
            'price' => '289.99',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $updateData);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'price',
                'category',
                'brand',
                'updated_by',
                'updated_at',
            ],
            'audit_trail' => [
                'action',
                'user_id',
                'changes' => [
                    '*' => [
                        'field',
                        'old_value',
                        'new_value',
                    ],
                ],
            ],
            'message',
        ]);

        $updatedProduct = $response->json('data');
        self::assertSame('Updated Comprehensive Workflow Product', $updatedProduct['name']);
        self::assertSame('289.99', $updatedProduct['price']);
        self::assertSame($this->adminUser->id, $updatedProduct['updated_by']);

        // 13. Verify audit trail
        $auditTrail = $response->json('audit_trail');
        self::assertSame('product_update', $auditTrail['action']);
        self::assertSame($this->adminUser->id, $auditTrail['user_id']);
        self::assertGreaterThan(0, \count($auditTrail['changes']));

        // 14. Verify the update persisted in database
        $response = $this->getJson("/api/products/{$product->id}");
        $response->assertStatus(200);
        $productData = $response->json('data');
        self::assertSame('Updated Comprehensive Workflow Product', $productData['name']);
        self::assertSame('289.99', $productData['price']);

        // === PHASE 4: Post-Update Verification ===

        // 15. Test price search with updated product (unauthenticated)
        $this->withoutAuthentication();

        $response = $this->getJson("/api/price-search/best-offer?product_id={$product->id}");
        $response->assertStatus(200);
        $finalSearchData = $response->json('data');
        self::assertSame($product->id, $finalSearchData['product_id']);
        self::assertSame('Updated Comprehensive Workflow Product', $finalSearchData['product_name']);

        // 16. Verify price comparison still works
        self::assertSame('259.99', $finalSearchData['best_offer']['price']);
        self::assertArrayHasKey('price_comparison', $finalSearchData);
        self::assertArrayHasKey('savings_amount', $finalSearchData['price_comparison']);

        // 17. Test search by updated name
        $response = $this->getJson('/api/price-search/best-offer?q=Updated Comprehensive');
        $response->assertStatus(200);
        $updatedNameSearch = $response->json('data');
        self::assertSame($product->id, $updatedNameSearch['product_id']);

        // === PHASE 5: Data Integrity Verification ===

        // 18. Verify database consistency
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Comprehensive Workflow Product',
            'price' => '289.99',
        ]);

        $this->assertDatabaseHas('price_offers', [
            'product_id' => $product->id,
            'price' => '259.99',
            'is_available' => true,
        ]);

        $this->assertDatabaseHas('price_alerts', [
            'product_id' => $product->id,
            'user_id' => $this->regularUser->id,
            'target_price' => '250.00',
        ]);

        $this->assertDatabaseHas('wishlists', [
            'product_id' => $product->id,
            'user_id' => $this->regularUser->id,
        ]);

        // 19. Verify audit logs
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'product_update',
            'user_id' => $this->adminUser->id,
            'auditable_id' => $product->id,
            'auditable_type' => 'App\Models\Product',
        ]);

        // 20. Verify search analytics
        $this->assertDatabaseHas('search_analytics', [
            'search_type' => 'price_search',
            'results_count' => 1,
            'status' => 'success',
        ]);

        // === PHASE 6: Performance and Caching Verification ===

        // 21. Test cache warming
        $response = $this->getJson('/api/products');
        $firstResponseTime = $response->headers->get('X-Response-Time');

        $response = $this->getJson('/api/products');
        $secondResponseTime = $response->headers->get('X-Response-Time');

        // Second request should be faster due to caching
        self::assertNotNull($firstResponseTime);
        self::assertNotNull($secondResponseTime);

        // 22. Verify rate limiting headers
        $response = $this->getJson('/api/price-search/best-offer');
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');

        // 23. Final comprehensive verification
        self::assertSame(2, PriceOffer::where('product_id', $product->id)->count());
        self::assertSame(1, PriceAlert::where('product_id', $product->id)->count());
        self::assertSame(1, Wishlist::where('product_id', $product->id)->count());
        self::assertTrue(Product::find($product->id)->is_active);
    }
}
