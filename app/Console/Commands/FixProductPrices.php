<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class FixProductPrices extends Command
{
    protected $signature = 'products:fix-prices {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Fix products with NULL or $0.00 prices by assigning realistic test prices';

    public function handle(): int
    {
        $this->info('🔍 Checking for products with missing prices...');

        $productsToFix = Product::where(function ($query) {
            $query->whereNull('price')
                  ->orWhere('price', 0)
                  ->orWhere('price', '0.00');
        })->get();

        if ($productsToFix->isEmpty()) {
            $this->info('✅ All products already have valid prices!');
            return 0;
        }

        $this->warn("Found {$productsToFix->count()} products with missing prices");

        if ($this->option('dry-run')) {
            $this->info('DRY RUN - No changes will be made');
            foreach ($productsToFix as $product) {
                $newPrice = $this->generateRealisticPrice($product);
                $this->line("  [{$product->id}] {$product->name}: \$0.00 → \${$newPrice}");
            }
            return 0;
        }

        $bar = $this->output->createProgressBar($productsToFix->count());
        $bar->start();

        $fixedCount = 0;
        foreach ($productsToFix as $product) {
            $newPrice = $this->generateRealisticPrice($product);
            $product->price = $newPrice;
            $product->save();
            $fixedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Successfully updated {$fixedCount} product prices!");

        return 0;
    }

    private function generateRealisticPrice(Product $product): float
    {
        // Generate realistic prices based on product category/type
        $categoryName = $product->category->name ?? '';
        $productName = strtolower($product->name);

        // Electronics
        if (str_contains($productName, 'laptop') || str_contains($productName, 'notebook')) {
            return (float) rand(699, 2499);
        }
        if (str_contains($productName, 'monitor') || str_contains($productName, 'display')) {
            return (float) rand(199, 899);
        }
        if (str_contains($productName, 'phone') || str_contains($productName, 'smartphone')) {
            return (float) rand(299, 1299);
        }
        if (str_contains($productName, 'tablet') || str_contains($productName, 'ipad')) {
            return (float) rand(329, 1099);
        }
        if (str_contains($productName, 'headphone') || str_contains($productName, 'earphone') || str_contains($productName, 'airpods')) {
            return (float) rand(49, 399);
        }
        if (str_contains($productName, 'keyboard') || str_contains($productName, 'mouse')) {
            return (float) rand(29, 199);
        }
        if (str_contains($productName, 'camera')) {
            return (float) rand(399, 2999);
        }
        if (str_contains($productName, 'tv') || str_contains($productName, 'television')) {
            return (float) rand(399, 1999);
        }

        // Fashion
        if (str_contains($categoryName, 'fashion') || str_contains($categoryName, 'clothing')) {
            if (str_contains($productName, 'shoes') || str_contains($productName, 'sneaker')) {
                return (float) rand(59, 299);
            }
            if (str_contains($productName, 'jacket') || str_contains($productName, 'coat')) {
                return (float) rand(79, 399);
            }
            return (float) rand(29, 149);
        }

        // Home & Garden
        if (str_contains($categoryName, 'home') || str_contains($categoryName, 'garden')) {
            return (float) rand(39, 499);
        }

        // Sports
        if (str_contains($categoryName, 'sport') || str_contains($categoryName, 'fitness')) {
            return (float) rand(29, 599);
        }

        // Books
        if (str_contains($categoryName, 'book') || str_contains($categoryName, 'media')) {
            return (float) rand(9, 59);
        }

        // Default for unknown categories
        return (float) rand(49, 299);
    }
}
