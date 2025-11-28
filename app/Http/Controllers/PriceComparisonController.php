<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Services\AnalyticsService;
use App\Services\CacheService;
use App\Services\GeolocationService;
use App\Services\PriceComparisonService;
use App\Services\StoreAdapterManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceComparisonController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly CacheService $cacheService,
        private readonly StoreAdapterManager $storeAdapterManager,
        private readonly PriceComparisonService $priceComparisonService,
        private readonly GeolocationService $geolocationService
    ) {
    }

    /**
     * Show price comparison for a product.
     */
    public function show(Request $request, string $product): View
    {
        // Resolve product by slug
        $productModel = Product::where('slug', $product)->firstOrFail();

        $this->analyticsService->trackPriceComparison(
            $productModel->id,
            auth()->check() ? (int) auth()->id() : null
        );

        /** @var array<int, array<string, string|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>> $prices */
        $prices = $this->cacheService->getCachedPriceComparison($productModel->id);

        if (! $prices) {
            $prices = $this->priceComparisonService->fetchPricesFromStores($productModel);
            $this->cacheService->cachePriceComparison($productModel->id, $prices);
        }

        $prices = $this->priceComparisonService->markBestDeal($prices);

        // Apply geolocation filtering
        $prices = $this->filterPricesByCountry($prices, $request);

        $showHistory = $request->boolean('history', false);
        $priceHistory = $showHistory ? $this->getPriceHistory() : [];

        $isWishlisted = auth()->check()
            ? auth()->user()->wishlist()->where('products.id', $productModel->id)->exists()
            : false;

        return view('products.price-comparison', [
            'product' => $productModel,
            'prices' => $prices,
            'showHistory' => $showHistory,
            'priceHistory' => $priceHistory,
            'availableStores' => $this->storeAdapterManager->getAvailableStores(),
            'isWishlisted' => $isWishlisted,
        ]);
    }

    /**
     * API endpoint to refresh prices.
     */
    public function refresh(string $product): JsonResponse
    {
        // Resolve product by slug
        $productModel = Product::where('slug', $product)->firstOrFail();

        $this->cacheService->invalidateProduct($productModel->id);

        $prices = $this->priceComparisonService->fetchPricesFromStores($productModel);
        $this->cacheService->cachePriceComparison($productModel->id, $prices);

        return response()->json([
            'success' => true,
            'prices' => $this->priceComparisonService->markBestDeal($prices),
        ]);
    }

    /**
     * Get price history for product.
     *
     * @psalm-return array<never, never>
     */
    private function getPriceHistory(): array
    {
        return [];
    }

    /**
     * Filter prices by user's country based on store's supported_countries.
     *
     * @param  array<int, array<string, string|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>>  $prices
     *
     * @return array<int, array<string, string|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    private function filterPricesByCountry(array $prices, Request $request): array
    {
        // Detect user's country
        $locale = $this->geolocationService->detectLocaleFromIP($request->ip());
        $userCountryCode = $locale['country'] ?? null;

        // If no country detected, return all prices
        if (! $userCountryCode) {
            return $prices;
        }

        // Filter prices by store's supported_countries
        $filteredPrices = [];
        foreach ($prices as $price) {
            $storeIdentifier = $price['store_identifier'] ?? null;
            if (! $storeIdentifier) {
                continue;
            }

            // Find store by identifier (slug or name)
            $store = Store::where('slug', $storeIdentifier)
                ->orWhere('name', 'like', "%{$storeIdentifier}%")
                ->first();

            if (! $store) {
                // If store not found, include the price (fallback)
                $filteredPrices[] = $price;

                continue;
            }

            // Check if store supports user's country
            $supportedCountries = $store->supported_countries;
            if (empty($supportedCountries)) {
                // If no supported countries specified, include the price (assume global)
                $filteredPrices[] = $price;

                continue;
            }

            // Handle JSON array or comma-separated string
            if (is_string($supportedCountries)) {
                // Try to decode as JSON first
                $decoded = json_decode($supportedCountries, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $supportedCountries = $decoded;
                } else {
                    // Treat as comma-separated string
                    $supportedCountries = array_map('trim', explode(',', $supportedCountries));
                }
            }

            // Check if user's country is in supported countries
            if (is_array($supportedCountries) && in_array(strtoupper($userCountryCode), array_map('strtoupper', $supportedCountries), true)) {
                $filteredPrices[] = $price;
            }
        }

        return $filteredPrices;
    }
}
