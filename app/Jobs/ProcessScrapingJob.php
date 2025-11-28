<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ScraperJob;
use App\Services\StoreAdapters\MockStoreAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessScrapingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The URL to scrape.
     *
     * @var string
     */
    public $url;

    /**
     * The batch ID for this scraping session.
     *
     * @var string
     */
    public $batchId;

    /**
     * The job number in the batch.
     *
     * @var int
     */
    public $jobNumber;

    /**
     * The scraper job model ID for tracking.
     *
     * @var int
     */
    public $scraperJobId;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    private const BLOCKED_HOST_KEYWORDS = [
        'amazon.',
        'noon.',
        'ebay.',
        'walmart.',
        'bestbuy.',
        'target.',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(string $url, string $batchId, int $jobNumber, int $scraperJobId)
    {
        $this->url = $url;
        $this->batchId = $batchId;
        $this->jobNumber = $jobNumber;
        $this->scraperJobId = $scraperJobId;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180, 300]; // Retry after 1, 3, and 5 minutes
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get the scraper job model
        $scraperJob = ScraperJob::find($this->scraperJobId);

        if (! $scraperJob) {
            Log::channel('scraper')->error("❌ Job #{$this->jobNumber}: ScraperJob record not found (ID: {$this->scraperJobId})");

            return;
        }

        // Mark job as running
        $scraperJob->markAsRunning();

        Log::channel('scraper')->info("🔍 Job #{$this->jobNumber}: Starting scraping for {$this->url}");

        try {
            // Validate URL
            if (! $this->isValidUrl($this->url)) {
                throw new \Exception("Invalid URL format");
            }

            $this->assertAllowedSource($this->url);

            // Initialize Mock Store Adapter
            $adapter = app(MockStoreAdapter::class);

            // Fetch product data using adapter
            $data = $adapter->fetchProduct($this->url);

            if (! $data) {
                throw new \Exception("Failed to extract data from URL");
            }

            // Store the adapter name
            $scraperJob->update(['store_adapter' => $adapter->getStoreName()]);

            $this->validateScrapedData($data);

            // Create product from scraped data
            $product = $this->createProduct($data);

            if ($product) {
                $scraperJob->markAsCompleted($product->id);
                Log::channel('scraper')->info("✅ Job #{$this->jobNumber}: Product created successfully (ID: {$product->id}, Name: {$product->name})");
            } else {
                throw new \Exception("Failed to create product in database");
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            Log::channel('scraper')->error("❌ Job #{$this->jobNumber}: Exception - {$errorMessage}");

            // Mark as failed if this is the last attempt
            if ($this->attempts() >= $this->tries) {
                $scraperJob->markAsFailed($errorMessage);
                Log::channel('scraper')->error("❌ Job #{$this->jobNumber}: FAILED after {$this->tries} attempts");
            } else {
                Log::channel('scraper')->warning("⏳ Job #{$this->jobNumber}: Will retry (Attempt {$this->attempts()}/{$this->tries})");
            }

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    private function validateScrapedData(array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new \Exception('Failed to extract a valid product title.');
        }

        $normalized = Str::of($name)->lower();

        if ($normalized->contains('www.') || $normalized->contains('.com')) {
            throw new \Exception('Failed to extract a valid product title.');
        }

        if ($normalized->length() < 8) {
            throw new \Exception('Failed to extract a valid product title.');
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('scraper')->error("❌ Job #{$this->jobNumber} FAILED PERMANENTLY: {$exception->getMessage()}");

        // Try to mark scraper job as failed if not already done
        try {
            $scraperJob = ScraperJob::find($this->scraperJobId);
            if ($scraperJob && $scraperJob->status !== 'failed') {
                $scraperJob->markAsFailed($exception->getMessage());
            }
        } catch (\Exception $e) {
            Log::channel('scraper')->error("Failed to update scraper job status: {$e->getMessage()}");
        }
    }

    /**
     * Validate if URL is a valid product URL.
     */
    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function assertAllowedSource(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (self::BLOCKED_HOST_KEYWORDS as $keyword) {
            if ($host !== '' && str_contains($host, $keyword)) {
                throw new \Exception('Scraping from marketplaces is currently disabled.');
            }
        }
    }

    /**
     * Create product from scraped data.
     */
    private function createProduct(array $data): ?Product
    {
        try {
            // Validate required fields
            if (empty($data['name']) || empty($data['price'])) {
                Log::channel('scraper')->error('❌ Missing required fields: name or price');

                return null;
            }

            // Find or create brand
            $brand = $this->findOrCreateBrand($data['brand'] ?? 'Unknown');

            // Find or create category
            $category = $this->findOrCreateCategory($data['category'] ?? 'Uncategorized');

            // Generate slug
            $slug = Str::slug($data['name']);

            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                ++$counter;
            }

            // Create product
            $product = Product::create([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'price' => $this->parsePrice($data['price']),
                'image' => null,
                'image_url' => $data['image_url'] ?? null,
                'source_url' => $this->url,
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'is_active' => true,
            ]);

            Log::channel('scraper')->info("✅ Product created: {$product->name} (ID: {$product->id})");

            return $product;
        } catch (\Exception $e) {
            Log::channel('scraper')->error("❌ Error creating product: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Find or create brand.
     */
    private function findOrCreateBrand(string $brandName): Brand
    {
        return Brand::firstOrCreate(
            ['name' => $brandName],
            [
                'slug' => Str::slug($brandName),
                'description' => 'Auto-imported brand',
                'is_active' => true,
            ]
        );
    }

    /**
     * Find or create category.
     */
    private function findOrCreateCategory(string $categoryName): Category
    {
        return Category::firstOrCreate(
            ['name' => $categoryName],
            [
                'slug' => Str::slug($categoryName),
                'description' => 'Auto-generated category',
                'is_active' => true,
            ]
        );
    }

    /**
     * Parse price from various formats.
     *
     * @param mixed $price
     */
    private function parsePrice($price): float
    {
        if (is_numeric($price)) {
            return (float) $price;
        }

        // Remove non-numeric characters except decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', (string) $price);

        return (float) $cleaned;
    }
}
