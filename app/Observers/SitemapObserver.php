<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Observer to automatically regenerate sitemap when products are created or updated.
 */
class SitemapObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->regenerateSitemap();
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->regenerateSitemap();
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->regenerateSitemap();
    }

    /**
     * Regenerate sitemap by running the sitemap:generate command.
     */
    private function regenerateSitemap(): void
    {
        try {
            // Run sitemap generation in the background to avoid blocking the request
            Artisan::queue('sitemap:generate');

            Log::info('Sitemap regeneration queued after product change');
        } catch (\Throwable $e) {
            // Log error but don't throw to avoid breaking the main operation
            Log::warning('Failed to queue sitemap regeneration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
