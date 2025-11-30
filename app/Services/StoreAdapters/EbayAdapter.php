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

    /**
     * Fetch product from eBay Web (Scraping fallback).
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromEbayWeb(string $itemId): ?array
    {
        try {
            $url = "https://www.ebay.com/itm/{$itemId}";

            // Mimic a real browser
            $response = $this->http->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ])->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // Basic Regex Extraction
            // Title
            preg_match('/<h1[^>]*class="x-item-title__mainTitle"[^>]*>.*?<span[^>]*>(.*?)<\/span>.*?<\/h1>/s', $html, $titleMatches);
            $title = isset($titleMatches[1]) ? trim(strip_tags($titleMatches[1])) : null;

            // Price
            preg_match('/<div[^>]*class="x-price-primary"[^>]*>.*?<span[^>]*>.*?([\d,.]+).*?<\/span>/s', $html, $priceMatches);
            $price = isset($priceMatches[1]) ? (float) str_replace(',', '', $priceMatches[1]) : 0.0;

            // Image
            preg_match('/<img[^>]*id="icImg"[^>]*src="([^"]+)"/', $html, $imgMatches);
            $image = $imgMatches[1] ?? null;

            if (! $title) {
                // Try alternative title pattern
                preg_match('/<title>(.*?)<\/title>/', $html, $titleMatchesAlt);
                $title = isset($titleMatchesAlt[1]) ? trim(str_replace('| eBay', '', $titleMatchesAlt[1])) : null;
            }

            if (! $title) {
                return null;
            }

            return [
                'Title' => $title,
                'ConvertedCurrentPrice' => [
                    'Value' => $price,
                    'CurrencyID' => 'USD',
                ],
                'ViewItemURLForNaturalSearch' => $url,
                'GalleryURL' => $image,
                'SellingStatus' => [
                    'SellingState' => 'Active',
                ],
                'Description' => 'Scraped from eBay',
                'PrimaryCategoryName' => 'General',
                'ItemID' => $itemId,
                'ListingType' => 'FixedPrice',
                'ConditionDisplayName' => 'Used',
                'EndTime' => date('Y-m-d\TH:i:s.000\Z', strtotime('+30 days')),
                'Seller' => [
                    'UserID' => 'unknown',
                ],
            ];

        } catch (\Exception $e) {
            $this->logger->error('eBay web scrape failed', [
                'item_id' => $itemId,
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
        if ($cached) {
            return $cached;
        }

        // Try Web Scraping first
        $scrapedData = $this->fetchFromEbayWeb($productIdentifier);
        if ($scrapedData) {
            $normalized = $this->normalizeEbayData($scrapedData);
            $this->cacheProduct($productIdentifier, $normalized, 3600);

            return $normalized;
        }

        // Fallback to dummy data
        $dummyData = $this->generateDummyData($productIdentifier);
        if ($dummyData) {
            $normalized = $this->normalizeEbayData($dummyData);
            $this->cacheProduct($productIdentifier, $normalized, 300); // Short cache for dummy

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
        $basePrice = 79.99 + (crc32($productIdentifier) % 400);
        $price = round($basePrice, 2);

        return [
            'Title' => "(Mock) eBay Product {$productIdentifier} - Scrape Failed",
            'ConvertedCurrentPrice' => [
                'Value' => $price,
                'CurrencyID' => 'USD',
            ],
            'ViewItemURLForNaturalSearch' => "https://www.ebay.com/itm/{$productIdentifier}",
            'GalleryURL' => 'https://via.placeholder.com/500x500?text=Scrape+Failed',
            'SellingStatus' => [
                'SellingState' => 'Active',
            ],
            'Description' => 'This is a placeholder because live scraping failed.',
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
        return 'https://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=JSON&ItemID=' . $itemId . '&siteid=0&version=967';
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
}
