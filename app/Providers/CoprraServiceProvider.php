<?php

/** @psalm-suppress UnusedClass */

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class CoprraServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[\Override]
    public function register(): void
    {
        // Merge COPRRA configuration
        $this->mergeConfigFrom(
            config_path('coprra.php'),
            'coprra'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share COPRRA configuration with all views
        View::share('coprraName', config('coprra.name', 'COPRRA'));
        View::share('coprraVersion', config('coprra.version', '1.0.0'));
        View::share('defaultCurrency', config('coprra.default_currency', 'USD'));
        View::share('defaultLanguage', config('coprra.default_language', 'en'));

        // Register Blade directives
        Blade::directive('currency', static function ($expression): string {
            return "<?php echo number_format((float) {$expression}, 2); ?>";
        });

        Blade::directive('pricecompare', static function ($expression): string {
            return "<?php echo \\App\\Helpers\\PriceHelper::formatPrice((float) {$expression}); ?>";
        });

        Blade::directive('rtl', static function (): string {
            return "<?php echo (in_array(app()->getLocale(), ['ar', 'he', 'fa']) ? 'rtl' : 'ltr'); ?>";
        });
    }
}
