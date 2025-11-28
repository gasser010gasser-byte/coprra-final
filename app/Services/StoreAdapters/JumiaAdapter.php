<?php

declare(strict_types=1);

namespace App\Services\StoreAdapters;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Jumia store adapter (African e-commerce).
 */
final class JumiaAdapter extends StoreAdapter
{
    private readonly string $apiKey;

    private readonly string $country;

    public function __construct(HttpFactory $http, CacheRepository $cache, LoggerInterface $logger)
    {
        parent::__construct($http, $cache, $logger);
        $apiKey = config('services.jumia.api_key', '');
        $this->apiKey = \is_string($apiKey) ? $apiKey : '';

        $country = config('services.jumia.country', 'ng'); // ng, ke, eg
        $this->country = \is_string($country) ? $country : 'ng';
    }

    /**
     * @psalm-return 'Jumia'
     */
    public function getStoreName(): string
    {
        return 'Jumia';
    }

    /**
     * @psalm-return 'jumia'
     */
    #[\Override]
    public function getStoreIdentifier(): string
    {
        return 'jumia';
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
            $normalized = $this->normalizeJumiaData($dummyData);
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
        // Jumia SKU format: alphanumeric
        return 1 === preg_match('/^[A-Z0-9-]+$/', $identifier);
    }

    #[\Override]
    public function getProductUrl(string $identifier): string
    {
        $domain = $this->getJumiaDomain();

        return "https://www.{$domain}/product/{$identifier}";
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 25,
            'requests_per_hour' => 800,
            'requests_per_day' => 8000,
        ];
    }

    /**
     * Normalize Jumia product data.
     *
     * @param array<string, mixed> $jumiaData
     *
     * @return array<array|scalar|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|scalar|null, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|scalar|null, brand: array|scalar|null, category: array|scalar|null, metadata: array|scalar}
     */
    private function normalizeJumiaData(array $jumiaData): array
    {
        $price = $jumiaData['price'] ?? 0;

        return $this->normalizeProductData([
            'name' => $jumiaData['name'] ?? '',
            'price' => is_numeric($price) ? (float) $price : 0.0,
            'currency' => $this->getCurrency(),
            'url' => $jumiaData['url'] ?? $this->getProductUrl($jumiaData['sku'] ?? ''),
            'image_url' => $jumiaData['image_url'] ?? null,
            'availability' => ($jumiaData['in_stock'] ?? false) ? 'in_stock' : 'out_of_stock',
            'rating' => $jumiaData['rating'] ?? null,
            'reviews_count' => $jumiaData['reviews_count'] ?? null,
            'description' => $jumiaData['description'] ?? null,
            'brand' => $jumiaData['brand'] ?? null,
            'category' => $jumiaData['category'] ?? null,
            'metadata' => [
                'sku' => $jumiaData['sku'] ?? '',
                'seller' => $jumiaData['seller'] ?? null,
                'discount_percentage' => $jumiaData['discount_percentage'] ?? null,
            ],
        ]);
    }

    /**
     * Get currency based on country.
     *
     * @psalm-return 'NGN'|'KES'|'EGP'
     */
    private function getCurrency(): string
    {
        return match ($this->country) {
            'ng' => 'NGN',
            'ke' => 'KES',
            'eg' => 'EGP',
            default => 'NGN',
        };
    }

    /**
     * Get Jumia domain based on country.
     */
    private function getJumiaDomain(): string
    {
        return match ($this->country) {
            'ng' => 'jumia.com.ng',
            'ke' => 'jumia.co.ke',
            'eg' => 'jumia.com.eg',
            default => 'jumia.com.ng',
        };
    }

    /**
     * Generate dummy product data for demonstration.
     *
     * @return array<string, mixed>
     */
    private function generateDummyData(string $productIdentifier): array
    {
        $basePrice = 199.99 + (crc32($productIdentifier) % 800);
        $salePrice = $basePrice * 0.80; // 20% discount
        $price = round($salePrice, 2);

        return [
            'name' => "Jumia Product {$productIdentifier} - Best Value",
            'price' => $price,
            'sku' => $productIdentifier,
            'url' => $this->getProductUrl($productIdentifier),
            'image_url' => 'https://via.placeholder.com/500x500?text=Jumia+Product',
            'in_stock' => true,
            'rating' => 4.2 + (crc32($productIdentifier) % 15) / 10,
            'reviews_count' => 300 + (crc32($productIdentifier) % 1500),
            'description' => 'Quality product available on Jumia with fast delivery across Africa.',
            'brand' => 'Trusted Brand',
            'category' => 'Electronics',
            'seller' => 'Jumia Official Store',
            'discount_percentage' => 20,
        ];
    }
}
