<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Repositories\RecommendationRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RecommendationService
{
    public function __construct(
        private readonly RecommendationRepository $recommendationRepository
    ) {
    }

    /**
     * @return array<int, Product>
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getRecommendations(User|int $user, int $limit = 10): array
    {
        // Handle both User object and user ID
        if (is_int($user)) {
            $userModel = User::find($user);
            if (!$userModel) {
                return [];
            }
            $user = $userModel;
        }

        $cacheKey = "recommendations_user_{$user->id}";
        $cacheTtl = (int) config('coprra.cache.durations.default', 3600);

        return Cache::remember(
            $cacheKey,
            $cacheTtl, /**
             * @psalm-return array<int, mixed>
             */
            function () use ($user, $limit): array {
                $recommendations = $this->collectRecommendations($user, $limit);

                return $this->filterAndLimitRecommendations($recommendations, $user, $limit);
            }
        );
    }

    /**
     * @return array<int, Product>
     */
    public function getSimilarProducts(Product $product, int $limit = 5): array
    {
        return $this->recommendationRepository
            ->getSimilarProducts($product, $limit)
            ->all()
        ;
    }

    /**
     * @return array<int, Product>
     */
    public function getFrequentlyBoughtTogether(Product $product, int $limit = 5): array
    {
        return $this->recommendationRepository
            ->getFrequentlyBoughtTogether($product, $limit)
            ->all()
        ;
    }

    /**
     * Collect recommendations from different sources.
     *
     * @psalm-return Collection<never, never>
     */
    private function collectRecommendations(User $user, int $limit): Collection
    {
        $recommendations = collect();

        // Content-Based Filtering (prioritize this as it's most personalized)
        $contentRecs = $this->getContentBasedRecommendations($user, $limit);
        $recommendations = $recommendations->merge($contentRecs);

        // Collaborative Filtering
        $collaborativeRecs = $this->getCollaborativeRecommendations($user, $limit);
        $recommendations = $recommendations->merge($collaborativeRecs);

        // Trending Products (fallback)
        $trendingRecs = $this->getTrendingRecommendations($limit);

        return $recommendations->merge($trendingRecs);
    }

    /**
     * Filter recommendations and apply limit.
     *
     * @return array<int, mixed>
     */
    private function filterAndLimitRecommendations(Collection $recommendations, User $user, int $limit): array
    {
        $purchasedProductIds = $this->getPurchasedProductIds($user);

        $filtered = $recommendations
            ->unique('id')
            ->reject(static function (mixed $product) use ($purchasedProductIds): bool {
                if (! \is_object($product) || ! property_exists($product, 'id')) {
                    return true;
                }

                return \in_array($product->id, $purchasedProductIds, true);
            })
            ->values()
        ;

        // If we don't have enough recommendations, get more from trending
        if ($filtered->count() < $limit) {
            $trendingRecs = $this->getTrendingRecommendations($limit * 2);
            $existingIds = $filtered->pluck('id')->toArray();
            $additional = collect($trendingRecs)
                ->reject(static function (mixed $product) use ($purchasedProductIds, $existingIds): bool {
                    if (! \is_object($product) || ! property_exists($product, 'id')) {
                        return true;
                    }
                    $productId = $product->id;
                    return \in_array($productId, $purchasedProductIds, true) 
                        || \in_array($productId, $existingIds, true);
                })
                ->unique('id');
            
            $filtered = $filtered->merge($additional);
        }

        return $filtered
            ->take($limit)
            ->values()
            ->toArray()
        ;
    }

    /**
     * Get user preferences based on purchase history.
     *
     * @return array<string, array<int, int>|array<string, float|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    private function getUserPreferences(User $user): array
    {
        $purchases = $this->recommendationRepository->getUserPurchaseHistory($user);

        if ($purchases->isEmpty()) {
            return [];
        }

        $categories = $this->extractCategoryPreferences($purchases);
        $brands = $this->extractBrandPreferences($purchases);
        $priceRange = $this->extractPriceRange($purchases);

        return [
            'categories' => $categories->keys()->toArray(),
            'brands' => $brands->keys()->toArray(),
            'price_range' => $priceRange,
        ];
    }

    /**
     * Extract category preferences from purchases.
     *
     * @psalm-return Collection<int, int>
     */
    private function extractCategoryPreferences(Collection $purchases): Collection
    {
        return $purchases->groupBy(static function (OrderItem $item): int {
            $product = $item->product;

            return $product && $product->category_id ? $product->category_id : 0;
        })
            ->map(static function ($items): int {
                return $items->sum('quantity');
            })
            ->sortDesc()
            ->take(5)
        ;
    }

    /**
     * Extract brand preferences from purchases.
     *
     * @psalm-return Collection<int, int>
     */
    private function extractBrandPreferences(Collection $purchases): Collection
    {
        return $purchases->groupBy(static function (OrderItem $item): int {
            $product = $item->product;

            return $product && $product->brand_id ? $product->brand_id : 0;
        })
            ->map(static function ($items): int {
                return $items->sum('quantity');
            })
            ->sortDesc()
            ->take(5)
        ;
    }

    /**
     * Extract price range from purchases.
     *
     * @return array<float|int|mixed|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{min: mixed, max: mixed, average: float|int|null}
     */
    private function extractPriceRange(Collection $purchases): array
    {
        $prices = $purchases->map(static function (OrderItem $item): float {
            $product = $item->product;

            return $product ? (float) $product->price : 0.0;
        });

        return [
            'min' => $prices->min(),
            'max' => $prices->max(),
            'average' => $prices->avg(),
        ];
    }

    /**
     * @return array<int, Product>
     */
    private function getCollaborativeRecommendations(User $user, int $limit): array
    {
        // Find users with similar purchase patterns
        $similarUsers = $this->findSimilarUsers($user);

        if ($similarUsers->isEmpty()) {
            return [];
        }

        $similarUserIds = $similarUsers->pluck('user_id')->toArray();

        // Get products purchased by similar users but not by current user
        $purchasedProductIds = $this->getPurchasedProductIds($user);

        return $this->getProductsBySimilarUsers($similarUserIds, $purchasedProductIds, $limit);
    }

    /**
     * Get products purchased by similar users but not by current user.
     *
     * @param array<int, int> $similarUserIds
     * @param array<int, int> $purchasedProductIds
     *
     * @return array<int, Product>
     */
    private function getProductsBySimilarUsers(array $similarUserIds, array $purchasedProductIds, int $limit): array
    {
        return Product::whereHas('orderItems', static function ($query) use ($similarUserIds): void {
            $query->whereHas('order', static function ($q) use ($similarUserIds): void {
                $q->whereIn('user_id', $similarUserIds);
            });
        })
            ->where('is_active', true)
            ->whereNotIn('id', $purchasedProductIds)
            ->withCount([
                'orderItems as purchase_count' => static function ($q) use ($similarUserIds): void {
                    $q->whereHas('order', static function ($o) use ($similarUserIds): void {
                        $o->whereIn('user_id', $similarUserIds);
                    });
                },
            ])
            ->orderByDesc('purchase_count')
            ->limit($limit)
            ->get()
            ->all()
        ;
    }

    /**
     * @return array<int, Product>
     */
    private function getContentBasedRecommendations(User $user, int $limit): array
    {
        $userPreferences = $this->getUserPreferences($user);

        // If no preferences, return trending products as fallback
        if (0 === \count($userPreferences)) {
            return [];
        }

        $query = Product::query();

        // Apply filters based on user preferences
        $hasAnyFilter = false;
        
        if (!empty($userPreferences['categories'] ?? [])) {
            $this->applyCategoryFilter($query, $userPreferences);
            $hasAnyFilter = true;
        }
        
        if (!empty($userPreferences['brands'] ?? [])) {
            $this->applyBrandFilter($query, $userPreferences);
            $hasAnyFilter = true;
        }
        
        // Only apply price range if we have other filters
        if ($hasAnyFilter && isset($userPreferences['price_range'])) {
            $this->applyPriceRangeFilter($query, $userPreferences);
        }
        
        // If no filters were applied, return empty
        if (!$hasAnyFilter) {
            return [];
        }

        $results = $query
            ->where('is_active', true)
            ->orderBy('rating', 'desc')
            ->limit($limit * 2) // Get more to account for filtering
            ->get()
            ->all()
        ;

        // If we have category or brand preferences but price filter is too restrictive,
        // try without price range filter to ensure we get recommendations
        if (count($results) < $limit && isset($userPreferences['price_range'])) {
            $hasCategoryFilter = !empty($userPreferences['categories'] ?? []);
            $hasBrandFilter = !empty($userPreferences['brands'] ?? []);
            
            if ($hasCategoryFilter || $hasBrandFilter) {
                $query2 = Product::query();
                $this->applyCategoryFilter($query2, $userPreferences);
                $this->applyBrandFilter($query2, $userPreferences);
                
                $results2 = $query2
                    ->where('is_active', true)
                    ->orderBy('rating', 'desc')
                    ->limit($limit * 2)
                    ->get()
                    ->all()
                ;
                
                // Merge results, prioritizing price-filtered ones
                $existingIds = array_column($results, 'id');
                foreach ($results2 as $product) {
                    if (!in_array($product->id, $existingIds, true)) {
                        $results[] = $product;
                    }
                }
            }
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Apply category filter to query.
     *
     * @param  array<string, array<int, int>|array<string, float|* @method static \App\Models\Brand create(array<string, string|bool|null>>  $userPreferences
     */
    private function applyCategoryFilter(Builder $query, array $userPreferences): void
    {
        $categories = $userPreferences['categories'] ?? null;
        if (\is_array($categories) && \count($categories) > 0) {
            $query->whereIn('category_id', $categories);
        }
    }

    /**
     * Apply price range filter to query.
     *
     * @param  array<string, array<int, int>|array<string, float|* @method static \App\Models\Brand create(array<string, string|bool|null>>  $userPreferences
     */
    private function applyPriceRangeFilter(Builder $query, array $userPreferences): void
    {
        if (isset($userPreferences['price_range']) && \is_array($userPreferences['price_range'])) {
            $minPrice = $userPreferences['price_range']['min'] ?? null;
            $maxPrice = $userPreferences['price_range']['max'] ?? null;

            if (null !== $minPrice && null !== $maxPrice) {
                // Expand range by 20% to allow some flexibility
                $range = $maxPrice - $minPrice;
                $expandedMin = max(0, $minPrice - ($range * 0.1));
                $expandedMax = $maxPrice + ($range * 0.1);
                $query->whereBetween('price', [$expandedMin, $expandedMax]);
            }
        }
    }

    /**
     * Apply brand filter to query.
     *
     * @param  array<string, array<int, int>|array<string, float|* @method static \App\Models\Brand create(array<string, string|bool|null>>  $userPreferences
     */
    private function applyBrandFilter(Builder $query, array $userPreferences): void
    {
        $brands = $userPreferences['brands'] ?? null;
        if (\is_array($brands) && \count($brands) > 0) {
            $query->whereIn('brand_id', $brands);
        }
    }

    /**
     * @return array<int, Product>
     */
    private function getTrendingRecommendations(int $limit): array
    {
        // Get all active products with recent purchases count and total purchases count
        $products = Product::where('is_active', true)
            ->withCount([
                'orderItems as recent_purchases' => $this->getRecentPurchasesQuery(),
            ])
            ->withCount([
                'orderItems as total_purchases',
            ])
            ->get()
            ->all()
        ;

        // If no products, return empty
        if (empty($products)) {
            return [];
        }

        // Sort by recent purchases first, then total purchases, then rating
        usort($products, static function ($a, $b) {
            // First sort by recent purchases (descending)
            $recentDiff = ($b->recent_purchases ?? 0) - ($a->recent_purchases ?? 0);
            if ($recentDiff !== 0) {
                return $recentDiff;
            }

            // Then by total purchases (descending)
            $totalDiff = ($b->total_purchases ?? 0) - ($a->total_purchases ?? 0);
            if ($totalDiff !== 0) {
                return $totalDiff;
            }

            // Finally by rating (descending)
            return ($b->rating ?? 0) <=> ($a->rating ?? 0);
        });

        // Return limited results
        return array_slice($products, 0, $limit);
    }

    /**
     * Get query for recent purchases (last 7 days).
     *
     * @psalm-return \Closure(Builder):void
     */
    private function getRecentPurchasesQuery(): \Closure
    {
        return static function (Builder $query): void {
            $query->whereHas('order', static function (Builder $q): void {
                $q->where('created_at', '>=', now()->subDays(7));
            });
        };
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function findSimilarUsers(User $user): Collection
    {
        $userPurchases = $this->getUserPurchaseHistory($user);

        if ($userPurchases->isEmpty()) {
            return new Collection();
        }

        $userProductIds = $userPurchases->pluck('product_id')->toArray();

        return $this->querySimilarUsers($user->id, $userProductIds);
    }

    /**
     * Query for finding similar users based on common products.
     *
     * @psalm-return Collection<int, \stdClass>
     */
    private function querySimilarUsers(int $userId, array $productIds): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.user_id', '!=', $userId)
            ->whereIn('order_items.product_id', $productIds)
            ->select('orders.user_id')
            ->selectRaw('COUNT(DISTINCT order_items.product_id) as common_products')
            ->groupBy('orders.user_id')
            ->having('common_products', '>=', 2)
            ->orderBy('common_products', 'desc')
            ->limit(10)
            ->get()
        ;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function getUserPurchaseHistory(User $user): Collection
    {
        return OrderItem::whereHas('order', static function ($query) use ($user): void {
            $query->where('user_id', $user->id);
        })
            ->select('product_id')
            ->distinct()
            ->get()
        ;
    }

    /**
     * @return array<int, int>
     */
    private function getPurchasedProductIds(User $user): array
    {
        return $this->recommendationRepository->getPurchasedProductIds($user);
    }
}
