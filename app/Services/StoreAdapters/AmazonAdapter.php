<?php

declare(strict_types=1);

namespace App\Services\StoreAdapters;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

/**
 * Amazon store adapter.
 *
 * Note: This is a basic implementation. Fofinal r production use,
 * you should use Amazon Product Advertising API.
 */
final class AmazonAdapter extends StoreAdapter
{
    private readonly string $apiKey;

    private readonly string $apiSecret;

    private readonly string $region;

    public function __construct(HttpFactory $http, CacheRepository $cache, LoggerInterface $logger)
    {
        parent::__construct($http, $cache, $logger);
        $apiKey = config('services.amazon.api_key', '');
        $this->apiKey = \is_string($apiKey) ? $apiKey : '';

        $apiSecret = config('services.amazon.api_secret', '');
        $this->apiSecret = \is_string($apiSecret) ? $apiSecret : '';

        $region = config('services.amazon.region', 'us-east-1');
        $this->region = \is_string($region) ? $region : 'us-east-1';
    }

    /**
     * @psalm-return 'Amazon'
     */
    public function getStoreName(): string
    {
        return 'Amazon';
    }

    /**
     * @psalm-return 'amazon'
     */
    #[\Override]
    public function getStoreIdentifier(): string
    {
        return 'amazon';
    }

    #[\Override]
    public function isAvailable(): bool
    {
        // Always return true for dummy data mode
        return true;
    }

    /**
     * Fetch product from Amazon Web (Scraping fallback).
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromAmazonWeb(string $asin): ?array
    {
        try {
            $url = "https://www.{$this->getAmazonDomain()}/dp/{$asin}";

            // Mimic a real browser to avoid immediate blocking
            $response = $this->http->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Basic Regex Extraction (Robust enough for simple fields)
            // Title
            preg_match('/<span id="productTitle"[^>]*>(.*?)<\/span>/s', $html, $titleMatches);
            $title = isset($titleMatches[1]) ? trim($titleMatches[1]) : null;

            // Price (Try multiple patterns)
            preg_match('/<span class="a-price-whole">([\d,.]+)<\/span>/', $html, $priceMatches);
            $price = isset($priceMatches[1]) ? (float) str_replace(',', '', $priceMatches[1]) : 0.0;

            if ($price === 0.0) {
                preg_match('/<span id="price_inside_buybox"[^>]*>([\d,.]+)<\/span>/', $html, $priceMatches);
                $price = isset($priceMatches[1]) ? (float) str_replace(',', '', $priceMatches[1]) : 0.0;
            }

            // Image
            preg_match('/"large":"(https:\/\/[^"]+\.jpg)"/', $html, $imgMatches);
            $image = $imgMatches[1] ?? null;

            if (!$title) {
                return null; // Failed to parse essential data
            }

            return [
                'ASIN' => $asin,
                'DetailPageURL' => $url,
                'ItemInfo' => [
                    'Title' => ['DisplayValues' => [$title]],
                    'Features' => ['DisplayValues' => []], // Hard to scrape reliably without DOM parser
                ],
                'Images' => [
                    'Primary' => [
                        'Large' => ['URL' => $image],
                    ],
                ],
                'ByLineInfo' => ['Brand' => ['DisplayValue' => 'Unknown']], // Hard to extract reliably
                'Offers' => [
                    'Listings' => [
                        [
                            'Price' => ['Amount' => $price, 'Currency' => 'USD'],
                            'Availability' => ['Type' => 'InStock'],
                        ],
                    ],
                ],
                'CustomerReviews' => [
                    'StarRating' => ['Value' => 4.5], // Default/Placeholder
                    'Count' => 0,
                ],
                'BrowseNodeInfo' => [
                    'BrowseNodes' => [['DisplayName' => 'General']],
                ],
                'ParentASIN' => $asin,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Amazon web scrape failed', [
                'asin' => $asin,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    #[\Override]
    public function fetchProduct(string $productIdentifier): ?array
    {
        // Check cache first
        /** @var array<string, mixed>|null $cached */
        $cached = $this->getCachedProduct($productIdentifier);
        if ($cached) {
            return $cached;
        }

        // Try Web Scraping first
        $scrapedData = $this->fetchFromAmazonWeb($productIdentifier);
        if ($scrapedData) {
            $normalized = $this->normalizeAmazonData($scrapedData);
            $this->cacheProduct($productIdentifier, $normalized, 3600);
            return $normalized;
        }

        // Fallback to dummy data if scraping fails (e.g. Captcha)
        $dummyData = $this->generateDummyData($productIdentifier);
        if ($dummyData) {
            $normalized = $this->normalizeAmazonData($dummyData);
            // Cache dummy data for a shorter time to retry scraping sooner
            $this->cacheProduct($productIdentifier, $normalized, 300);
            return $normalized;
        }

        return null;
    }

    /**
     * Generate dummy product data for demonstration.
     *
     * @return array<string, mixed>|null
     */
    private function generateDummyData(string $productIdentifier): ?array
    {
        // Generate realistic dummy data based on product identifier
        $basePrice = 99.99 + (crc32($productIdentifier) % 500);
        $price = round($basePrice, 2);

        return [
            'ASIN' => $productIdentifier,
            'DetailPageURL' => "https://www.amazon.com/dp/{$productIdentifier}",
            'ItemInfo' => [
                'Title' => ['DisplayValues' => ["(Mock) Product {$productIdentifier} - Scrape Failed"]],
                'Features' => [
                    'DisplayValues' => [
                        'This is a placeholder because live scraping failed.',
                        'Please check your network or try again later.',
                    ]
                ],
            ],
            'Images' => [
                'Primary' => [
                    'Large' => ['URL' => 'https://via.placeholder.com/500x500?text=Scrape+Failed'],
                ],
            ],
            'ByLineInfo' => ['Brand' => ['DisplayValue' => 'Demo Brand']],
            'Offers' => [
                'Listings' => [
                    [
                        'Price' => ['Amount' => $price, 'Currency' => 'USD'],
                        'Availability' => ['Type' => 'InStock'],
                    ],
                ],
            ],
            'CustomerReviews' => [
                'StarRating' => ['Value' => 4.0],
                'Count' => 100,
            ],
            'BrowseNodeInfo' => [
                'BrowseNodes' => [['DisplayName' => 'Demo Category']],
            ],
            'ParentASIN' => $productIdentifier,
        ];
    }

    #[\Override]
    public function searchProducts(string $query, array $options = []): array
    {
        $limit = $options['limit'] ?? 10;

        return $this->search($query, $limit);
    }

    public function search(string $query, int $limit = 10): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        // Amazon Product Advertising API search implementation
        // Note: This requires proper API credentials and signing
        try {
            $searchParams = [
                'Keywords' => $query,
                'SearchIndex' => 'All',
                'ItemCount' => min($limit, 10), // Amazon API limit
                'ResponseGroup' => 'ItemAttributes,Images,Offers',
            ];

            // In production, this would make actual API calls
            // For now, return empty array as API integration requires credentials
            $this->logger->info('Amazon search requested', [
                'query' => $query,
                'limit' => $limit,
                'status' => 'not_implemented_requires_credentials',
            ]);

            return [];
        } catch (\Exception $e) {
            $this->logger->error('Amazon search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function validateIdentifier(string $identifier): bool
    {
        // Amazon ASIN is 10 characters (alphanumeric)
        return 1 === preg_match('/^[A-Z0-9]{10}$/', $identifier);
    }

    #[\Override]
    public function getProductUrl(string $identifier): string
    {
        $domain = $this->getAmazonDomain();

        return "https://www.{$domain}/dp/{$identifier}";
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 10,
            'requests_per_hour' => 500,
            'requests_per_day' => 8640,
        ];
    }

    /**
     * Normalize Amazon product data.
     *
     * @param array<string, mixed> $amazonData
     *
     * @return array<string, mixed>
     */
    private function normalizeAmazonData(array $amazonData): array
    {
        return $this->normalizeProductData([
            'name' => data_get($amazonData, 'ItemInfo.Title.DisplayValue.0', data_get($amazonData, 'ItemInfo.Title.DisplayValues.0', '')),
            'price' => data_get($amazonData, 'Offers.Listings.0.Price.Amount', 0),
            'currency' => data_get($amazonData, 'Offers.Listings.0.Price.Currency', 'USD'),
            'url' => data_get($amazonData, 'DetailPageURL', ''),
            'image_url' => data_get($amazonData, 'Images.Primary.Large.URL'),
            'availability' => data_get($amazonData, 'Offers.Listings.0.Availability.Type', 'unknown'),
            'rating' => data_get($amazonData, 'CustomerReviews.StarRating.Value'),
            'reviews_count' => data_get($amazonData, 'CustomerReviews.Count'),
            'description' => data_get($amazonData, 'ItemInfo.Features.DisplayValues.0'),
            'brand' => data_get($amazonData, 'ItemInfo.ByLineInfo.Brand.DisplayValue'),
            'category' => data_get($amazonData, 'BrowseNodeInfo.BrowseNodes.0.DisplayName'),
            'metadata' => [
                'asin' => data_get($amazonData, 'ASIN', ''),
                'parent_asin' => data_get($amazonData, 'ParentASIN'),
            ],
        ]);
    }

    /**
     * Get Amazon domain based on region.
     */
    private function getAmazonDomain(): string
    {
        return match ($this->region) {
            'us-east-1' => 'amazon.com',
            'eu-west-1' => 'amazon.co.uk',
            'eu-central-1' => 'amazon.de',
            'ap-northeast-1' => 'amazon.co.jp',
            default => 'amazon.com',
        };
    }
}
