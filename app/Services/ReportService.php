<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Reports\PriceAnalysisReportGenerator;
use App\Services\Reports\ProductPerformanceReportGenerator;
use App\Services\Reports\UserActivityReportGenerator;
use Carbon\Carbon;

/**
 * Main report service that coordinates different report generators.
 * Acts as a facade for various specialized report generation services.
 *
 * Note: Sales reporting has been removed as the platform operates on an affiliate model
 * where purchases are made on external stores, not directly through the platform.
 */
final readonly class ReportService
{
    public function __construct(
        private ProductPerformanceReportGenerator $productPerformanceGenerator,
        private UserActivityReportGenerator $userActivityGenerator,
        private PriceAnalysisReportGenerator $priceAnalysisGenerator
    ) {
    }

    /**
     * Generate product performance report.
     *
     * @return array{
     *     product_id: int,
     *     name: string,
     *     price_stats: array<string, mixed>,
     *     offer_availability: array<string, mixed>,
     *     review_analysis: array<string, mixed>,
     *     engagement_metrics: array<string, mixed>,
     *     price_trends: array<int, array<string, mixed>>,
     *     top_products: array<int, array<string, mixed>>
     * }
     */
    public function generateProductPerformanceReport(
        int $productId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $endDate ??= Carbon::now();
        $startDate ??= $endDate->copy()->subDays(30);

        return $this->productPerformanceGenerator->generateReport($productId, $startDate, $endDate);
    }

    /**
     * Generate user activity report.
     *
     * @return array{
     *     total_users_active: int,
     *     wishlist_activity: array<int, array<string, mixed>>,
     *     price_alerts_activity: array<int, array<string, mixed>>,
     *     reviews_activity: array<int, array<string, mixed>>,
     *     engagement_summary: array<string, mixed>
     * }
     */
    public function generateUserActivityReport(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate ??= now()->subMonth();
        $endDate ??= now();

        return $this->userActivityGenerator->generateReport($startDate, $endDate);
    }

    /**
     * Generate sales report.
     *
     * @deprecated Sales reporting has been removed as the platform operates on an affiliate model.
     * This method returns an empty report structure for backward compatibility.
     *
     * @return array{
     *     total_orders: int,
     *     total_revenue: float,
     *     average_order_value: float,
     *     top_selling_products: array<int, array<string, mixed>>,
     *     sales_by_period: array<string, array<string, mixed>>,
     *     order_status_breakdown: array<string, int>,
     *     revenue_trends: array<string, float>
     * }
     */
    public function generateSalesReport(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        // Sales reporting removed - platform is affiliate-based, no direct sales
        return [
            'total_orders' => 0,
            'total_revenue' => 0.0,
            'average_order_value' => 0.0,
            'top_selling_products' => [],
            'sales_by_period' => [],
            'order_status_breakdown' => [],
            'revenue_trends' => [],
        ];
    }

    /**
     * Generate price analysis report.
     *
     * @return array{
     *     price_changes_summary: array<string, mixed>,
     *     trending_products: array<int, array<string, mixed>>,
     *     price_volatility_analysis: array<int, array<string, mixed>>,
     *     market_trends: array<string, mixed>,
     *     price_alerts_triggered: int
     * }
     */
    public function generatePriceAnalysisReport(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate ??= now()->subMonth();
        $endDate ??= now();

        return $this->priceAnalysisGenerator->generateReport($startDate, $endDate);
    }

    /**
     * Generate comprehensive dashboard report combining all report types.
     *
     * @return array{
     *     sales_summary: array<string, mixed>,
     *     user_activity_summary: array<string, mixed>,
     *     price_analysis_summary: array<string, mixed>,
     *     generated_at: string
     * }
     */
    public function generateDashboardReport(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate ??= now()->subMonth();
        $endDate ??= now();

        return [
            'sales_summary' => $this->generateSalesReport($startDate, $endDate),
            'user_activity_summary' => $this->userActivityGenerator->generateReport($startDate, $endDate),
            'price_analysis_summary' => $this->priceAnalysisGenerator->generateReport($startDate, $endDate),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get daily sales summary.
     *
     * @deprecated Sales reporting has been removed as the platform operates on an affiliate model.
     */
    public function getDailySalesSummary(Carbon $date): array
    {
        return [
            'date' => $date->toDateString(),
            'orders_count' => 0,
            'revenue' => 0.0,
            'average_order_value' => 0.0,
        ];
    }

    /**
     * Get user activity summary for a specific user.
     */
    public function getUserActivitySummary(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        return $this->userActivityGenerator->getUserActivitySummary($userId, $startDate, $endDate);
    }

    /**
     * Get price trends for a specific product.
     */
    public function getProductPriceTrends(int $productId, Carbon $startDate, Carbon $endDate): array
    {
        return $this->priceAnalysisGenerator->getProductPriceTrends($productId, $startDate, $endDate);
    }

    /**
     * Get top selling products.
     *
     * @deprecated Sales reporting has been removed as the platform operates on an affiliate model.
     */
    public function getTopSellingProducts(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        return [];
    }

    /**
     * Get customer analysis.
     *
     * @deprecated Sales reporting has been removed as the platform operates on an affiliate model.
     */
    public function getCustomerAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'total_customers' => 0,
            'new_customers' => 0,
            'returning_customers' => 0,
            'average_orders_per_customer' => 0.0,
            'customer_lifetime_value' => 0.0,
        ];
    }
}
