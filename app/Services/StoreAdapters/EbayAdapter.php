<?php

declare(strict_types=1);

namespace App\Services\StoreAdapters;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * eBay store adapter.
 */
final class EbayAdapter extends StoreAdapter
{
    private readonly string $appId;

    public function __construct(HttpFactory $http, CacheRepository $cache, LoggerInterface $logger)
    {
        parent::__construct($http, $cache, $logger);
        $appId = config('services.ebay.app_id', '');
        $this->appId = \is_string($appId) ? $appId : '';
    }

    /**
     * @psalm-return 'eBay'
     */
    public function getStoreName(): string
    {
        return 'eBay';
    }

    /**
     * @psalm-return 'ebay'
     */
    #[\Override]
    public function getStoreIdentifier(): string
    {
        return 'ebay';
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
        if ($cached) {
            return $cached;
        }

        // Return dummy data for demonstration
        $dummyData = $this->generateDummyData($productIdentifier);
        if ($dummyData) {
            $normalized = $this->normalizeEbayData($dummyData);
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
        if (! $this->isAvailable()) {
            return [];
        }

        $url = 'https://svcs.ebay.com/services/search/FindingService/v1';
        $params = [
            'OPERATION-NAME' => 'findItemsAdvanced',
            'SERVICE-VERSION' => '1.0.0',
            'SECURITY-APPNAME' => $this->appId,
            'RESPONSE-DATA-FORMAT' => 'JSON',
            'keywords' => $query,
            'paginationInput.entriesPerPage' => 20,
            'paginationInput.pageNumber' => 1,
        ];

        $response = $this->makeRequest($url, $params);

        $searchResult = data_get($response, 'findItemsAdvancedResponse.0.searchResult.0');
        if (\is_array($searchResult) && isset($searchResult['item'])) {
            $items = $searchResult['item'];

            if (! \is_array($items)) {
                return [];
            }

            return array_values(array_filter(array_map(
                function ($item): ?array {
                    if (! \is_array($item)) {
                        return null;
                    }

                    // @var array<string, array> $item
                    return $this->normalizeEbaySearchResult($item);
                },
                $items
            )));
        }

        return [];
    }

    public function validateIdentifier(string $identifier): bool
    {
        // eBay item ID is numeric, typically 12 digits
        return 1 === preg_match('/^\d{10,15}$/', $identifier);
    }

    #[\Override]
    public function getProductUrl(string $identifier): string
    {
        return "https://www.ebay.com/itm/{$identifier}";
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 20,
            'requests_per_hour' => 1000,
            'requests_per_day' => 5000,
        ];
    }

    /**
     * Build API URL for product.
     *
     * @phpstan-ignore-next-line
     */
    private function buildApiUrl(string $itemId): string
    {
        return 'https://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=JSON&ItemID='.$itemId.'&siteid=0&version=967';
    }

    /**
     * Normalize eBay product data.
     *
     * @param array<string, mixed> $item
     *
     * @return array<array|scalar|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|scalar|null, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|scalar|null, brand: array|scalar|null, category: array|scalar|null, metadata: array|scalar}
     */
    private function normalizeEbayData(array $item): array
    {
        $price = is_array($item['ConvertedCurrentPrice'] ?? null)
            ? ($item['ConvertedCurrentPrice']['Value'] ?? 0.0)
            : ($item['ConvertedCurrentPrice'] ?? 0.0);

        $sellingState = is_array($item['SellingStatus'] ?? null)
            ? ($item['SellingStatus']['SellingState'] ?? null)
            : null;

        return $this->normalizeProductData([
            'name' => $item['Title'] ?? '',
            'price' => is_numeric($price) ? (float) $price : 0.0,
            'currency' => is_array($item['ConvertedCurrentPrice'] ?? null)
                ? ($item['ConvertedCurrentPrice']['CurrencyID'] ?? 'USD')
                : 'USD',
            'url' => $item['ViewItemURLForNaturalSearch'] ?? '',
            'image_url' => $item['GalleryURL'] ?? null,
            'availability' => $this->mapEbayAvailability($sellingState),
            'rating' => null, // Not directly available
            'reviews_count' => null, // Not directly available
            'description' => $item['Description'] ?? null,
            'brand' => null, // Can be extracted from item specifics
            'category' => $item['PrimaryCategoryName'] ?? null,
            'metadata' => [
                'item_id' => $item['ItemID'] ?? '',
                'listing_type' => $item['ListingType'] ?? null,
                'condition' => $item['ConditionDisplayName'] ?? null,
                'end_time' => $item['EndTime'] ?? null,
                'seller' => is_array($item['Seller'] ?? null)
                    ? ($item['Seller']['UserID'] ?? null)
                    : null,
            ],
        ]);
    }

    /**
     * Map eBay availability status.
     *
     * @psalm-return 'in_stock'|'out_of_stock'
     */
    private function mapEbayAvailability(?string $status): string
    {
        return match ($status) {
            'Active' => 'in_stock',
            'Ended' => 'out_of_stock',
            default => 'out_of_stock',
        };
    }

    /**
     * Normalize eBay search result item.
     *
     * @param array<string, array> $item
     *
     * @return array<array|scalar|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{name: array|scalar, price: float, currency: array|scalar, url: array|scalar, image_url: array|scalar|null, availability: array|scalar, rating: float|null, reviews_count: int|null, description: array|scalar|null, brand: array|scalar|null, category: array|scalar|null, metadata: array|scalar}
     */
    private function normalizeEbaySearchResult(array $item): array
    {
        $price = data_get($item, 'sellingStatus.0.currentPrice.0.__value__', 0.0);

        return $this->normalizeProductData([
            'name' => data_get($item, 'title.0', ''),
            'price' => is_numeric($price) ? (float) $price : 0.0,
            'currency' => data_get($item, 'sellingStatus.0.currentPrice.0.@currencyId', 'USD'),
            'url' => data_get($item, 'viewItemURL.0', ''),
            'image_url' => data_get($item, 'galleryURL.0'),
            'availability' => 'in_stock', // Search results are typically for active items
            'rating' => null,
            'reviews_count' => null,
            'description' => null,
            'brand' => null,
            'category' => data_get($item, 'primaryCategory.0.categoryName.0'),
            'metadata' => [
                'item_id' => data_get($item, 'itemId.0', ''),
                'listing_type' => data_get($item, 'listingInfo.0.listingType.0'),
                'condition' => data_get($item, 'condition.0.conditionDisplayName.0'),
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
        $basePrice = 79.99 + (crc32($productIdentifier) % 400);
        $price = round($basePrice, 2);

        return [
            'Title' => "eBay Product {$productIdentifier} - Great Deal",
            'ConvertedCurrentPrice' => [
                'Value' => $price,
                'CurrencyID' => 'USD',
            ],
            'ViewItemURLForNaturalSearch' => "https://www.ebay.com/itm/{$productIdentifier}",
            'GalleryURL' => 'https://via.placeholder.com/500x500?text=eBay+Product',
            'SellingStatus' => [
                'SellingState' => 'Active',
            ],
            'Description' => 'High-quality product available on eBay with excellent customer reviews.',
            'PrimaryCategoryName' => 'Electronics',
            'ItemID' => $productIdentifier,
            'ListingType' => 'FixedPrice',
            'ConditionDisplayName' => 'New',
            'EndTime' => date('Y-m-d\TH:i:s.000\Z', strtotime('+30 days')),
            'Seller' => [
                'UserID' => 'trusted_seller',
            ],
        ];
    }
}
