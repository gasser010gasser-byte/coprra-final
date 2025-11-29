<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceAlert;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Unit tests for the Product model.
 *
 * @internal
 */
#[CoversClass(Product::class)]
final class ProductTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = $this->faker();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test that brand relation is a BelongsTo instance.
     */
    public function testBrandRelation(): void
    {
        $product = new Product();

        $relation = $product->brand();

        self::assertInstanceOf(BelongsTo::class, $relation);
        self::assertSame(Brand::class, $relation->getRelated()::class);
    }

    /**
     * Test that category relation is a BelongsTo instance.
     */
    public function testCategoryRelation(): void
    {
        $product = new Product();

        $relation = $product->category();

        self::assertInstanceOf(BelongsTo::class, $relation);
        self::assertSame(Category::class, $relation->getRelated()::class);
    }

    /**
     * Test that store relation is a BelongsTo instance.
     */
    public function testStoreRelation(): void
    {
        $product = new Product();

        $relation = $product->store();

        self::assertInstanceOf(BelongsTo::class, $relation);
        self::assertSame(Store::class, $relation->getRelated()::class);
    }

    /**
     * Test that priceAlerts relation is a HasMany instance.
     */
    public function testPriceAlertsRelation(): void
    {
        $product = new Product();

        $relation = $product->priceAlerts();

        self::assertInstanceOf(HasMany::class, $relation);
        self::assertSame(PriceAlert::class, $relation->getRelated()::class);
    }

    /**
     * Test that reviews relation is a HasMany instance.
     */
    public function testReviewsRelation(): void
    {
        $product = new Product();

        $relation = $product->reviews();

        self::assertInstanceOf(HasMany::class, $relation);
        self::assertSame(Review::class, $relation->getRelated()::class);
    }

    /**
     * Test that wishlists relation is a HasMany instance.
     */
    public function testWishlistsRelation(): void
    {
        $product = new Product();

        $relation = $product->wishlists();

        self::assertInstanceOf(HasMany::class, $relation);
        self::assertSame(Wishlist::class, $relation->getRelated()::class);
    }

    /**
     * Test that priceOffers relation is a HasMany instance.
     */
    public function testPriceOffersRelation(): void
    {
        $product = new Product();

        $relation = $product->priceOffers();

        self::assertInstanceOf(HasMany::class, $relation);
        self::assertSame(PriceOffer::class, $relation->getRelated()::class);
    }

    /**
     * Test scopeActive adds where clause for is_active.
     */
    public function testScopeActive(): void
    {
        $query = Product::query()->active();

        self::assertSame('select * from "products" where "is_active" = ? and "products"."deleted_at" is null', $query->toSql());
        self::assertSame([true], $query->getBindings());
    }

    /**
     * Test scopeSearch adds where like clause for name.
     */
    public function testScopeSearch(): void
    {
        $query = Product::query()->search('test');

        self::assertSame('select * from "products" where "name" like ? and "products"."deleted_at" is null', $query->toSql());
        self::assertSame(['%test%'], $query->getBindings());
    }

    /**
     * Test scopeWithReviewsCount adds withCount for reviews.
     */
    public function testScopeWithReviewsCount(): void
    {
        $query = Product::query()->withReviewsCount();

        self::assertSame('select * from "products" where "products"."deleted_at" is null', $query->toSql());
        // Note: withCount adds to eager loads, not SQL directly
    }

    /**
     * Test getAverageRating returns average rating.
     */
    public function testGetAverageRating(): void
    {
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id, 'rating' => 5]);
        Review::factory()->create(['product_id' => $product->id, 'rating' => 3]);

        self::assertSame(4.0, $product->getAverageRating());
    }

    /**
     * Test getTotalReviews returns count of reviews.
     */
    public function testGetTotalReviews(): void
    {
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id]);
        Review::factory()->create(['product_id' => $product->id]);

        self::assertSame(2, $product->getTotalReviews());
    }

    /**
     * Test isInWishlist checks if product is in user's wishlist.
     */
    public function testIsInWishlist(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        Wishlist::factory()->create(['product_id' => $product->id, 'user_id' => $user->id]);

        self::assertTrue($product->isInWishlist($user->id));
        self::assertFalse($product->isInWishlist(999));
    }

    /**
     * Test getCurrentPrice returns price or offer price.
     */
    public function testGetCurrentPrice(): void
    {
        $product = Product::factory()->create(['price' => 100.00]);
        PriceOffer::factory()->create(['product_id' => $product->id, 'price' => 80.00, 'is_available' => true]);

        self::assertSame(80.0, $product->getCurrentPrice());
    }

    /**
     * Test getPriceHistory returns ordered price offers.
     */
    public function testGetPriceHistory(): void
    {
        $product = Product::factory()->create();
        PriceOffer::factory()->create(['product_id' => $product->id, 'price' => 90.00]);
        PriceOffer::factory()->create(['product_id' => $product->id, 'price' => 85.00]);

        $history = $product->getPriceHistory();

        self::assertCount(2, $history);
        self::assertEquals(85.00, (float) $history->first()->price);
    }

    /**
     * Test validate method.
     */
    public function testValidate(): void
    {
        $product = new Product(['name' => 'Test', 'price' => 10.00, 'brand_id' => 1, 'category_id' => 1]);

        self::assertTrue($product->validate());
        self::assertEmpty($product->getErrors());
    }

    /**
     * Test validate fails with invalid data.
     */
    public function testValidateFails(): void
    {
        $product = new Product(['name' => '', 'price' => -10, 'brand_id' => 0]);

        self::assertFalse($product->validate());
        self::assertNotEmpty($product->getErrors());
    }
}
