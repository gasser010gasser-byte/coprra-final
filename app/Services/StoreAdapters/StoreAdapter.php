<?php

declare(strict_types=1);

namespace App\Services\StoreAdapters;

use App\Contracts\StoreAdapter as StoreAdapterInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for store adapters.
 */
abstract class StoreAdapter implements StoreAdapterInterface
{
    protected ?string $lastError = null;

    protected int $timeout = 30;

    protected int $retries = 3;

    public function __construct(
        protected readonly HttpFactory $http,
        protected readonly CacheRepository $cache,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get the store name.
     */
    abstract public function getStoreName(): string;

    /**
     * Get the store identifier.
     */
    abstract public function getStoreIdentifier(): string;

    /**
     * Check if the adapter is available and configured.
     */
    abstract public function isAvailable(): bool;

    /**
     * Fetch product data by product identifier.
     */
    abstract public function fetchProduct(string $productIdentifier): ?array;

    /**
     * Search for products by query.
     */
    abstract public function searchProducts(string $query, array $options = []): array;

    /**
     * Validate product identifier format.
     */
    abstract public function validateIdentifier(string $identifier): bool;

    /**
     * Get the product URL from identifier.
     */
    abstract public function getProductUrl(string $identifier): string;

    /**
     * Get rate limit information.
     */
    abstract public function getRateLimits(): array;

    /**
     * Make HTTP request with retry logic.
     *
     * @param  array<string, string|int|float|array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>  $options
     *
     * @return array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>|null
     */
    protected function makeRequest(string $url, array $options = []): ?array
    {
        $this->lastError = null;

        for ($attempt = 1; $attempt <= $this->retries; ++$attempt) {
            try {
                $response = $this->http->timeout($this->timeout)
                    ->retry($this->retries, 100)
                    ->get($url, $options)
                ;

                if ($response->successful()) {
                    $data = $response->json();

                    return \is_array($data) ? $data : null;
                }

                $this->lastError = "HTTP {$response->status()}: {$response->body()}";

                $this->logger->warning('Store adapter request failed', [
                    'store' => $this->getStoreName(),
                    'url' => $url,
                    'status' => $response->status(),
                    'attempt' => $attempt,
                ]);
            } catch (\Exception $exception) {
                $this->lastError = $exception->getMessage();

                $this->logger->error('Store adapter request exception', [
                    'store' => $this->getStoreName(),
                    'url' => $url,
                    'error' => $exception->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt === $this->retries) {
                    return null;
                }

                sleep(1); // Wait before retry
            }
        }

        return null;
    }

    /**
     * Cache product data.
     *
     * @param  array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>  $data
     */
    protected function cacheProduct(string $identifier, array $data, int $ttl = 3600): void
    {
        $key = $this->getCacheKey($identifier);
        $this->cache->put($key, $data, $ttl);
    }

    /**
     * Get cached product data.
     *
     * @return array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>|null
     */
    protected function getCachedProduct(string $identifier): ?array
    {
        $key = $this->getCacheKey($identifier);
        $cached = $this->cache->get($key);

        return \is_array($cached) ? $cached : null;
    }

    /**
     * Get cache key for product.
     */
    protected function getCacheKey(string $identifier): string
    {
        return "store_adapter:{$this->getStoreIdentifier()}:{$identifier}";
    }

    /**
     * Normalize product data to standard format.
     *
     * @param  array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>  $rawData
     *
     * @return array<array|scalar|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|scalar|null, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|scalar|null, brand: array|scalar|null, category: array|scalar|null, metadata: array|scalar}
     */
    protected function normalizeProductData(array $rawData): array
    {
        $price = $rawData['price'] ?? 0;
        $rating = $rawData['rating'] ?? null;
        $reviewsCount = $rawData['reviews_count'] ?? null;

        return [
            'name' => $rawData['name'] ?? '',
            'price' => is_numeric($price) ? (float) $price : 0.0,
            'currency' => $rawData['currency'] ?? 'USD',
            'url' => $rawData['url'] ?? '',
            'image_url' => $rawData['image_url'] ?? null,
            'availability' => $rawData['availability'] ?? 'unknown',
            'rating' => is_numeric($rating) ? (float) $rating : null,
            'reviews_count' => is_numeric($reviewsCount) ? (int) $reviewsCount : null,
            'description' => $rawData['description'] ?? null,
            'brand' => $rawData['brand'] ?? null,
            'category' => $rawData['category'] ?? null,
            'metadata' => $rawData['metadata'] ?? [],
        ];
    }
}
