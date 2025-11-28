<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Language;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use PDO;

class MigrateDataFromSqlite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coprra:migrate-data-from-sqlite
                            {sqlite-path=/home/u990109832/temp_db/backup_13mb.sqlite : Path to SQLite database file}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from SQLite backup to MySQL production database using Eloquent ORM';

    private PDO $sqlite;

    private int $migrated = 0;

    private int $skipped = 0;

    private bool $dryRun = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sqlitePath = $this->argument('sqlite-path');
        $this->dryRun = $this->option('dry-run');

        if (! file_exists($sqlitePath)) {
            $this->error("SQLite database not found at: {$sqlitePath}");

            return Command::FAILURE;
        }

        $this->info('═══════════════════════════════════════════════════');
        $this->info('  COPRRA Data Migration: SQLite → MySQL');
        $this->info('═══════════════════════════════════════════════════');
        $this->info("Source: {$sqlitePath}");
        $this->info('Target: MySQL (' . config('database.connections.mysql.database') . ')');
        $this->info('Mode: ' . ($this->dryRun ? 'DRY RUN (no changes)' : 'LIVE MIGRATION'));
        $this->info('═══════════════════════════════════════════════════');
        $this->newLine();

        try {
            // Connect to SQLite
            $this->sqlite = new PDO('sqlite:' . $sqlitePath);
            $this->sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if ($this->dryRun) {
                $this->warn('⚠️  DRY RUN MODE: No changes will be made to the database');
                $this->newLine();
            }

            // Migrate in order (respecting foreign keys)
            $this->migrateCurrencies();
            $this->migrateLanguages();
            $this->migrateCategories();
            $this->migrateBrands();
            $this->migrateStores();
            $this->migrateProducts();
            $this->migratePriceOffers();

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════');
            $this->info('  Migration Complete!');
            $this->info('═══════════════════════════════════════════════════');
            $this->info("✅ Total records migrated: {$this->migrated}");
            $this->info("⏭️  Total records skipped: {$this->skipped}");
            $this->info('═══════════════════════════════════════════════════');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function migrateCurrencies(): void
    {
        $this->info('📦 Migrating currencies...');
        $records = $this->sqlite->query('SELECT * FROM currencies')->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $record) {
            if (! $this->dryRun) {
                Currency::updateOrCreate(
                    ['code' => $record['code']],
                    [
                        'name' => $record['name'],
                        'symbol' => $record['symbol'],
                        'exchange_rate' => $record['exchange_rate'] ?? 1.0,
                        'is_active' => $record['is_active'] ?? true,
                    ]
                );
            }
            ++$count;
        }

        $this->migrated += $count;
        $this->info("✅ Migrated {$count} currencies");
    }

    private function migrateLanguages(): void
    {
        $this->info('📦 Migrating languages...');
        $records = $this->sqlite->query('SELECT * FROM languages')->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $record) {
            if (! $this->dryRun) {
                Language::updateOrCreate(
                    ['code' => $record['code']],
                    [
                        'name' => $record['name'],
                        'native_name' => $record['native_name'] ?? $record['name'],
                        'direction' => $record['direction'] ?? 'ltr',
                        'is_active' => $record['is_active'] ?? true,
                    ]
                );
            }
            ++$count;
        }

        $this->migrated += $count;
        $this->info("✅ Migrated {$count} languages");
    }

    private function migrateCategories(): void
    {
        $this->info('📦 Migrating categories...');
        $records = $this->sqlite->query('SELECT * FROM categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $record) {
            if (! $this->dryRun) {
                Category::updateOrCreate(
                    ['slug' => $record['slug']],
                    [
                        'name' => $record['name'],
                        'description' => $record['description'] ?? null,
                        'parent_id' => $record['parent_id'] ?? null,
                        'image_url' => $record['image_url'] ?? null,
                        'icon' => $record['icon'] ?? null,
                        'is_active' => $record['is_active'] ?? true,
                        'display_order' => $record['display_order'] ?? 0,
                        'created_at' => $record['created_at'] ?? now(),
                        'updated_at' => $record['updated_at'] ?? now(),
                    ]
                );
            }
            ++$count;
        }

        $this->migrated += $count;
        $this->info("✅ Migrated {$count} categories");
    }

    private function migrateBrands(): void
    {
        $this->info('📦 Migrating brands...');
        $records = $this->sqlite->query('SELECT * FROM brands')->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $record) {
            if (! $this->dryRun) {
                Brand::updateOrCreate(
                    ['slug' => $record['slug']],
                    [
                        'name' => $record['name'],
                        'description' => $record['description'] ?? null,
                        'logo_url' => $record['logo_url'] ?? null,
                        'website_url' => $record['website_url'] ?? null,
                        'is_active' => $record['is_active'] ?? true,
                        'created_at' => $record['created_at'] ?? now(),
                        'updated_at' => $record['updated_at'] ?? now(),
                    ]
                );
            }
            ++$count;
        }

        $this->migrated += $count;
        $this->info("✅ Migrated {$count} brands");
    }

    private function migrateStores(): void
    {
        $this->info('📦 Migrating stores...');
        $records = $this->sqlite->query('SELECT * FROM stores')->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        // Get default currency (USD or first available) for stores without currency
        $defaultCurrency = Currency::where('code', 'USD')->first() ?? Currency::first();

        if (! $defaultCurrency) {
            $this->error('No currencies found in database. Please migrate currencies first.');

            return;
        }

        foreach ($records as $record) {
            if (! $this->dryRun) {
                // Get currency_id from currencies table if it exists, otherwise use default
                $currencyId = $defaultCurrency->id;
                if (isset($record['currency_id'])) {
                    $currency = Currency::find($record['currency_id']);
                    if ($currency) {
                        $currencyId = $currency->id;
                    }
                }

                Store::updateOrCreate(
                    ['slug' => $record['slug']],
                    [
                        'name' => $record['name'],
                        'description' => $record['description'] ?? null,
                        'logo_url' => $record['logo_url'] ?? null,
                        'website_url' => $record['website_url'] ?? null,
                        'country_code' => $record['country_code'] ?? 'US',
                        'is_active' => $record['is_active'] ?? true,
                        'priority' => $record['priority'] ?? 0,
                        'affiliate_base_url' => $record['affiliate_base_url'] ?? null,
                        'affiliate_code' => $record['affiliate_code'] ?? null,
                        'api_config' => $record['api_config'] ?? null,
                        'currency_id' => $currencyId,
                        'created_at' => $record['created_at'] ?? now(),
                        'updated_at' => $record['updated_at'] ?? now(),
                    ]
                );
            }
            ++$count;
        }

        $this->migrated += $count;
        $this->info("✅ Migrated {$count} stores");
    }

    private function migrateProducts(): void
    {
        $this->info('📦 Migrating products (164 expected)...');
        $records = $this->sqlite->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        // Disable model events to prevent cache clearing issues during migration
        Product::withoutEvents(function () use ($records, &$count) {
            foreach ($records as $record) {
                if (! $this->dryRun) {
                    // Get brand_id and category_id if they exist
                    $brandId = null;
                    if (isset($record['brand_id'])) {
                        $brand = Brand::find($record['brand_id']);
                        $brandId = $brand?->id;
                    }

                    $categoryId = null;
                    if (isset($record['category_id'])) {
                        $category = Category::find($record['category_id']);
                        $categoryId = $category?->id;
                    }

                    Product::updateOrCreate(
                        ['slug' => $record['slug']],
                        [
                            'name' => $record['name'],
                            'description' => $record['description'] ?? null,
                            'long_description' => $record['long_description'] ?? null,
                            'sku' => $record['sku'] ?? null,
                            'barcode' => $record['barcode'] ?? null,
                            'brand_id' => $brandId,
                            'category_id' => $categoryId,
                            'current_price' => $record['current_price'] ?? 0,
                            'original_price' => $record['original_price'] ?? null,
                            'image_url' => $record['image_url'] ?? null,
                            'images' => $record['images'] ?? null,
                            'specifications' => $record['specifications'] ?? null,
                            'features' => $record['features'] ?? null,
                            'is_active' => $record['is_active'] ?? true,
                            'is_featured' => $record['is_featured'] ?? false,
                            'stock_status' => $record['stock_status'] ?? 'in_stock',
                            'view_count' => $record['view_count'] ?? 0,
                            'average_rating' => $record['average_rating'] ?? null,
                            'created_at' => $record['created_at'] ?? now(),
                            'updated_at' => $record['updated_at'] ?? now(),
                        ]
                    );
                }
                ++$count;
            }
        });

        $this->migrated += $count;
        $this->info("✅ Migrated {$count} products");
    }

    private function migratePriceOffers(): void
    {
        $this->info('📦 Migrating price offers...');
        $records = $this->sqlite->query('SELECT * FROM price_offers')->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($records as $record) {
            if (! $this->dryRun) {
                // Get product_id and store_id
                $product = Product::where('slug', function ($query) use ($record) {
                    $query->select('slug')->from('products')->where('id', $record['product_id']);
                })->first();

                $store = Store::find($record['store_id']);

                if ($product && $store) {
                    PriceOffer::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'store_id' => $store->id,
                        ],
                        [
                            'price' => $record['price'],
                            'currency_code' => $record['currency_code'] ?? 'USD',
                            'is_available' => $record['is_available'] ?? true,
                            'shipping_cost' => $record['shipping_cost'] ?? null,
                            'delivery_time' => $record['delivery_time'] ?? null,
                            'last_checked_at' => $record['last_checked_at'] ?? now(),
                            'created_at' => $record['created_at'] ?? now(),
                            'updated_at' => $record['updated_at'] ?? now(),
                        ]
                    );
                }
            }
            ++$count;
        }

        $this->migrated += $count;
        $this->info("✅ Migrated {$count} price offers");
    }
}
