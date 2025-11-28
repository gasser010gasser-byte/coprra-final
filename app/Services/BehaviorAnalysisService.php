<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Repositories\BehaviorAnalysisRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Behavior Analysis Service.
 *
 * Tracks and analyzes user behavior patterns for personalization and analytics.
 * Not marked as final to allow mocking in unit tests while maintaining production integrity.
 */
class BehaviorAnalysisService
{
    public function __construct(
        private readonly BehaviorAnalysisRepository $behaviorRepository
    ) {
    }

    /**
     * Track user behavior action.
     *
     * @param  array<string, string|int|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>  $data
     */
    public function trackUserBehavior(User $user, string $action, array $data = []): void
    {
        $payload = [
            'user_id' => $user->id,
            'action' => $action,
            'data' => json_encode($data),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->behaviorRepository->insertUserBehavior($payload);
    }

    /**
     * Get comprehensive user analytics.
     *
     * @return array<string, mixed>
     */
    public function getUserAnalytics(User $user): array
    {
        $cacheKey = "user_analytics_{$user->id}";

        return Cache::remember(
            $cacheKey,
            1800,
            /** @return array<string, mixed> */
            function () use ($user): array {
                return [
                    'purchase_history' => $this->getPurchaseHistory($user),
                    'browsing_patterns' => $this->getBrowsingPatterns($user),
                    'preferences' => $this->getUserPreferences($user),
                    'engagement_score' => $this->calculateEngagementScore($user),
                    'lifetime_value' => $this->calculateLifetimeValue($user),
                    'recommendation_score' => $this->calculateRecommendationScore($user),
                ];
            }
        );
    }

    /**
     * Get site-wide analytics.
     *
     * @return array<string, mixed>
     */
    public function getSiteAnalytics(): array
    {
        $cacheKey = 'site_analytics';

        return Cache::remember(
            $cacheKey,
            3600,
            /** @return array<string, mixed> */
            function (): array {
                return [
                    'total_users' => $this->behaviorRepository->getTotalUsersCount(),
                    'active_users' => $this->behaviorRepository->getActiveUsersCount(),
                    'total_orders' => $this->behaviorRepository->getTotalOrdersCount(),
                    'total_revenue' => $this->behaviorRepository->getTotalRevenue(),
                    'average_order_value' => $this->behaviorRepository->getAverageOrderValue(),
                    'conversion_rate' => $this->getConversionRate(),
                    'most_viewed_products' => $this->getMostViewedProducts(),
                    'top_selling_products' => $this->getTopSellingProducts(),
                ];
            }
        );
    }

    /**
     * Get user's purchase history.
     *
     * @return array<int, array<string, string|int|float|bool|array<int, array<string, string|int|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>>|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    private function getPurchaseHistory(User $user): array
    {
        /** @var array<int, array<string, mixed>> $history */
        $history = $this->behaviorRepository->getUserPurchaseHistory($user, 10)
            ->map(
                /** @return array<string, mixed> */
                static function (Order $order): array {
                    return [
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'status' => $order->status,
                        'created_at' => $order->created_at,
                        'products' => $order->items->map(
                            /** @return array<string, mixed> */
                            static function (OrderItem $item): array {
                                $product = $item->product;

                                return [
                                    'name' => $product ? $product->name : '',
                                    'price' => $item->unit_price,
                                    'quantity' => $item->quantity,
                                ];
                            }
                        )->toArray(),
                    ];
                }
            )
            ->toArray()
        ;

        return $history;
    }

    /**
     * Get user's browsing patterns.
     *
     * @return array<array<int>|int>
     *
     * @psalm-return array{page_views: int<0, max>, product_views: int<0, max>, search_queries: int<0, max>, cart_additions: int<0, max>, wishlist_additions: int<0, max>, most_viewed_categories: array<int, int>, peak_activity_hours: array<int, int>}
     */
    private function getBrowsingPatterns(User $user): array
    {
        $behaviors = $this->behaviorRepository->getUserBehaviors($user, 30);

        $patterns = [
            'page_views' => $behaviors->where('action', 'page_view')->count(),
            'product_views' => $behaviors->where('action', 'product_view')->count(),
            'search_queries' => $behaviors->where('action', 'search')->count(),
            'cart_additions' => $behaviors->where('action', 'cart_add')->count(),
            'wishlist_additions' => $behaviors->where('action', 'wishlist_add')->count(),
        ];

        $patterns['most_viewed_categories'] = $this->getMostViewedCategories($user);
        $patterns['peak_activity_hours'] = $this->getPeakActivityHours($user);

        return $patterns;
    }

    /**
     * Get user preferences based on purchase history.
     *
     * @return array<array|mixed>
     *
     * @psalm-return array{preferred_categories?: mixed, preferred_brands?: mixed, price_range?: array{min: mixed, max: mixed, average: mixed}}
     */
    private function getUserPreferences(User $user): array
    {
        $purchases = $this->behaviorRepository->getUserOrderItems($user);

        if ($purchases->isEmpty()) {
            return [];
        }

        $categories = $purchases->groupBy(static function (OrderItem $item): int {
            $product = $item->product;

            return $product && $product->category_id ? $product->category_id : 0;
        })
            ->map(static function ($items) {
                return $items->sum('quantity');
            })
            ->sortDesc()
            ->take(5)
        ;

        $brands = $purchases->groupBy(static function (OrderItem $item): int {
            $product = $item->product;

            return $product && $product->brand_id ? $product->brand_id : 0;
        })
            ->map(static function ($items) {
                return $items->sum('quantity');
            })
            ->sortDesc()
            ->take(5)
        ;

        $priceRange = $purchases->map(static function (OrderItem $item) {
            $product = $item->product;

            return $product ? $product->price : 0;
        });

        return [
            'preferred_categories' => $categories->keys()->toArray(),
            'preferred_brands' => $brands->keys()->toArray(),
            'price_range' => [
                'min' => $priceRange->min(),
                'max' => $priceRange->max(),
                'average' => $priceRange->avg(),
            ],
        ];
    }

    /**
     * Calculate user engagement score (0-1).
     */
    private function calculateEngagementScore(User $user): float
    {
        $behaviors = DB::table('user_behaviors')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
        ;

        $score = 0;

        // Page views (weight: 1)
        $score += $behaviors->where('action', 'page_view')->count() * 1;

        // Product views (weight: 2)
        $score += $behaviors->where('action', 'product_view')->count() * 2;

        // Search queries (weight: 3)
        $score += $behaviors->where('action', 'search')->count() * 3;

        // Cart additions (weight: 5)
        $score += $behaviors->where('action', 'cart_add')->count() * 5;

        // Purchases (weight: 10)
        $score += Order::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count() * 10
        ;

        return min($score / 100, 1.0); // Normalize to 0-1
    }

    /**
     * Calculate user's lifetime value.
     */
    private function calculateLifetimeValue(User $user): float
    {
        $sum = Order::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount')
        ;

        return is_numeric($sum) ? (float) $sum : 0.0;
    }

    /**
     * Calculate recommendation score.
     */
    private function calculateRecommendationScore(User $user): float
    {
        $engagementScore = $this->calculateEngagementScore($user);
        $lifetimeValue = $this->calculateLifetimeValue($user);
        $purchaseFrequency = $this->getPurchaseFrequency($user);

        return ($engagementScore * 0.4) + (min($lifetimeValue / 1000, 1) * 0.3) + (min($purchaseFrequency, 1) * 0.3);
    }

    /**
     * Get purchase frequency.
     */
    private function getPurchaseFrequency(User $user): float
    {
        $firstPurchaseValue = Order::where('user_id', $user->id)->min('created_at');
        $firstPurchase = \is_string($firstPurchaseValue) ? $firstPurchaseValue : null;

        if (! $firstPurchase) {
            return 0;
        }

        $daysSinceFirstPurchase = now()->diffInDays($firstPurchase);
        $totalPurchases = Order::where('user_id', $user->id)->count();

        return $daysSinceFirstPurchase > 0 ? $totalPurchases / $daysSinceFirstPurchase : 0;
    }

    /**
     * Get conversion rate.
     */
    private function getConversionRate(): float
    {
        $conversionData = $this->behaviorRepository->getConversionRateData(30);

        return $conversionData['total_visitors'] > 0
            ? $conversionData['total_buyers'] / $conversionData['total_visitors'] * 100
            : 0;
    }

    /**
     * Get most viewed products.
     *
     * @return array<array<int|string|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     *
     * @psalm-return array<int, array<string, int|string|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    private function getMostViewedProducts(): array
    {
        return $this->behaviorRepository->getMostViewedProducts(10)->toArray();
    }

    /**
     * Get top selling products.
     *
     * @return array<array<int|string|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     *
     * @psalm-return array<int, array<string, int|string|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    private function getTopSellingProducts(): array
    {
        return $this->behaviorRepository->getTopSellingProducts(10)->map(static function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'purchase_count' => $product->order_items_sum_quantity ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get most viewed categories for user.
     *
     * @return array<int, int>
     */
    private function getMostViewedCategories(User $user): array
    {
        $productViews = $this->behaviorRepository->getUserBehaviorsByAction($user, 'product_view', 30);

        // Extract all unique product IDs from views (prevent N+1 query)
        $productIds = collect($productViews)
            ->map(static function ($view) {
                $data = json_decode($view->data, true);

                return $data['product_id'] ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Fetch all products in a single query
        $products = Product::whereIn('id', $productIds)
            ->select('id', 'category_id')
            ->get()
            ->keyBy('id');

        // Count category views
        $categoryViews = [];
        foreach ($productViews as $view) {
            $data = json_decode($view->data, true);
            if (isset($data['product_id'])) {
                $product = $products->get($data['product_id']);
                if ($product && $product->category_id) {
                    $categoryViews[$product->category_id] = ($categoryViews[$product->category_id] ?? 0) + 1;
                }
            }
        }

        arsort($categoryViews);

        return array_keys(\array_slice($categoryViews, 0, 5, true));
    }

    /**
     * Get peak activity hours for user.
     *
     * @return array<int, int>
     */
    private function getPeakActivityHours(User $user): array
    {
        return $this->behaviorRepository->getUserBehaviorsByHour($user, 30);
    }
}
