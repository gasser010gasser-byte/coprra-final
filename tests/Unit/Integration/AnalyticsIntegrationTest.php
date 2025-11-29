<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AnalyticsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = new AnalyticsService();

        // Enable analytics tracking for tests
        Config::set('coprra.analytics.track_user_behavior', true);
    }

    public function testTrackAnalyticsEventIntegration(): void
    {
        // Create test data
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $store = Store::factory()->create();

        // Track an analytics event
        $event = $this->analyticsService->track(
            AnalyticsEvent::TYPE_PRODUCT_VIEW,
            'Product Viewed',
            $user->id,
            $product->id,
            $category->id,
            $store->id,
            ['source' => 'search', 'position' => 3]
        );

        // Assert event was created
        self::assertInstanceOf(AnalyticsEvent::class, $event);
        $this->assertDatabaseHas('analytics_events', [
            'event_type' => AnalyticsEvent::TYPE_PRODUCT_VIEW,
            'event_name' => 'Product Viewed',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'category_id' => $category->id,
            'store_id' => $store->id,
        ]);

        // Verify metadata is stored correctly
        $storedEvent = AnalyticsEvent::find($event->id);
        self::assertSame(['source' => 'search', 'position' => 3], $storedEvent->metadata);
    }

    public function testTrackPriceComparisonIntegration(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Track price comparison event
        $event = $this->analyticsService->trackPriceComparison(
            $product->id,
            $user->id,
            ['stores_compared' => 3, 'price_difference' => 15.50]
        );

        // Assert event was created with correct type
        self::assertInstanceOf(AnalyticsEvent::class, $event);
        self::assertSame(AnalyticsEvent::TYPE_PRICE_COMPARISON, $event->event_type);
        self::assertSame('Price Comparison Viewed', $event->event_name);
        self::assertSame($user->id, $event->user_id);
        self::assertSame($product->id, $event->product_id);

        // Verify metadata
        self::assertSame(['stores_compared' => 3, 'price_difference' => 15.50], $event->metadata);
    }

    public function testTrackMultipleEventTypes(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $store = Store::factory()->create();

        // Track different types of events
        $events = [
            [
                'type' => AnalyticsEvent::TYPE_PRODUCT_VIEW,
                'name' => 'Product Viewed',
                'metadata' => ['source' => 'homepage'],
            ],
            [
                'type' => AnalyticsEvent::TYPE_SEARCH,
                'name' => 'Search Performed',
                'metadata' => ['query' => 'laptop', 'results_count' => 25],
            ],
            [
                'type' => AnalyticsEvent::TYPE_STORE_CLICK,
                'name' => 'Store Clicked',
                'metadata' => ['store_name' => 'Amazon'],
            ],
            [
                'type' => AnalyticsEvent::TYPE_CATEGORY_VIEW,
                'name' => 'Category Viewed',
                'metadata' => ['category_name' => 'Electronics'],
            ],
            [
                'type' => AnalyticsEvent::TYPE_WISHLIST_ADD,
                'name' => 'Added to Wishlist',
                'metadata' => ['from_page' => 'product_detail'],
            ],
            [
                'type' => AnalyticsEvent::TYPE_CART_ADD,
                'name' => 'Added to Cart',
                'metadata' => ['quantity' => 2],
            ],
        ];

        foreach ($events as $eventData) {
            $event = $this->analyticsService->track(
                $eventData['type'],
                $eventData['name'],
                $user->id,
                $product->id,
                $category->id,
                $store->id,
                $eventData['metadata']
            );

            self::assertInstanceOf(AnalyticsEvent::class, $event);
            self::assertSame($eventData['type'], $event->event_type);
            self::assertSame($eventData['name'], $event->event_name);
            self::assertSame($eventData['metadata'], $event->metadata);
        }

        // Verify all events were stored
        $this->assertDatabaseCount('analytics_events', 6);
    }

    public function testAnalyticsTrackingDisabled(): void
    {
        // Disable analytics tracking
        Config::set('coprra.analytics.track_user_behavior', false);

        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Attempt to track an event
        $event = $this->analyticsService->track(
            AnalyticsEvent::TYPE_PRODUCT_VIEW,
            'Product Viewed',
            $user->id,
            $product->id
        );

        // Should return null when tracking is disabled
        self::assertNull($event);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function testAnalyticsEventWithOptionalParameters(): void
    {
        // Track event with minimal parameters
        $event = $this->analyticsService->track(
            AnalyticsEvent::TYPE_SEARCH,
            'Search Performed'
        );

        self::assertInstanceOf(AnalyticsEvent::class, $event);
        self::assertSame(AnalyticsEvent::TYPE_SEARCH, $event->event_type);
        self::assertSame('Search Performed', $event->event_name);
        self::assertNull($event->user_id);
        self::assertNull($event->product_id);
        self::assertNull($event->category_id);
        self::assertNull($event->store_id);
        self::assertNull($event->metadata);
    }

    public function testAnalyticsEventWithComplexMetadata(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $complexMetadata = [
            'search_query' => 'gaming laptop',
            'filters' => [
                'price_range' => ['min' => 500, 'max' => 1500],
                'brands' => ['Dell', 'HP', 'Lenovo'],
                'rating' => 4.5,
            ],
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'referrer' => 'https://google.com',
            'session_duration' => 1200,
            'is_mobile' => false,
        ];

        $event = $this->analyticsService->track(
            AnalyticsEvent::TYPE_SEARCH,
            'Advanced Search',
            $user->id,
            $product->id,
            null,
            null,
            $complexMetadata
        );

        self::assertInstanceOf(AnalyticsEvent::class, $event);
        self::assertSame($complexMetadata, $event->metadata);

        // Verify complex metadata is properly stored and retrieved
        $storedEvent = AnalyticsEvent::find($event->id);
        self::assertSame($complexMetadata, $storedEvent->metadata);
        self::assertSame(['Dell', 'HP', 'Lenovo'], $storedEvent->metadata['filters']['brands']);
    }

    public function testCleanOldAnalyticsData(): void
    {
        // Create old events (older than 30 days)
        $oldEvents = AnalyticsEvent::factory()->count(5)->create([
            'created_at' => now()->subDays(35),
        ]);

        // Create recent events (within 30 days)
        $recentEvents = AnalyticsEvent::factory()->count(3)->create([
            'created_at' => now()->subDays(15),
        ]);

        // Verify initial count
        $this->assertDatabaseCount('analytics_events', 8);

        // Clean old data (keep 30 days)
        $deletedCount = $this->analyticsService->cleanOldData(30);

        // Verify old events were deleted
        self::assertSame(5, $deletedCount);
        $this->assertDatabaseCount('analytics_events', 3);

        // Verify recent events still exist
        foreach ($recentEvents as $event) {
            $this->assertDatabaseHas('analytics_events', ['id' => $event->id]);
        }

        // Verify old events were deleted
        foreach ($oldEvents as $event) {
            $this->assertDatabaseMissing('analytics_events', ['id' => $event->id]);
        }
    }

    public function testAnalyticsErrorHandling(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to track analytics event', \Mockery::type('array'))
        ;

        // Force an error by using invalid data
        $event = $this->analyticsService->track(
            str_repeat('x', 300), // Very long event type to trigger validation error
            'Test Event'
        );

        // Should return null on error
        self::assertNull($event);
    }

    public function testAnalyticsEventTimestamps(): void
    {
        $beforeTracking = now()->subSecond();

        $event = $this->analyticsService->track(
            AnalyticsEvent::TYPE_PRODUCT_VIEW,
            'Product Viewed'
        );

        $afterTracking = now()->addSecond();

        self::assertInstanceOf(AnalyticsEvent::class, $event);
        // Allow 1 second buffer for timing precision
        self::assertTrue(
            $event->created_at->gte($beforeTracking) && $event->created_at->lte($afterTracking),
            'created_at should be between beforeTracking and afterTracking'
        );
        self::assertTrue(
            $event->updated_at->gte($beforeTracking) && $event->updated_at->lte($afterTracking),
            'updated_at should be between beforeTracking and afterTracking'
        );
    }

    public function testAnalyticsIntegrationWorkflow(): void
    {
        // Simulate a complete user journey
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $category = Category::factory()->create();
        $store = Store::factory()->create();

        // 1. User performs search
        $searchEvent = $this->analyticsService->track(
            AnalyticsEvent::TYPE_SEARCH,
            'Search Performed',
            $user->id,
            null,
            null,
            null,
            ['query' => 'laptop', 'results_count' => 15]
        );

        // 2. User views category
        $categoryEvent = $this->analyticsService->track(
            AnalyticsEvent::TYPE_CATEGORY_VIEW,
            'Category Viewed',
            $user->id,
            null,
            $category->id,
            null,
            ['category_name' => 'Electronics']
        );

        // 3. User views product
        $productEvent = $this->analyticsService->track(
            AnalyticsEvent::TYPE_PRODUCT_VIEW,
            'Product Viewed',
            $user->id,
            $product->id,
            $category->id,
            null,
            ['source' => 'search_results', 'position' => 3]
        );

        // 4. User compares prices
        $comparisonEvent = $this->analyticsService->trackPriceComparison(
            $product->id,
            $user->id,
            ['stores_compared' => 4, 'price_difference' => 25.99]
        );

        // 5. User clicks store
        $storeEvent = $this->analyticsService->track(
            AnalyticsEvent::TYPE_STORE_CLICK,
            'Store Clicked',
            $user->id,
            $product->id,
            $category->id,
            $store->id,
            ['store_name' => 'Amazon', 'price' => 599.99]
        );

        // 6. User adds to wishlist
        $wishlistEvent = $this->analyticsService->track(
            AnalyticsEvent::TYPE_WISHLIST_ADD,
            'Added to Wishlist',
            $user->id,
            $product->id,
            $category->id,
            null,
            ['from_page' => 'product_detail']
        );

        // Verify all events were tracked
        $this->assertDatabaseCount('analytics_events', 6);

        // Verify event sequence and data integrity
        $events = AnalyticsEvent::where('user_id', $user->id)
            ->orderBy('created_at')
            ->get()
        ;

        self::assertSame(6, $events->count());
        self::assertSame(AnalyticsEvent::TYPE_SEARCH, $events[0]->event_type);
        self::assertSame(AnalyticsEvent::TYPE_CATEGORY_VIEW, $events[1]->event_type);
        self::assertSame(AnalyticsEvent::TYPE_PRODUCT_VIEW, $events[2]->event_type);
        self::assertSame(AnalyticsEvent::TYPE_PRICE_COMPARISON, $events[3]->event_type);
        self::assertSame(AnalyticsEvent::TYPE_STORE_CLICK, $events[4]->event_type);
        self::assertSame(AnalyticsEvent::TYPE_WISHLIST_ADD, $events[5]->event_type);

        // Verify user journey consistency
        $productRelatedEvents = $events->where('product_id', $product->id);
        self::assertSame(4, $productRelatedEvents->count());

        $categoryRelatedEvents = $events->where('category_id', $category->id);
        self::assertSame(4, $categoryRelatedEvents->count());
    }
}
