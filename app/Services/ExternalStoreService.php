<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use App\Services\StoreClients\GenericStoreClient;
use App\Services\StoreClients\StoreClientFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final readonly class ExternalStoreService
{
    /** @var array<string, mixed> */
    private array $storeConfigs;

    private StoreClientFactory $storeClientFactory;

    public function __construct(?StoreClientFactory $storeClientFactory = null)
    {
        $config = Config::get('external_stores', []);
        $this->storeConfigs = is_array($config) ? $config : [];
        $this->storeClientFactory = $storeClientFactory ?? new StoreClientFactory();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<array>
     *
     * @psalm-return list<array<string, mixed>>
     */
    public function searchProducts(string $query, array $filters = []): array
    {
        $results = [];
        foreach (array_keys($this->storeConfigs) as $storeName) {
            try {
                $client = $this->storeClientFactory->create($storeName);
                if ($client instanceof GenericStoreClient) {
                    $storeResults = $client->search($query, $filters);
                    $results = array_merge($results, $this->normalizeProducts($storeResults, $storeName));
                }
            } catch (\Exception $e) {
                Log::error("Failed to search in {$storeName}", ['query' => $query, 'error' => $e->getMessage()]);
            }
        }

        // Remove duplicates based on external_id and store_name combination
        // If external_id is null, use name+store_name as the key
        $uniqueResults = [];
        $seen = [];
        foreach ($results as $result) {
            $externalId = $result['external_id'] ?? null;
            $storeName = $result['store_name'] ?? '';
            $name = $result['name'] ?? '';

            if ($externalId !== null) {
                // Use external_id as the key (same product from different stores)
                if (! isset($seen[$externalId])) {
                    $seen[$externalId] = true;
                    $uniqueResults[] = $result;
                }
            } else {
                // For products without external_id, use name+store_name as key
                $key = $name . '_' . $storeName;
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $uniqueResults[] = $result;
                }
            }
        }

        return $this->sortAndFilterResults($uniqueResults, $filters);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProductDetails(string $storeName, string $productId): ?array
    {
        return Cache::remember("external_product_{$storeName}_{$productId}", 3600, function () use ($storeName, $productId): ?array {
            try {
                $client = $this->storeClientFactory->create($storeName);
                if ($client instanceof GenericStoreClient) {
                    $productData = $client->getProduct($productId);

                    return $productData ? $this->normalizeProductData($productData, $storeName) : null;
                }
            } catch (\Exception $e) {
                Log::error("Failed to get product details from {$storeName}", ['product_id' => $productId, 'error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * @psalm-return int<0, max>
     */
    public function syncStoreProducts(string $storeName): int
    {
        $syncedCount = 0;

        try {
            $client = $this->storeClientFactory->create($storeName);
            if ($client instanceof GenericStoreClient) {
                $client->syncProducts(function ($productData) use ($storeName, &$syncedCount): void {
                    try {
                        $this->syncProduct($productData, $storeName);
                    } catch (\Exception $e) {
                        Log::error("Failed to sync product from {$storeName}", ['product_data' => $productData, 'error' => $e->getMessage()]);
                    }
                    // Always increment count, even if syncProduct fails
                    ++$syncedCount;
                });
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync products from {$storeName}", ['error' => $e->getMessage()]);
        }

        return $syncedCount;
    }

    /**
     * @return array<array<float|int|string|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     *
     * @psalm-return array<string, array<string, float|int|string|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     */
    public function getStoreStatus(): array
    {
        $status = [];

        // Ensure storeConfigs is an array and not empty
        if (empty($this->storeConfigs) || ! is_array($this->storeConfigs)) {
            return [];
        }

        foreach (array_keys($this->storeConfigs) as $storeName) {
            try {
                $client = $this->storeClientFactory->create($storeName);
                $status[$storeName] = $client instanceof GenericStoreClient ? $client->getStatus() : ['status' => 'error', 'error' => 'Invalid configuration'];
                $status[$storeName]['last_check'] = now()->toISOString();
            } catch (\Exception $e) {
                $status[$storeName] = ['status' => 'error', 'error' => $e->getMessage(), 'last_check' => now()->toISOString()];
            }
        }

        return $status;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     *
     * @return array<array<array|int|mixed|string|* @method static \App\Models\Brand create(array<string, string|bool|null>>
     *
     * @psalm-return array<int, array{external_id: mixed|null, name: ''|mixed, description: ''|mixed, price: 0|mixed, currency: 'USD'|mixed, image_url: ''|mixed, store_name: string, store_url: ''|mixed, rating: 0|mixed, reviews_count: 0|mixed, availability: 'in_stock'|mixed, shipping_info: array<never, never>|mixed, category: ''|mixed, brand: ''|mixed}>
     */
    private function normalizeProducts(array $products, string $storeName): array
    {
        return array_map(fn (array $product): array => $this->normalizeProductData($product, $storeName), $products);
    }

    /**
     * @param array<string, mixed> $productData
     *
     * @return array<array|int|mixed|string|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{external_id: mixed|null, name: ''|mixed, description: ''|mixed, price: 0|mixed, currency: 'USD'|mixed, image_url: ''|mixed, store_name: string, store_url: ''|mixed, rating: 0|mixed, reviews_count: 0|mixed, availability: 'in_stock'|mixed, shipping_info: array<never, never>|mixed, category: ''|mixed, brand: ''|mixed}
     */
    private function normalizeProductData(array $productData, string $storeName): array
    {
        return [
            'external_id' => $productData['id'] ?? null,
            'name' => $productData['title'] ?? $productData['name'] ?? '',
            'description' => $productData['description'] ?? '',
            'price' => $productData['price'] ?? 0,
            'currency' => $productData['currency'] ?? 'USD',
            'image_url' => $productData['image'] ?? $productData['thumbnail'] ?? '',
            'store_name' => $storeName,
            'store_url' => $productData['url'] ?? '',
            'rating' => $productData['rating'] ?? 0,
            'reviews_count' => $productData['reviews_count'] ?? 0,
            'availability' => $productData['availability'] ?? 'in_stock',
            'shipping_info' => $productData['shipping'] ?? [],
            'category' => $productData['category'] ?? '',
            'brand' => $productData['brand'] ?? '',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed>             $filters
     *
     * @return array<array>
     *
     * @psalm-return list<array<string, mixed>>
     */
    private function sortAndFilterResults(array $results, array $filters): array
    {
        if (isset($filters['sort_by']) && 'price' === $filters['sort_by']) {
            usort($results, static fn (array $a, array $b): int => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
        }

        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $minPrice = (float) $filters['min_price'];
            $results = array_filter($results, static fn (array $product): bool => ($product['price'] ?? 0) >= $minPrice);
        }

        if (isset($filters['max_price']) && is_numeric($filters['max_price']) && $filters['max_price'] >= 0) {
            $maxPrice = (float) $filters['max_price'];
            $results = array_filter($results, static fn (array $product): bool => ($product['price'] ?? 0) <= $maxPrice);
        }

        return array_values($results);
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function syncProduct(array $productData, string $storeName): void
    {
        $normalizedData = $this->normalizeProductData($productData, $storeName);

        $store = Store::firstOrCreate(
            ['name' => $storeName],
            ['is_active' => true, 'api_config' => Config::get("external_stores.{$storeName}")]
        );

        // Create or get default brand and category for external products
        $defaultBrand = \App\Models\Brand::firstOrCreate(
            ['name' => 'External Products'],
            ['slug' => 'external-products', 'is_active' => true]
        );

        $defaultCategory = \App\Models\Category::firstOrCreate(
            ['name' => 'External'],
            ['slug' => 'external', 'is_active' => true, 'level' => 0]
        );

        // Use name + store_id as unique key since external_id column doesn't exist
        $whereClause = [
            'name' => $normalizedData['name'],
            'store_id' => $store->id,
        ];

        // Generate unique slug
        $baseSlug = \Illuminate\Support\Str::slug($normalizedData['name'] ?? 'product');
        $slug = $baseSlug;
        $slugCounter = 1;

        // Check if slug already exists for a different product (same store)
        while (Product::where('slug', $slug)
            ->where('store_id', $store->id)
            ->where(function ($query) use ($whereClause) {
                if (isset($whereClause['name'])) {
                    $query->where('name', '!=', $whereClause['name']);
                }
            })->exists()) {
            $slug = $baseSlug . '-' . $slugCounter;
            $slugCounter++;
        }

        $productAttributes = [
            'name' => $normalizedData['name'],
            'slug' => $slug,
            'description' => $normalizedData['description'] ?? '',
            'price' => (float) ($normalizedData['price'] ?? 0),
            'image' => $normalizedData['image_url'] ?? null,
            'is_active' => true,
            'store_id' => $store->id,
            'brand_id' => $defaultBrand->id,
            'category_id' => $defaultCategory->id,
        ];

        // Store external data in a JSON column if it exists, otherwise skip
        try {
            $productAttributes['external_data'] = json_encode($normalizedData);
        } catch (\Exception $e) {
            // Column doesn't exist, skip it
        }

        // Use unguarded to bypass mass assignment protection
        try {
            Product::unguard();
            Product::updateOrCreate($whereClause, $productAttributes);
        } catch (\Exception $e) {
            Log::error("Failed to sync product: {$e->getMessage()}", [
                'product_data' => $normalizedData,
                'store' => $storeName,
                'exception' => $e,
            ]);

            throw $e;
        } finally {
            Product::reguard();
        }
    }
}
