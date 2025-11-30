<?php

declare(strict_types=1);

namespace App\Services\StoreAdapters;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Noon store adapter (Middle East e-commerce).
 */
final class NoonAdapter extends StoreAdapter
{
    private readonly string $apiKey;

    private readonly string $country;

    public function __construct(HttpFactory $http, CacheRepository $cache, LoggerInterface $logger)
    {
        parent::__construct($http, $cache, $logger);
        $apiKey = config()->get('services.noon.api_key');
        $this->apiKey = \is_string($apiKey) ? $apiKey : '';

        $country = config()->get('services.noon.country');
        $this->country = \is_string($country) ? $country : 'ae'; // ae, sa, eg
    }

    /**
     * @psalm-return 'Noon'
     */
    public function getStoreName(): string
    {
        return 'Noon';
    }

    /**
     * @psalm-return 'noon'
     */
    #[\Override]
    public function getStoreIdentifier(): string
    {
        return 'noon';
    }

    #[\Override]
    public function isAvailable(): bool
    {
        // Always return true for dummy data mode
        return true;
    }

    /**
     * Fetch product from Noon Web (Scraping fallback).
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromNoonWeb(string $sku): ?array
    {
        try {
            $domain = $this->getNoonDomain();
            $url = "https://www.{$domain}/product/{$sku}";

            // Mimic a real browser
            $response = $this->http->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ])->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // Noon uses Next.js, so data is often in __NEXT_DATA__ script
            preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches);

            if (isset($matches[1])) {
                $jsonData = json_decode($matches[1], true);
                $productData = data_get($jsonData, 'props.pageProps.catalog.product.product');

                if ($productData) {
                    return [
                        'name' => $productData['title'] ?? '',
                        'price' => $productData['price_now'] ?? 0,
                        'sale_price' => $productData['price_now'] ?? 0,
                        'sku' => $sku,
                        'url' => $url,
                        'image_url' => data_get($productData, 'images.image_key.0') ? "https://f.nooncdn.com/products/tr:n-t_400/" . data_get($productData, 'images.image_key.0') . ".jpg" : null,
                        'in_stock' => ($productData['stock_gross'] ?? 0) > 0,
                        'rating' => $productData['product_rating']['value'] ?? 0,
                        'reviews_count' => $productData['product_rating']['count'] ?? 0,
                        'description' => strip_tags($productData['feature_bullets'] ?? ''),
                        'brand' => $productData['brand']['name'] ?? 'Unknown',
                        'category' => data_get($productData, 'breadcrumbs.0.name', 'General'),
                        'seller' => 'Noon',
                        'discount_percentage' => 0,
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error('Noon web scrape failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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

        // Try Web Scraping first
        $scrapedData = $this->fetchFromNoonWeb($productIdentifier);
        if ($scrapedData) {
            $normalized = $this->normalizeNoonData($scrapedData);
            $this->cacheProduct($productIdentifier, $normalized, 3600);

            return $normalized;
        }

        // Fallback to dummy data
        $dummyData = $this->generateDummyData($productIdentifier);
        if ($dummyData) {
            $normalized = $this->normalizeNoonData($dummyData);
            $this->cacheProduct($productIdentifier, $normalized, 300); // Short cache

            return $normalized;
        }

        return null;
    }

    /**
     * Generate dummy product data for demonstration.
     *
     * @return array<string, mixed>
     */
    private function generateDummyData(string $productIdentifier): array
    {
        $basePrice = 149.99 + (crc32($productIdentifier) % 600);
        $salePrice = $basePrice * 0.85; // 15% discount
        $price = round($salePrice, 2);

        return [
            'name' => "(Mock) Noon Product {$productIdentifier} - Scrape Failed",
            'price' => $basePrice,
            'sale_price' => $price,
            'sku' => $productIdentifier,
            'url' => $this->getProductUrl($productIdentifier),
            'image_url' => 'https://via.placeholder.com/500x500?text=Scrape+Failed',
            'in_stock' => true,
            'rating' => 4.0,
            'reviews_count' => 50,
            'description' => 'This is a placeholder because live scraping failed.',
            'brand' => 'Demo Brand',
            'category' => 'Electronics',
            'seller' => 'Noon Official Store',
            'discount_percentage' => 15,
        ];
    }

    /**
     * @return array<int, array<string, scalar|array|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     *
     * @psalm-return list<non-empty-array<string, scalar|array|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    public function searchProducts(string $query, array $options = []): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $url = 'https://api.noon.com/v1/search';

        $params = [
            'q' => $query,
            'api_key' => $this->apiKey,
            'limit' => 20,
            'page' => 1,
        ];

        /** @var array<string, array>|null $response */
        $response = $this->makeRequest($url, $params);

        if ($response && isset($response['products'])) {
            /** @var array<array<string, array>> $products */
            $products = $response['products'];

            return array_values(array_map(
                fn (array $product): array => $this->normalizeNoonData($product),
                $products
            ));
        }

        return [];
    }

    public function validateIdentifier(string $identifier): bool
    {
        // Noon SKU format: N followed by numbers
        return 1 === preg_match('/^N\d+$/', $identifier);
    }

    #[\Override]
    public function getProductUrl(string $identifier): string
    {
        $domain = $this->getNoonDomain();

        return "https://www.{$domain}/product/{$identifier}";
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 30,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000,
        ];
    }

    /**
     * Build API URL for product.
     *
     * @phpstan-ignore-next-line
     */
    private function buildApiUrl(string $sku): string
    {
        return "https://api.noon.com/v1/products/{$sku}";
    }

    /**
     * Normalize Noon product data.
     *
     * @param  array<string, array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>|string|int|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>  $noonData
     *
     * @return array<array|scalar|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|scalar|null, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|scalar|null, brand: array|scalar|null, category: array|scalar|null, metadata: array|scalar}
     */
    private function normalizeNoonData(array $noonData): array
    {
        $price = $noonData['sale_price'] ?? $noonData['price'] ?? 0;
        $sku = \is_string($noonData['sku'] ?? null) ? $noonData['sku'] : '';

        return $this->normalizeProductData([
            'name' => $noonData['name'] ?? '',
            'price' => is_numeric($price) ? (float) $price : 0.0,
            'currency' => $this->getCurrency(),
            'url' => $noonData['url'] ?? $this->getProductUrl($sku),
            'image_url' => $noonData['image_url'] ?? null,
            'availability' => $noonData['in_stock'] ?? false ? 'in_stock' : 'out_of_stock',
            'rating' => $noonData['rating'] ?? null,
            'reviews_count' => $noonData['reviews_count'] ?? null,
            'description' => $noonData['description'] ?? null,
            'brand' => $noonData['brand'] ?? null,
            'category' => $noonData['category'] ?? null,
            'metadata' => [
                'sku' => $noonData['sku'] ?? '',
                'seller' => $noonData['seller'] ?? null,
                'discount_percentage' => $noonData['discount_percentage'] ?? null,
            ],
        ]);
    }

    /**
     * Get currency based on country.
     *
     * @psalm-return 'AED'|'EGP'|'SAR'
     */
    private function getCurrency(): string
    {
        return match ($this->country) {
            'ae' => 'AED',
            'sa' => 'SAR',
            'eg' => 'EGP',
            default => 'AED',
        };
    }

    /**
     * Get Noon domain based on country.
     */
    private function getNoonDomain(): string
    {
        return match ($this->country) {
            'ae' => 'noon.com/uae-en',
            'sa' => 'noon.com/saudi-en',
            'eg' => 'noon.com/egypt-en',
            default => 'noon.com',
        };
    }
}
