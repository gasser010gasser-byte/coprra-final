<?php

declare(strict_types=1);

namespace App\Services\StoreAdapters;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Mock Store Adapter for Development & Testing
 *
 * Returns realistic fake product data without requiring external API credentials.
 * This allows full end-to-end testing of the scraper engine.
 */
final class MockStoreAdapter extends StoreAdapter
{
    private const MARKETPLACE_HOST_KEYWORDS = [
        'amazon.',
        'noon.',
        'ebay.',
        'walmart.',
        'bestbuy.',
        'target.',
        'aliexpress.',
    ];

    public function __construct(HttpFactory $http, CacheRepository $cache, LoggerInterface $logger)
    {
        parent::__construct($http, $cache, $logger);
    }

    /**
     * @psalm-return 'Mock Store'
     */
    public function getStoreName(): string
    {
        return 'Mock Store';
    }

    /**
     * @psalm-return 'mock'
     */
    #[\Override]
    public function getStoreIdentifier(): string
    {
        return 'mock';
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return true; // Always available
    }

    /**
     * Fetch product by URL - extracts data from URL and returns mock product data
     *
     * @return array<string, mixed>|null
     */
    #[\Override]
    public function fetchProduct(string $productIdentifier): ?array
    {
        $this->logger->info('MockStoreAdapter: Fetching product', ['url' => $productIdentifier]);

        if (! $this->validateIdentifier($productIdentifier)) {
            throw new \InvalidArgumentException('Invalid product URL supplied.');
        }

        $this->ensureNotMarketplaceUrl($productIdentifier);

        // Attempt to retrieve from cache first
        $cached = $this->getCachedProduct($productIdentifier);
        if ($cached !== null) {
            return $cached;
        }

        $productData = $this->extractDataFromUrl($productIdentifier);

        if ($productData === null) {
            throw new \RuntimeException('Failed to extract product data from HTML document.');
        }

        $normalized = $this->normalizeProductData($productData);
        $this->logger->info('MockStoreAdapter: Product data generated successfully');

        // Cache for subsequent requests
        $this->cacheProduct($productIdentifier, $normalized, 900);

        return $normalized;
    }

    private function ensureNotMarketplaceUrl(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (self::MARKETPLACE_HOST_KEYWORDS as $keyword) {
            if ($host !== '' && str_contains($host, $keyword)) {
                throw new \RuntimeException('Scraping from marketplaces is currently disabled.');
            }
        }
    }

    #[\Override]
    public function searchProducts(string $query, array $options = []): array
    {
        // Not implemented for mock adapter
        return [];
    }

    public function validateIdentifier(string $identifier): bool
    {
        // Accept any valid URL
        return filter_var($identifier, FILTER_VALIDATE_URL) !== false;
    }

    #[\Override]
    public function getProductUrl(string $identifier): string
    {
        return $identifier;
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 1000,
            'requests_per_hour' => 10000,
            'requests_per_day' => 100000,
        ];
    }

    /**
     * Extract product data from URL using intelligent pattern matching
     *
     * @return array<string, mixed>|null
     */
    private function extractDataFromUrl(string $url): ?array
    {
        $crawler = $this->fetchDocument($url);

        if ($crawler === null) {
            return null;
        }

        $meta = $this->extractMetaTags($crawler);
        $structured = $this->parseStructuredData($crawler);

        $title = $structured['name']
            ?? ($meta['og:title'] ?? $meta['twitter:title'] ?? $meta['title'] ?? null)
            ?? $this->extractTitleFromCrawler($crawler)
            ?? $this->extractNameFromUrl($url);
        $title = $this->cleanTitle($title);

        if ($title === '') {
            return null;
        }

        $price = $structured['price']
            ?? $this->extractPriceFromMeta($meta);

        $currency = $structured['currency']
            ?? $meta['product:price:currency']
            ?? $meta['og:price:currency']
            ?? 'USD';

        $brand = $structured['brand']
            ?? $this->extractBrandFromMeta($meta)
            ?? $this->detectBrand(strtolower($title))
            ?? $this->extractBrandFromUrl($url)
            ?? 'Official Brand';

        $category = $structured['category']
            ?? $this->extractCategoryFromMeta($meta)
            ?? $this->detectCategory(strtolower($url))
            ?? 'Official Brand Product';

        if ($price === null) {
            $price = $this->generatePrice($category);
        }

        $availability = $structured['availability']
            ?? $meta['product:availability']
            ?? 'unknown';

        if (is_string($availability)) {
            $availabilityLower = strtolower($availability);
            if (str_contains($availabilityLower, 'instock')) {
                $availability = 'in_stock';
            } elseif (str_contains($availabilityLower, 'outofstock') || str_contains($availabilityLower, 'out_of_stock')) {
                $availability = 'out_of_stock';
            }
        }

        $imageUrl = $this->resolveUrl(
            $structured['image'] ?? $meta['og:image'] ?? $meta['twitter:image'] ?? null,
            $url
        );

        $description = $structured['description']
            ?? ($meta['description'] ?? $meta['og:description'] ?? null)
            ?? $this->extractPrimaryDescription($crawler);

        if ($description === null || trim($description) === '') {
            $description = $this->generateDescription(
                $title,
                $brand,
                $category,
                $this->extractProductId($url)
            );
        }

        return [
            'name' => $title,
            'price' => $price,
            'currency' => $currency,
            'url' => $url,
            'image_url' => $imageUrl,
            'availability' => $availability,
            'description' => $description,
            'brand' => $brand,
            'category' => $category,
            'metadata' => [
                'product_id' => $this->extractProductId($url),
                'source' => $this->detectSource($url),
                'structured_data' => $structured,
                'meta' => array_filter($meta),
                'imported_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function fetchDocument(string $url): ?Crawler
    {
        try {
            $response = $this->http
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                        .'(KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->timeout($this->timeout)
                ->retry($this->retries, 200)
                ->get($url);

            if (! $response->successful()) {
                $this->logger->warning('MockStoreAdapter: HTTP request failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return null;
            }

            $body = (string) $response->body();

            if (trim($body) === '') {
                return null;
            }

            return new Crawler($body);
        } catch (\Throwable $exception) {
            $this->logger->error('MockStoreAdapter: Exception while fetching document', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function extractMetaTags(Crawler $crawler): array
    {
        $meta = [];

        try {
            $crawler->filterXPath('//meta[@name or @property]')->each(static function (Crawler $node) use (&$meta): void {
                $key = $node->attr('name') ?? $node->attr('property');
                $content = $node->attr('content');

                if ($key !== null && $content !== null) {
                    $meta[strtolower(trim($key))] = trim($content);
                }
            });
        } catch (\Throwable $exception) {
            $this->logger->debug('MockStoreAdapter: Meta tag parsing warning', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $meta;
    }

    private function parseStructuredData(Crawler $crawler): array
    {
        $result = [
            'name' => null,
            'description' => null,
            'image' => null,
            'price' => null,
            'currency' => null,
            'brand' => null,
            'category' => null,
            'availability' => null,
        ];

        try {
            $crawler->filterXPath("//script[@type='application/ld+json']")->each(function (Crawler $node) use (&$result): void {
                $json = trim($node->text());

                if ($json === '') {
                    return;
                }

                $decoded = json_decode($json, true);

                if (! is_array($decoded)) {
                    return;
                }

                $items = $this->normalizeStructuredItems($decoded);

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    if (isset($item['@graph']) && is_array($item['@graph'])) {
                        foreach ($this->normalizeStructuredItems($item['@graph']) as $graphItem) {
                            if (is_array($graphItem)) {
                                $this->mergeStructuredItem($graphItem, $result);
                            }
                        }

                        continue;
                    }

                    $this->mergeStructuredItem($item, $result);
                }
            });
        } catch (\Throwable $exception) {
            $this->logger->warning('MockStoreAdapter: Failed parsing structured data', [
                'error' => $exception->getMessage(),
            ]);
        }

        return array_filter($result, static fn ($value) => $value !== null);
    }

    private function mergeStructuredItem(array $item, array &$result): void
    {
        $type = $item['@type'] ?? $item['type'] ?? null;
        if (is_array($type)) {
            $type = $type[0] ?? null;
        }

        if (! is_string($type)) {
            $type = null;
        }

        if ($type !== null && strcasecmp($type, 'Product') === 0) {
            $result['name'] ??= $item['name'] ?? null;
            $result['description'] ??= $item['description'] ?? null;
            $result['image'] ??= $this->firstString($item['image'] ?? null);
            $result['brand'] ??= $this->extractBrandFromStructured($item['brand'] ?? null);
            $result['category'] ??= $item['category'] ?? $item['itemCategory'] ?? null;

            if (isset($item['offers'])) {
                foreach ($this->normalizeStructuredItems($item['offers']) as $offer) {
                    if (! is_array($offer)) {
                        continue;
                    }

                    $result['price'] ??= $this->parsePriceValue($offer['price'] ?? null);
                    $result['currency'] ??= $offer['priceCurrency'] ?? null;
                    $result['availability'] ??= $offer['availability'] ?? null;
                }
            }
        }

        if ($type !== null && strcasecmp($type, 'Offer') === 0) {
            $result['price'] ??= $this->parsePriceValue($item['price'] ?? null);
            $result['currency'] ??= $item['priceCurrency'] ?? null;
            $result['availability'] ??= $item['availability'] ?? null;
        }
    }

    private function normalizeStructuredItems($data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return $this->normalizeStructuredItems($data['@graph']);
        }

        if ($this->isAssoc($data)) {
            return [$data];
        }

        return $data;
    }

    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function firstString($value): ?string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    return trim($item);
                }
            }
        }

        return null;
    }

    private function extractBrandFromStructured($brand): ?string
    {
        if (is_string($brand) && trim($brand) !== '') {
            return trim($brand);
        }

        if (is_array($brand)) {
            return $brand['name'] ?? $brand['brand'] ?? null;
        }

        return null;
    }

    private function parsePriceValue($price): ?float
    {
        if (is_numeric($price)) {
            return (float) $price;
        }

        if (is_string($price)) {
            $clean = preg_replace('/[^0-9.]/', '', $price);

            if ($clean !== '' && is_numeric($clean)) {
                return (float) $clean;
            }
        }

        return null;
    }

    private function resolveUrl(?string $value, string $baseUrl): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (
            Str::startsWith($value, 'http://')
            || Str::startsWith($value, 'https://')
        ) {
            return $value;
        }

        if (Str::startsWith($value, '//')) {
            return 'https:' . $value;
        }

        $parsed = parse_url($baseUrl);
        if ($parsed === false) {
            return $value;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        return sprintf('%s://%s/%s', $scheme, $host, ltrim($value, '/'));
    }

    private function extractPrimaryDescription(Crawler $crawler): ?string
    {
        $xpaths = [
            "//div[@itemprop='description']",
            "//section[contains(@class,'description')]",
            "//div[contains(@class,'description')]",
            "//p[contains(@class,'description')]",
        ];

        foreach ($xpaths as $xpath) {
            try {
                $node = $crawler->filterXPath($xpath)->first();
                if ($node->count() > 0) {
                    $text = trim($node->text(''));
                    if ($text !== '') {
                        return preg_replace('/\s+/', ' ', $text) ?: $text;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function extractPriceFromMeta(array $meta): ?float
    {
        $candidates = [
            $meta['product:price:amount'] ?? null,
            $meta['og:price:amount'] ?? null,
            $meta['price'] ?? null,
            $meta['twitter:data1'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $price = $this->parsePriceValue($candidate);
            if ($price !== null) {
                return $price;
            }
        }

        return null;
    }

    private function extractBrandFromMeta(array $meta): ?string
    {
        $candidates = [
            $meta['brand'] ?? null,
            $meta['product:brand'] ?? null,
            $meta['og:site_name'] ?? null,
            $meta['twitter:site'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return Str::title(trim(ltrim($candidate, '@')));
            }
        }

        return null;
    }

    private function extractBrandFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null) {
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return Str::title($host);
        }

        $brandPart = $parts[count($parts) - 2];

        return Str::title(str_replace('-', ' ', $brandPart));
    }

    private function extractCategoryFromMeta(array $meta): ?string
    {
        $candidates = [
            $meta['category'] ?? null,
            $meta['product:category'] ?? null,
            $meta['og:category'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $clean = preg_replace('/[^a-z0-9\s]/i', ' ', $candidate) ?: $candidate;

                return Str::title(trim(preg_replace('/\s+/', ' ', $clean) ?: $clean));
            }
        }

        return null;
    }

    private function extractTitleFromCrawler(Crawler $crawler): ?string
    {
        try {
            $ogTitle = $crawler
                ->filterXPath("//meta[@property='og:title' or @name='og:title']")
                ->first()
                ->attr('content');

            $cleanOg = $this->cleanTitle($ogTitle ?? '');
            if ($cleanOg !== '') {
                return $cleanOg;
            }
        } catch (\InvalidArgumentException) {
            // Meta tag not found
        }

        try {
            $titleText = $crawler->filter('title')->first()->text('');
            $cleanTitle = $this->cleanTitle($titleText);

            if ($cleanTitle !== '') {
                return $cleanTitle;
            }
        } catch (\InvalidArgumentException) {
            // Title tag not found
        }

        return null;
    }

    private function cleanTitle(?string $title): string
    {
        $title = html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        // Remove common marketplace suffixes/prefixes
        $patterns = [
            '/\s*[-|•–]\s*(Amazon\.com.*)$/i',
            '/\s*[:|]\s*(Amazon\.com.*)$/i',
            '/\s*[-|•–]\s*(Best Buy.*)$/i',
            '/\s*[-|•–]\s*(Walmart.*)$/i',
            '/\s*[-|•–]\s*(eBay.*)$/i',
        ];

        foreach ($patterns as $pattern) {
            $title = preg_replace($pattern, '', $title) ?? $title;
        }

        // Trim residual separators
        $title = trim($title, "-–—|• ");

        // Collapse multiple spaces
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;

        return trim($title);
    }

    /**
     * Extract product ID from URL
     */
    private function extractProductId(string $url): string
    {
        // Try to extract ASIN from Amazon URLs
        if (preg_match('/\/dp\/([A-Z0-9]{10})/', $url, $matches)) {
            return $matches[1];
        }

        // Try to extract any product ID pattern
        if (preg_match('/[\/\-]([A-Z0-9]{6,15})(?:[\/\?]|$)/', $url, $matches)) {
            return $matches[1];
        }

        // Fallback: generate from URL hash
        return 'PROD' . strtoupper(substr(md5($url), 0, 8));
    }

    /**
     * Extract product name from URL
     */
    private function extractNameFromUrl(string $url): string
    {
        // Try to extract name from Amazon-style URLs
        if (preg_match('/\/([^\/]+)\/dp\//', $url, $matches)) {
            $urlName = $matches[1];
        }
        // Try to extract from slug-style URLs
        elseif (preg_match('/\/([^\/\?]+?)(?:\?|$)/', $url, $matches)) {
            $urlName = $matches[1];
        } else {
            $urlName = 'Product';
        }

        // Clean up the name
        $urlName = str_replace(['-', '_', '+'], ' ', $urlName);
        $urlName = preg_replace('/\s+/', ' ', $urlName);
        $urlName = ucwords(trim($urlName));

        // Limit length
        if (strlen($urlName) > 100) {
            $urlName = substr($urlName, 0, 97) . '...';
        }

        return $urlName;
    }

    /**
     * Detect brand from URL
     */
    private function detectBrand(string $urlLower): string
    {
        $brands = [
            'apple' => 'Apple',
            'iphone' => 'Apple',
            'ipad' => 'Apple',
            'macbook' => 'Apple',
            'samsung' => 'Samsung',
            'galaxy' => 'Samsung',
            'lg' => 'LG',
            'sony' => 'Sony',
            'dell' => 'Dell',
            'hp' => 'HP',
            'hewlett' => 'HP',
            'lenovo' => 'Lenovo',
            'asus' => 'ASUS',
            'acer' => 'Acer',
            'xiaomi' => 'Xiaomi',
            'redmi' => 'Xiaomi',
            'oppo' => 'OPPO',
            'vivo' => 'Vivo',
            'realme' => 'Realme',
            'nokia' => 'Nokia',
            'huawei' => 'Huawei',
            'microsoft' => 'Microsoft',
            'surface' => 'Microsoft',
            'google' => 'Google',
            'pixel' => 'Google',
            'bosch' => 'Bosch',
            'siemens' => 'Siemens',
            'whirlpool' => 'Whirlpool',
            'tornado' => 'Tornado',
            'fresh' => 'Fresh',
            'kiriazi' => 'Kiriazi',
            'sharp' => 'Sharp',
            'toshiba' => 'Toshiba',
            'panasonic' => 'Panasonic',
            'philips' => 'Philips',
            'canon' => 'Canon',
            'nikon' => 'Nikon',
        ];

        foreach ($brands as $keyword => $brandName) {
            if (stripos($urlLower, $keyword) !== false) {
                return $brandName;
            }
        }

        return 'Generic Brand';
    }

    /**
     * Detect category from URL
     */
    private function detectCategory(string $urlLower): string
    {
        $categories = [
            'Mobile Phones' => ['iphone', 'mobile', 'phone', 'smartphone', 'galaxy', 'pixel'],
            'Laptops' => ['macbook', 'laptop', 'notebook', 'computer', 'desktop'],
            'Tablets' => ['ipad', 'tablet'],
            'Smart Watches' => ['watch', 'smartwatch'],
            'Audio Accessories' => ['airpods', 'headphone', 'earbuds', 'speaker', 'audio'],
            'Home Appliances' => ['refrigerator', 'washing', 'washer', 'dryer', 'dishwasher', 'oven', 'fridge'],
            'Kitchen Appliances' => ['blender', 'mixer', 'toaster', 'coffee', 'microwave', 'kettle'],
            'TVs & Displays' => ['tv', 'television', 'monitor', 'display', 'screen'],
            'Gaming' => ['playstation', 'xbox', 'nintendo', 'gaming', 'console'],
            'Cameras' => ['camera', 'canon', 'nikon', 'photo'],
        ];

        foreach ($categories as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($urlLower, $keyword) !== false) {
                    return $categoryName;
                }
            }
        }

        return 'Electronics';
    }

    /**
     * Generate realistic price based on category
     */
    private function generatePrice(string $category): float
    {
        $priceRanges = [
            'Mobile Phones' => [5000, 40000],
            'Laptops' => [15000, 80000],
            'Tablets' => [8000, 35000],
            'Smart Watches' => [2000, 20000],
            'Audio Accessories' => [500, 8000],
            'Home Appliances' => [5000, 50000],
            'Kitchen Appliances' => [500, 15000],
            'TVs & Displays' => [8000, 60000],
            'Gaming' => [5000, 25000],
            'Cameras' => [10000, 50000],
        ];

        $range = $priceRanges[$category] ?? [1000, 20000];

        return (float) rand($range[0], $range[1]);
    }

    /**
     * Generate product description
     */
    private function generateDescription(string $name, string $brand, string $category, string $productId): string
    {
        $features = [
            'High-quality product with excellent performance',
            'Latest technology and advanced features',
            'Durable construction and premium materials',
            'Energy efficient and eco-friendly design',
            'Easy to use with intuitive controls',
            'Sleek modern design that fits any space',
            'Reliable performance and long-lasting durability',
            'Excellent value for money',
        ];

        shuffle($features);
        $selectedFeatures = array_slice($features, 0, rand(3, 5));

        $description = "<h3>{$name}</h3>\n";
        $description .= "<p><strong>Brand:</strong> {$brand}</p>\n";
        $description .= "<p><strong>Category:</strong> {$category}</p>\n";
        $description .= "<p><strong>Product ID:</strong> {$productId}</p>\n\n";
        $description .= "<h4>Key Features:</h4>\n<ul>\n";

        foreach ($selectedFeatures as $feature) {
            $description .= "<li>{$feature}</li>\n";
        }

        $description .= "</ul>\n\n";
        $description .= "<p><em>Note: This product was imported using the Content Scraper Engine. Images and detailed specifications can be added manually from the product edit page.</em></p>";

        return $description;
    }

    /**
     * Detect source store from URL
     */
    private function detectSource(string $url): string
    {
        if (stripos($url, 'amazon') !== false) {
            return 'Amazon';
        }
        if (stripos($url, 'jumia') !== false) {
            return 'Jumia';
        }
        if (stripos($url, 'noon') !== false) {
            return 'Noon';
        }
        if (stripos($url, 'ebay') !== false) {
            return 'eBay';
        }

        return 'External Store';
    }
}
