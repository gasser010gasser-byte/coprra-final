<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the public sitemap.xml file.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) (config('app.url') ?? URL::to('/')), '/');

        $urls = [];
        $now = now()->toAtomString();

        $urls[] = [
            'loc' => $baseUrl . '/',
            'lastmod' => $now,
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        $categoryQuery = Category::query();
        if (Schema::hasColumn(Category::make()->getTable(), 'is_active')) {
            $categoryQuery->where('is_active', true);
        }

        $categoryQuery->orderBy('updated_at', 'desc')->each(function (Category $category) use (&$urls, $baseUrl): void {
            try {
                $loc = $baseUrl . route('categories.show', $category->slug, false);
            } catch (\Throwable) {
                $loc = $baseUrl . '/categories/' . $category->slug;
            }

            $lastModified = optional($category->updated_at ?? $category->created_at)->toAtomString() ?? now()->toAtomString();

            $urls[] = [
                'loc' => $loc,
                'lastmod' => $lastModified,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        });

        $productQuery = Product::query();
        if (Schema::hasColumn(Product::make()->getTable(), 'is_active')) {
            $productQuery->where('is_active', true);
        }

        $productQuery->latest('updated_at')->each(function (Product $product) use (&$urls, $baseUrl): void {
            try {
                $loc = $baseUrl . route('products.show', $product->slug, false);
            } catch (\Throwable) {
                $loc = $baseUrl . '/products/' . $product->slug;
            }

            $lastModified = optional($product->updated_at ?? $product->created_at)->toAtomString() ?? now()->toAtomString();

            $urls[] = [
                'loc' => $loc,
                'lastmod' => $lastModified,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        });

        $xmlLines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $entry) {
            $xmlLines[] = '  <url>';
            $xmlLines[] = '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . '</loc>';
            $xmlLines[] = '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1) . '</lastmod>';
            $xmlLines[] = '    <changefreq>' . htmlspecialchars($entry['changefreq'], ENT_XML1) . '</changefreq>';
            $xmlLines[] = '    <priority>' . htmlspecialchars($entry['priority'], ENT_XML1) . '</priority>';
            $xmlLines[] = '  </url>';
        }

        $xmlLines[] = '</urlset>';

        File::put(public_path('sitemap.xml'), implode(PHP_EOL, $xmlLines) . PHP_EOL);

        $this->info('Sitemap generated successfully at ' . public_path('sitemap.xml'));

        return self::SUCCESS;
    }
}
