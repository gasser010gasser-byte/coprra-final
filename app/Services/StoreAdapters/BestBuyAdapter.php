<?php

declare(strict_types=1);

namespace App\Services\StoreAdapters;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Best Buy store adapter.
 */
final class BestBuyAdapter extends StoreAdapter
{
    private readonly string $apiKey;

    public function __construct(HttpFactory $http, CacheRepository $cache, LoggerInterface $logger)
    {
        parent::__construct($http, $cache, $logger);
        $apiKey = config('services.bestbuy.api_key', '');
        $this->apiKey = \is_string($apiKey) ? $apiKey : '';
    }

    /**
     * @psalm-return 'Best Buy'
     */
    public function getStoreName(): string
    {
        return 'Best Buy';
    }

    /**
     * @psalm-return 'bestbuy'
     */
    #[\Override]
    public function getStoreIdentifier(): string
    {
        return 'bestbuy';
    }

    #[\Override]
    public function isAvailable(): bool
    {
        // Always return true for dummy data mode
        return true;
    }

    #[\Override]
    public function fetchProduct(string $productIdentifier): ?array
    {
        // Check cache first
        /** @var array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|scalar|null, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|scalar|null, brand: array|scalar|null, category: array|scalar|null, metadata: array|scalar}|null $cached */
        $cached = $this->getCachedProduct($productIdentifier);
        if (\is_array($cached)) {
            return $cached;
        }

        // Return dummy data for demonstration
        $dummyData = $this->generateDummyData($productIdentifier);
        if ($dummyData) {
            $normalized = $this->normalizeBestBuyData($dummyData);
            $this->cacheProduct($productIdentifier, $normalized, 3600);

            return $normalized;
        }

        return null;
    }

    /**
     * @return array<int, array<string, scalar|array|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     *
     * @psalm-return list<non-empty-array<string, scalar|array|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    public function searchProducts(string $query, array $options = []): array
    {
        // Return empty array for search (can be implemented later)
        return [];
    }

    public function validateIdentifier(string $identifier): bool
    {
        // Best Buy SKU format: numeric, typically 8-10 digits
        return 1 === preg_match('/^\d{8,10}$/', $identifier);
    }

    #[\Override]
    public function getProductUrl(string $identifier): string
    {
        return "https://www.bestbuy.com/site/product/{$identifier}.p";
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 15,
            'requests_per_hour' => 600,
            'requests_per_day' => 5000,
        ];
    }

    /**
     * Normalize Best Buy product data.
     *
     * @param array<string, mixed> $bestBuyData
     *
     * @return array<array|scalar|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|scalar|null, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|scalar|null, brand: array|scalar|null, category: array|scalar|null, metadata: array|scalar}
     */
    private function normalizeBestBuyData(array $bestBuyData): array
    {
        $price = $bestBuyData['salePrice'] ?? $bestBuyData['regularPrice'] ?? 0;

        return $this->normalizeProductData([
            'name' => $bestBuyData['name'] ?? '',
            'price' => is_numeric($price) ? (float) $price : 0.0,
            'currency' => 'USD',
            'url' => $bestBuyData['url'] ?? $this->getProductUrl($bestBuyData['sku'] ?? ''),
            'image_url' => $bestBuyData['image'] ?? null,
            'availability' => ($bestBuyData['onlineAvailability'] ?? false) ? 'in_stock' : 'out_of_stock',
            'rating' => $bestBuyData['customerReviewAverage'] ?? null,
            'reviews_count' => $bestBuyData['customerReviewCount'] ?? null,
            'description' => $bestBuyData['shortDescription'] ?? null,
            'brand' => $bestBuyData['manufacturer'] ?? null,
            'category' => $bestBuyData['categoryPath'] ?? null,
            'metadata' => [
                'sku' => $bestBuyData['sku'] ?? '',
                'model_number' => $bestBuyData['modelNumber'] ?? null,
                'upc' => $bestBuyData['upc'] ?? null,
            ],
        ]);
    }

    /**
     * Generate dummy product data for demonstration.
     *
     * @return array<string, mixed>
     */
    private function generateDummyData(string $productIdentifier): array
    {
        $basePrice = 249.99 + (crc32($productIdentifier) % 1000);
        $salePrice = $basePrice * 0.90; // 10% discount
        $price = round($salePrice, 2);

        return [
            'name' => "Best Buy Product {$productIdentifier} - Premium Electronics",
            'salePrice' => $price,
            'regularPrice' => $basePrice,
            'sku' => $productIdentifier,
            'url' => $this->getProductUrl($productIdentifier),
            'image' => 'https://via.placeholder.com/500x500?text=BestBuy+Product',
            'onlineAvailability' => true,
            'customerReviewAverage' => 4.5 + (crc32($productIdentifier) % 10) / 10,
            'customerReviewCount' => 2000 + (crc32($productIdentifier) % 8000),
            'shortDescription' => 'Premium electronics product available at Best Buy with expert advice and installation services.',
            'manufacturer' => 'Top Brand',
            'categoryPath' => 'Electronics',
            'modelNumber' => 'MOD-' . substr($productIdentifier, 0, 6),
            'upc' => '0' . str_pad($productIdentifier, 11, '0', STR_PAD_LEFT),
        ];
    }
}
