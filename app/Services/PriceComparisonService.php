<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Store;

final readonly class PriceComparisonService
{
    public function __construct(
        private StoreAdapterManager $storeAdapterManager
    ) {
    }

    /**
     * Fetch prices from all available stores.
     *
     * @return array<int, array<string, string|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    public function fetchPricesFromStores(Product $product): array
    {
        $prices = [];

        /** @var array<string, string>|null $storeMappings */
        $storeMappings = $product->store_mappings ?? null;

        // If store_mappings exists, use them
        if (\is_array($storeMappings) && ! empty($storeMappings)) {
            foreach ($storeMappings as $storeIdentifier => $productIdentifier) {
                $productData = $this->storeAdapterManager->fetchProduct(
                    $storeIdentifier,
                    $productIdentifier
                );

                if ($productData) {
                    $prices[] = $this->buildPriceArray($storeIdentifier, $productData);
                }
            }
        } else {
            // If no store_mappings, fetch from all available adapters using product slug/ID as identifier
            $availableAdapters = $this->storeAdapterManager->getAvailableAdapters();

            foreach ($availableAdapters as $storeIdentifier => $adapter) {
                // Use product slug or ID as identifier for dummy data
                $productIdentifier = $product->slug ?? (string) $product->id;

                $productData = $adapter->fetchProduct($productIdentifier);

                if ($productData) {
                    $prices[] = $this->buildPriceArray($storeIdentifier, $productData);
                }
            }
        }

        return $prices;
    }

    /**
     * Build price array from product data.
     *
     * @param array<string, mixed> $productData
     * @return array<string, string|float|bool|null>
     */
    private function buildPriceArray(string $storeIdentifier, array $productData): array
    {
        $price = isset($productData['price']) && is_numeric($productData['price']) ? (float) $productData['price'] : 0.0;
        $currency = isset($productData['currency']) && \is_string($productData['currency']) ? $productData['currency'] : 'USD';
        $inStock = isset($productData['availability']) && 'in_stock' === $productData['availability'];
        $originalUrl = $productData['url'] ?? '';

        // Generate affiliate URL using Store model
        $affiliateUrl = $this->generateAffiliateUrlForStore($storeIdentifier, $originalUrl);

        return [
            'store_name' => $this->getStoreName($storeIdentifier),
            'store_identifier' => $storeIdentifier,
            'store_logo' => $this->getStoreLogo($storeIdentifier),
            'price' => $price,
            'formatted_price' => $this->formatPrice($price, $currency),
            'currency' => $currency,
            'original_price' => null,
            'url' => $affiliateUrl,
            'in_stock' => $inStock,
            'rating' => $productData['rating'] ?? null,
            'reviews_count' => $productData['reviews_count'] ?? null,
            'shipping_cost' => null,
            'is_best_deal' => false,
        ];
    }

    /**
     * Mark the best deal in prices array.
     *
     * @param  array<int, array<string, string|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>>  $deals
     *
     * @return array<int, array<string, string|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    public function markBestDeal(array $deals): array
    {
        $filtered = array_filter($deals, static fn (array $item): bool => isset($item['price'], $item['in_stock'], $item['is_best_deal'])
            && is_numeric($item['price'])
            && \is_bool($item['in_stock'])
            && \is_bool($item['is_best_deal']));

        if ([] === $filtered) {
            return $deals;
        }

        $inStockPrices = array_filter($filtered, static fn (array $item): bool|float|string => $item['in_stock'] ?? false);

        if ([] === $inStockPrices) {
            return $deals;
        }

        $pricesArray = array_column($inStockPrices, 'price');
        $lowestPrice = min($pricesArray);

        foreach ($deals as &$price) {
            if (($price['in_stock'] ?? false) && ($price['price'] ?? null) === $lowestPrice) {
                $price['is_best_deal'] = true;

                break;
            }
        }

        return $deals;
    }

    private function getStoreName(string $identifier): string
    {
        return match ($identifier) {
            'amazon' => 'Amazon',
            'ebay' => 'eBay',
            'noon' => 'Noon',
            'jumia' => 'Jumia',
            'bestbuy' => 'BestBuy',
            default => ucfirst($identifier),
        };
    }

    private function getStoreLogo(string $identifier): ?string
    {
        $logos = [
            'amazon' => asset('images/stores/amazon.png'),
            'ebay' => asset('images/stores/ebay.png'),
            'noon' => asset('images/stores/noon.png'),
            'jumia' => asset('images/stores/jumia.png'),
            'bestbuy' => asset('images/stores/bestbuy.png'),
        ];

        return $logos[$identifier] ?? null;
    }

    private function formatPrice(float $price, string $currency): string
    {
        return number_format($price, 2).' '.$currency;
    }

    /**
     * Generate affiliate URL for a store using Store model's generateAffiliateUrl method.
     */
    private function generateAffiliateUrlForStore(string $storeIdentifier, string $productUrl): string
    {
        if (empty($productUrl)) {
            return '';
        }

        // Find store by identifier (slug or name)
        $store = Store::where('slug', $storeIdentifier)
            ->orWhere('name', 'like', "%{$storeIdentifier}%")
            ->first();

        // If store found, use its generateAffiliateUrl method
        if ($store) {
            return $store->generateAffiliateUrl($productUrl);
        }

        // Fallback: use placeholder mechanism directly
        $separator = strpos($productUrl, '?') !== false ? '&' : '?';

        return $productUrl . $separator . 'ref=coprra';
    }
}
