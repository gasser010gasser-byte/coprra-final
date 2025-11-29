<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\Log;

final class AnalyticsService
{
    /**
     * Track an analytics event.
     *
     * @param  string|null  $eventType
     * @param  string  $eventName
     * @param  int|null  $userId
     * @param  int|null  $productId
     * @param  int|null  $categoryId
     * @param  int|null  $storeId
     * @param  array<string, mixed>|null  $metadata
     */
    public function track(
        ?string $eventType,
        string $eventName,
        ?int $userId = null,
        ?int $productId = null,
        ?int $categoryId = null,
        ?int $storeId = null,
        ?array $metadata = null
    ): ?AnalyticsEvent {
        if (! config('coprra.analytics.track_user_behavior', true)) {
            return null;
        }

        // Validate event_type is not null
        if ($eventType === null || $eventType === '') {
            Log::warning('Failed to track analytics event', [
                'error' => 'Event type is required and cannot be null or empty',
            ]);

            return null;
        }

        // Validate event_type length (max 50 characters per migration)
        if (\strlen($eventType) > 50) {
            Log::warning('Failed to track analytics event', [
                'error' => 'Event type exceeds maximum length of 50 characters',
                'event_type_length' => \strlen($eventType),
            ]);

            return null;
        }

        // Validate event_name length (max 100 characters per migration)
        if (\strlen($eventName) > 100) {
            Log::warning('Failed to track analytics event', [
                'error' => 'Event name exceeds maximum length of 100 characters',
                'event_name_length' => \strlen($eventName),
            ]);

            return null;
        }

        try {
            // Sanitize metadata to remove null bytes and other problematic characters for JSON encoding
            $sanitizedMetadata = $this->sanitizeMetadata($metadata);

            return AnalyticsEvent::create([
                'event_type' => $eventType,
                'event_name' => $eventName,
                'user_id' => $userId,
                'product_id' => $productId,
                'category_id' => $categoryId,
                'store_id' => $storeId,
                'metadata' => $sanitizedMetadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to track analytics event', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Track price comparison event.
     */
    public function trackPriceComparison(int $productId, ?int $userId = null, array $metadata = []): ?AnalyticsEvent
    {
        return $this->track(
            AnalyticsEvent::TYPE_PRICE_COMPARISON,
            'Price Comparison Viewed',
            $userId,
            $productId,
            null,
            null,
            $metadata
        );
    }

    /**
     * Track product view event.
     */
    public function trackProductView(int $productId, ?int $userId = null): ?AnalyticsEvent
    {
        return $this->track(
            AnalyticsEvent::TYPE_PRODUCT_VIEW,
            'Product Viewed',
            $userId,
            $productId,
            null,
            null,
            null
        );
    }

    /**
     * Track search event.
     */
    public function trackSearch(string $query, ?int $userId = null, array $filters = []): ?AnalyticsEvent
    {
        return $this->track(
            AnalyticsEvent::TYPE_SEARCH,
            'Search Performed',
            $userId,
            null,
            null,
            null,
            ['query' => $query, 'filters' => $filters]
        );
    }

    /**
     * Track store click event.
     */
    public function trackStoreClick(int $storeId, ?int $productId = null, ?int $userId = null): ?AnalyticsEvent
    {
        return $this->track(
            AnalyticsEvent::TYPE_STORE_CLICK,
            'Store Clicked',
            $userId,
            $productId,
            null,
            $storeId,
            null
        );
    }

    /**
     * Get most viewed products.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMostViewedProducts(int $limit = 10, int $days = 30): array
    {
        $cutoffDate = now()->subDays($days);

        return AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_PRODUCT_VIEW)
            ->where('created_at', '>=', $cutoffDate)
            ->selectRaw('product_id, COUNT(*) as view_count')
            ->groupBy('product_id')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                return [
                    'product_id' => $event->product_id,
                    'view_count' => (int) $event->view_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get most searched queries.
     *
     * @return array<string, int>
     */
    public function getMostSearchedQueries(int $limit = 10, int $days = 30): array
    {
        $cutoffDate = now()->subDays($days);

        $events = AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_SEARCH)
            ->where('created_at', '>=', $cutoffDate)
            ->get();

        $queries = [];
        foreach ($events as $event) {
            $metadata = $event->metadata ?? [];
            $query = $metadata['query'] ?? '';
            if ($query) {
                $queries[$query] = ($queries[$query] ?? 0) + 1;
            }
        }

        arsort($queries);

        return \array_slice($queries, 0, $limit, true);
    }

    /**
     * Get most popular stores.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMostPopularStores(int $limit = 10, int $days = 30): array
    {
        $cutoffDate = now()->subDays($days);

        return AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_STORE_CLICK)
            ->where('created_at', '>=', $cutoffDate)
            ->whereNotNull('store_id')
            ->selectRaw('store_id, COUNT(*) as click_count')
            ->groupBy('store_id')
            ->orderByDesc('click_count')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                return [
                    'store_id' => $event->store_id,
                    'click_count' => (int) $event->click_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get price comparison statistics.
     *
     * @return array<string, mixed>
     */
    public function getPriceComparisonStats(int $days = 30): array
    {
        $cutoffDate = now()->subDays($days);

        $events = AnalyticsEvent::where('event_type', AnalyticsEvent::TYPE_PRICE_COMPARISON)
            ->where('created_at', '>=', $cutoffDate)
            ->get();

        $uniqueProducts = $events->pluck('product_id')->filter()->unique()->count();
        $uniqueUsers = $events->pluck('user_id')->filter()->unique()->count();

        return [
            'total_comparisons' => $events->count(),
            'unique_products' => $uniqueProducts,
            'unique_users' => $uniqueUsers,
            'average_per_day' => $days > 0 ? round($events->count() / $days, 2) : 0.0,
        ];
    }

    /**
     * Get dashboard data.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(int $days = 30): array
    {
        return [
            'overview' => [
                'total_events' => AnalyticsEvent::where('created_at', '>=', now()->subDays($days))->count(),
                'unique_users' => AnalyticsEvent::where('created_at', '>=', now()->subDays($days))
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id'),
            ],
            'price_comparisons' => $this->getPriceComparisonStats($days),
            'most_viewed_products' => $this->getMostViewedProducts(10, $days),
            'most_searched_queries' => $this->getMostSearchedQueries(10, $days),
            'most_popular_stores' => $this->getMostPopularStores(10, $days),
        ];
    }

    /**
     * Sanitize metadata array to remove null bytes and ensure JSON compatibility.
     *
     * @param  array<string, mixed>|null  $metadata
     *
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $sanitized = [];
        foreach ($metadata as $key => $value) {
            $sanitizedKey = \is_string($key) ? str_replace("\0", '', $key) : $key;

            if (\is_string($value)) {
                $sanitized[$sanitizedKey] = str_replace("\0", '', $value);
            } elseif (\is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeMetadata($value);
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Clean old analytics data.
     */
    public function cleanOldData(int $daysToKeep = 365): int
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);

            $count = AnalyticsEvent::where('created_at', '<', $cutoffDate)->delete();

            Log::info('Cleaned old analytics data', [
                'days_to_keep' => $daysToKeep,
                'records_deleted' => $count,
            ]);

            return \is_int($count) ? $count : 0;
        } catch (\Throwable $e) {
            Log::warning('Failed to clean old analytics data', [
                'error' => $e->getMessage(),
                'days_to_keep' => $daysToKeep,
            ]);

            return 0;
        }
    }
}
