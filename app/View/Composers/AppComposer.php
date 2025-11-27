<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class AppComposer
{
    /**
     * Compose the view with global data.
     */
    public function compose(View $view): void
    {
        $view->with([
            'navLanguages' => $this->getLanguages(),
            'navCurrencies' => $this->getCurrencies(),
            'navCountries' => $this->getCountries(),
            'navCategories' => $this->getCategories(),
            'navBrands' => $this->getBrands(),
            'isRTL' => $this->isRTL(),
            'i18nHierarchy' => $this->getI18nHierarchy(),
        ]);
    }

    /**
     * Get active languages.
     *
     * @return array<array<array<int|string>>>
     *
     * @psalm-return list<array<string, array<string, int|string>>>
     */
    private function getLanguages(): array
    {
        $mapper
            /**
             * @return array<bool|string>
             *
             * @psalm-return array{code: string, name: string, native_name: string, direction: string, is_current: bool}
             */
            = static fn(Language $language): array => [
                'code' => $language->code ?? '',
                'name' => $language->name ?? '',
                'native_name' => $language->native_name ?? '',
                'direction' => $language->direction ?? 'ltr',
                'is_current' => app()->getLocale() === $language->code,
            ];

        return $this->getCachedData('languages', Language::class, $mapper, 'sort_order', 3600);
    }

    /**
     * Get active currencies.
     *
     * @return array<array<array<int|string>>>
     *
     * @psalm-return list<array<string, array<string, int|string>>>
     */
    private function getCurrencies(): array
    {
        $currentCurrency = session('currency', config('app.default_currency', 'USD'));

        /**
         * @return array<string|bool>
         *
         * @psalm-return array{code: string, name: string, symbol: string, is_current: bool}
         */
        $mapper = function (Currency $currency) use ($currentCurrency): array {
            return [
                'code' => $currency->code ?? '',
                'name' => $currency->name ?? '',
                'symbol' => $currency->symbol ?? '',
                'is_current' => $currentCurrency === $currency->code,
            ];
        };

        return $this->getCachedData('currencies', Currency::class, $mapper, 'sort_order', 3600);
    }

    /**
     * Get active countries.
     *
     * @return array<array<array<int|string>>>
     *
     * @psalm-return list<array<string, array<string, int|string|bool|null>>>
     */
    private function getCountries(): array
    {
        $currentCountry = session('locale_country', null);

        /**
         * @return array{code: string, name: string, native_name: string, flag?: string|null, is_current: bool}
         */
        $mapper = function (Country $country) use ($currentCountry): array {
            return [
                'code' => $country->code ?? '',
                'name' => $country->name ?? '',
                'native_name' => $country->native_name ?? '',
                'flag' => $country->flag_emoji ?? null,
                'is_current' => (null !== $currentCountry) && ($currentCountry === $country->code),
            ];
        };

        return $this->getCachedData('countries', Country::class, $mapper, 'sort_order', 3600);
    }

    /**
     * Get active categories.
     *
     * @return array<array<array<int|string>>>
     *
     * @psalm-return list<array<string, array<string, int|string>>>
     */
    private function getCategories(): array
    {
        $mapper
            /**
             * @return array<int|string>
             *
             * @psalm-return array{id: int, name: string, slug: string, url: string}
             */
            = static fn(Category $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name ?? '',
                'slug' => $category->slug ?? '',
                'url' => $category->slug ? route('categories.show', $category->slug) : '',
            ];

        // Cache categories for 240 minutes (4 hours) as they change infrequently
        return $this->getCachedData('categories_menu', Category::class, $mapper, 'name', 14400);
    }

    /**
     * Get active brands.
     *
     * @return array<array<array<int|string>>>
     *
     * @psalm-return list<array<string, array<string, int|string>>>
     */
    private function getBrands(): array
    {
        $mapper
            /**
             * @return array<int|string>
             *
             * @psalm-return array{id: int, name: string, slug: string, logo: string|null, url: string}
             */
            = static fn(Brand $brand): array => [
                'id' => (int) $brand->id,
                'name' => $brand->name ?? '',
                'slug' => $brand->slug ?? '',
                'logo' => $brand->logo_url,
                'url' => $brand->slug ? route('brands.show', $brand->slug) : '',
            ];

        // Cache brands for 240 minutes (4 hours) as they change infrequently
        return $this->getCachedData('brands_menu', Brand::class, $mapper, 'name', 14400);
    }

    /**
     * Check if current locale is RTL.
     */
    private function isRTL(): bool
    {
        $rtlLocales = ['ar', 'ur', 'fa', 'he'];

        return \in_array(app()->getLocale(), $rtlLocales, true);
    }

    /**
     * Get hierarchical i18n data for dynamic dropdowns.
     * Returns: { languages: [...], hierarchy: { lang_code: { countries: [...] } } }
     */
    private function getI18nHierarchy(): array
    {
        try {
            return cache()->remember('i18n_hierarchy', 3600, function () {
                // Get all countries with their relationships
                $countries = Country::with(['language', 'currency'])
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                // Build hierarchy: language_code => [countries]
                $hierarchy = [];
                foreach ($countries as $country) {
                    $langCode = $country->language->code ?? 'en';

                    if (!isset($hierarchy[$langCode])) {
                        $hierarchy[$langCode] = [];
                    }

                    $hierarchy[$langCode][] = [
                        'code' => $country->code,
                        'name' => $country->name,
                        'native_name' => $country->native_name,
                        'flag' => $country->flag_emoji,
                        'currency_code' => $country->currency->code ?? 'USD',
                        'currency_symbol' => $country->currency->symbol ?? '$',
                    ];
                }

                // Get current selections
                $currentLang = app()->getLocale();
                $currentCountry = session('locale_country', null);
                $currentCurrency = session('currency', config('app.default_currency', 'USD'));

                return [
                    'hierarchy' => $hierarchy,
                    'current' => [
                        'language' => $currentLang,
                        'country' => $currentCountry,
                        'currency' => $currentCurrency,
                    ],
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Failed to build i18n hierarchy', ['error' => $e->getMessage()]);
            return [
                'hierarchy' => [],
                'current' => [
                    'language' => 'en',
                    'country' => null,
                    'currency' => 'USD',
                ],
            ];
        }
    }

    /**
     * @param class-string $modelClass
     *
     * @return list<array<string, array<string, int|string>>>
     */
    private function getCachedData(
        string $key,
        string $modelClass,
        callable $mapper,
        string $orderBy = 'name',
        int $ttl = 1800
    ): array {
        // Defensive: if DB is unavailable, avoid 500s and return an empty dataset
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            Log::error('DB unavailable in AppComposer', [
                'key' => $key,
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        /** @var list<array<string, array<string, int|string>>> $result */
        try {
            $result = cache()->remember(
                $key,
                $ttl,
                /**
                 * @psalm-return array<int, mixed>
                 */
                static function () use ($modelClass, $orderBy, $mapper): array {
                    /** @var Builder<
                     *     Model
                     * > $query */
                    $query = $modelClass::where('is_active', true);

                    $collection = $query->orderBy($orderBy)->get();

                    /** @var Collection<int, array<string, array>> $mapped */
                    $mapped = $collection->map($mapper);

                    // Convert array items to objects for view compatibility
                    return $mapped->map(fn($item) => (object) $item)->values()->all();
                }
            );
        } catch (\Throwable $e) {
            Log::error('AppComposer cache build failed', [
                'key' => $key,
                'model' => $modelClass,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return \is_array($result) ? array_values($result) : [];
    }
}
