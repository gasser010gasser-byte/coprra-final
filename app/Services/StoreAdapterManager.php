<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\StoreAdapter;
use App\Services\StoreAdapters\AmazonAdapter;
use App\Services\StoreAdapters\BestBuyAdapter;
use App\Services\StoreAdapters\EbayAdapter;
use App\Services\StoreAdapters\JumiaAdapter;
use App\Services\StoreAdapters\NoonAdapter;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Manager for store adapters.
 */
class StoreAdapterManager
{
    /**
     * @var array<string, StoreAdapter>
     */
    private array $adapters = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->adapters = [];
        $this->registerDefaultAdapters();
    }

    /**
     * Register a store adapter.
     */
    public function register(StoreAdapter $adapter): void
    {
        $this->adapters[$adapter->getStoreIdentifier()] = $adapter;
    }

    /**
     * Get adapter by store identifier.
     */
    public function getAdapter(string $identifier): ?StoreAdapter
    {
        return $this->adapters[$identifier] ?? null;
    }

    /**
     * Get all registered adapters.
     *
     * @return array<string, StoreAdapter>
     */
    public function getAllAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * Get all available (configured) adapters.
     *
     * @return array<string, StoreAdapter>
     */
    public function getAvailableAdapters(): array
    {
        return array_filter(
            $this->adapters,
            static fn (StoreAdapter $adapter): bool => $adapter->isAvailable()
        );
    }

    /**
     * Check if store is supported.
     */
    public function isStoreSupported(string $storeIdentifier): bool
    {
        return isset($this->adapters[$storeIdentifier]);
    }

    /**
     * Get supported stores.
     *
     * @return list<string>
     */
    public function getSupportedStores(): array
    {
        return array_keys($this->adapters);
    }

    /**
     * Validate product identifier for a store.
     */
    public function validateIdentifier(string $storeIdentifier, string $identifier): bool
    {
        $adapter = $this->getAdapter($storeIdentifier);

        if (! $adapter) {
            return false;
        }

        return $adapter->validateIdentifier($identifier);
    }

    /**
     * Get product URL for a store.
     */
    public function getProductUrl(string $storeIdentifier, string $identifier): ?string
    {
        $adapter = $this->getAdapter($storeIdentifier);

        if (! $adapter) {
            return null;
        }

        return $adapter->getProductUrl($identifier);
    }

    /**
     * Get statistics about adapters.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $available = $this->getAvailableAdapters();

        return [
            'total_adapters' => \count($this->adapters),
            'available_adapters' => \count($available),
            'adapters' => array_map(
                static fn (StoreAdapter $adapter) => [
                    'name' => $adapter->getStoreName(),
                    'identifier' => $adapter->getStoreIdentifier(),
                    'available' => $adapter->isAvailable(),
                ],
                $this->adapters
            ),
        ];
    }

    /**
     * Fetch product from specific store.
     *
     * @return (array|scalar|null)[]|null
     *
     * @psalm-return array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|null|scalar, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|null|scalar, brand: array|null|scalar, category: array|null|scalar, metadata: array|scalar}|null
     */
    public function fetchProduct(string $storeIdentifier, string $productIdentifier): ?array
    {
        $adapter = $this->getAdapter($storeIdentifier);

        if (! $adapter instanceof StoreAdapter) {
            return null;
        }

        if (! $adapter->isAvailable()) {
            return null;
        }

        return $adapter->fetchProduct($productIdentifier);
    }

    /**
     * Get list of available store identifiers.
     *
     * @return list<string>
     */
    public function getAvailableStores(): array
    {
        return array_keys($this->getAvailableAdapters());
    }

    /**
     * Register default adapters.
     */
    private function registerDefaultAdapters(): void
    {
        // For testing purposes, we'll create mock adapters
        // In production, these would be properly injected
        $http = app(HttpFactory::class);
        $cache = app(CacheRepository::class);
        $logger = app(LoggerInterface::class);

        $this->register(new AmazonAdapter($http, $cache, $logger));
        $this->register(new EbayAdapter($http, $cache, $logger));
        $this->register(new NoonAdapter($http, $cache, $logger));
        $this->register(new JumiaAdapter($http, $cache, $logger));
        $this->register(new BestBuyAdapter($http, $cache, $logger));
    }
}
