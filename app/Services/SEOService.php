<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Service for generating SEO meta data and structured data.
 */
final readonly class SEOService
{
    public function __construct(
        private UrlGenerator $urlGenerator,
        private ConfigRepository $configRepository,
        private Str $str
    ) {}

    /**
     * Generate SEO meta data for a model.
     *
     * @return array<string>
     *
     * @psalm-return array{title: string, description: string, keywords: string, og_title: string, og_description: string, og_image: string, og_type: 'product'|'website', og_url: string, canonical: string, robots: 'index, follow'}
     */
    public function generateMetaData(Model $model, ?string $type = null): array
    {
        $type = null !== $type ? $type : class_basename($model);

        return match ($type) {
            'Product' => $model instanceof Product ? $this->generateProductMeta($model) : $this->generateDefaultMeta(),
            'Category' => $model instanceof Category
                ? $this->generateCategoryMeta($model)
                : $this->generateDefaultMeta(),
            'Brand' => $model instanceof Brand ? $this->generateBrandMeta($model) : $this->generateDefaultMeta(),
            'Store' => $model instanceof Store ? $this->generateStoreMeta($model) : $this->generateDefaultMeta(),
            default => $this->generateDefaultMeta(),
        };
    }

    /**
     * Validate SEO meta data.
     *
     * @param  array<string, string|* @method static \App\Models\Brand create(array<string, string|bool|null>  $metaData
     *
     * @return array<string>
     *
     * @psalm-return array<int, string>
     */
    public function validateMetaData(array $metaData): array
    {
        $issues = [];

        $this->validateTitle($metaData, $issues);
        $this->validateDescription($metaData, $issues);
        $this->validateKeywords($metaData, $issues);
        $this->validateCanonical($metaData, $issues);

        return $issues;
    }

    /**
     * Generate meta data for a product.
     *
     * @return array<string>
     *
     * @psalm-return array{title: string, description: string, keywords: string, og_title: string, og_description: string, og_image: string, og_type: 'product', og_url: string, canonical: string, robots: 'index, follow'}
     */
    private function generateProductMeta(Product $product): array
    {
        $productName = $this->safeCastToString($product->name);
        $title = $productName.' - Coprra';
        $productDescription = $product->description && '' !== $product->description
            ? $product->description
            : $productName;
        $description = $this->generateDescription($this->safeCastToString($productDescription));
        $keywords = $this->generateKeywords($product);

        $imageUrl = $product->image_url && '' !== $product->image_url
            ? $product->image_url
            : $this->urlGenerator->asset('images/default-product.png');

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $this->safeCastToString($imageUrl),
            'og_type' => 'product',
            'og_url' => $this->safeCastToString(
                $this->urlGenerator->route('products.show', $product->slug)
            ),
            'canonical' => $this->safeCastToString(
                $this->urlGenerator->route('products.show', $product->slug)
            ),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Generate Product Schema (JSON-LD) for structured data.
     *
     * @return array<string, mixed>
     */
    public function generateProductSchema(Product $product): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->safeCastToString($product->name),
            'description' => $this->generateDescription(
                $this->safeCastToString($product->description ?: $product->name)
            ),
            'sku' => (string) $product->id,
            'url' => $this->safeCastToString(
                $this->urlGenerator->route('products.show', $product->slug)
            ),
        ];

        // Add image
        if ($product->image_url && '' !== $product->image_url) {
            $schema['image'] = $this->safeCastToString($product->image_url);
        }

        // Add brand
        if ($product->brand && ($product->brand->name ?? '') !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $this->safeCastToString($product->brand->name),
            ];
        }

        // Add offers
        $price = $product->price;
        if ($price !== null && $price > 0) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => 'USD', // Currency is set to USD by default; dynamic currency support can be added in future updates
                'price' => (string) number_format((float) $price, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
                'url' => $this->safeCastToString(
                    $this->urlGenerator->route('products.show', $product->slug)
                ),
            ];
        }

        return $schema;
    }

    /**
     * Generate meta data for a category.
     *
     * @return array<string>
     *
     * @psalm-return array{title: string, description: string, keywords: string, og_title: string, og_description: string, og_image: string, og_type: 'website', og_url: string, canonical: string, robots: 'index, follow'}
     */
    private function generateCategoryMeta(Category $category): array
    {
        $categoryName = $this->safeCastToString($category->name);
        $title = $this->generateTitle($categoryName.' Products');
        $description = $this->generateDescription(
            'Compare the best '.$categoryName.' products and find the best prices on Coprra.'
        );

        $imageUrl = $category->image_url && '' !== $category->image_url
            ? $category->image_url
            : $this->urlGenerator->asset('images/default-category.png');

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords($category),
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $this->safeCastToString($imageUrl),
            'og_type' => 'website',
            'og_url' => $this->safeCastToString(
                $this->urlGenerator->route('categories.show', $category->slug)
            ),
            'canonical' => $this->safeCastToString(
                $this->urlGenerator->route('categories.show', $category->slug)
            ),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Generate meta data for a brand.
     *
     * @return array<string>
     *
     * @psalm-return array{title: string, description: string, keywords: string, og_title: string, og_description: string, og_image: string, og_type: 'website', og_url: string, canonical: string, robots: 'index, follow'}
     */
    private function generateBrandMeta(Brand $brand): array
    {
        $brandName = $this->safeCastToString($brand->name);
        $title = $this->generateTitle($brandName.' Products');
        $description = $this->generateDescription(
            'Discover and compare the latest '.$brandName.' products on Coprra.'
        );

        $imageUrl = $brand->logo_url && '' !== $brand->logo_url
            ? $brand->logo_url
            : $this->urlGenerator->asset('images/default-brand.png');

        $brandUrl = Route::has('brands.show')
            ? $this->urlGenerator->route('brands.show', $brand->slug)
            : $this->urlGenerator->to('brands/'.$this->safeCastToString($brand->slug));

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords($brand),
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $this->safeCastToString($imageUrl),
            'og_type' => 'website',
            'og_url' => $this->safeCastToString($brandUrl),
            'canonical' => $this->safeCastToString($brandUrl),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Generate meta data for a store.
     *
     * @return array<string>
     *
     * @psalm-return array{title: string, description: string, keywords: string, og_title: string, og_description: string, og_image: string, og_type: 'website', og_url: string, canonical: string, robots: 'index, follow'}
     */
    private function generateStoreMeta(Store $store): array
    {
        $title = $this->generateTitle($this->safeCastToString($store->name).' - Store');
        $storeDescription = $store->description && '' !== $store->description
            ? $store->description
            : 'Shop at '.$this->safeCastToString($store->name).' and compare prices';
        $description = $this->generateDescription($this->safeCastToString($storeDescription));

        $imageUrl = $store->logo_url && '' !== $store->logo_url
            ? $store->logo_url
            : $this->urlGenerator->asset('images/default-store.png');

        $storeUrl = Route::has('stores.show')
            ? $this->urlGenerator->route('stores.show', $store->slug)
            : $this->urlGenerator->to('stores/'.$this->safeCastToString($store->slug));

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->generateKeywords($store),
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $this->safeCastToString($imageUrl),
            'og_type' => 'website',
            'og_url' => $this->safeCastToString($storeUrl),
            'canonical' => $this->safeCastToString($storeUrl),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Generate default meta data.
     *
     * @return array<string>
     *
     * @psalm-return array{title: string, description: 'Compare prices across multiple stores and find the best deals', keywords: 'price comparison, shopping, deals, best prices, online shopping', og_title: string, og_description: 'Compare prices across multiple stores and find the best deals', og_image: string, og_type: 'website', og_url: string, canonical: string, robots: 'index, follow'}
     */
    private function generateDefaultMeta(): array
    {
        $appName = $this->safeCastToString($this->configRepository->get('app.name', 'COPRRA'));
        $description = 'Compare prices across multiple stores and find the best deals';

        return [
            'title' => $appName.' - Price Comparison Platform',
            'description' => $description,
            'keywords' => 'price comparison, shopping, deals, best prices, online shopping',
            'og_title' => $appName,
            'og_description' => $description,
            'og_image' => $this->safeCastToString(
                $this->urlGenerator->asset('images/og-default.png')
            ),
            'og_type' => 'website',
            'og_url' => $this->safeCastToString($this->urlGenerator->to('/')),
            'canonical' => $this->safeCastToString($this->urlGenerator->to('/')),
            'robots' => 'index, follow',
        ];
    }

    /**
     * Generate optimized title (50-60 characters).
     */
    private function generateTitle(string $title): string
    {
        $appName = $this->safeCastToString($this->configRepository->get('app.name', 'COPRRA'));
        $maxLength = 60;

        // If title is too long, truncate it
        if (\strlen($title) > ($maxLength - \strlen($appName) - 3)) {
            $title = $this->str->limit(
                $title,
                $maxLength - \strlen($appName) - 6,
                ''
            );
        }

        return $title.' - '.$appName;
    }

    /**
     * Generate optimized description (150-160 characters).
     */
    private function generateDescription(string $description): string
    {
        $maxLength = 160;

        // Strip HTML tags
        $description = strip_tags($description);

        // Truncate if too long
        if (\strlen($description) > $maxLength) {
            $description = $this->str->limit($description, $maxLength - 3, '...');
        }

        return $description;
    }

    /**
     * Generate keywords from model.
     */
    private function generateKeywords(Model $model): string
    {
        $keywords = [];

        $this->addModelNameToKeywords($model, $keywords);
        $this->addProductSpecificKeywords($model, $keywords);
        $this->addGenericKeywords($keywords);

        return $this->formatKeywords($keywords);
    }

    /**
     * Validate title field.
     *
     * @param array<string,?string> $metaData
     * @param array<int,string>     $issues
     */
    private function validateTitle(array $metaData, array &$issues): void
    {
        if (($metaData['title'] ?? '') === '') {
            $issues[] = 'Title is missing';

            return;
        }

        $titleLength = mb_strlen($this->safeCastToString($metaData['title']));

        if ($titleLength < 30) {
            $issues[] = 'Title is too short (minimum 30 characters)';
        } elseif ($titleLength > 60) {
            $issues[] = 'Title is too long (maximum 60 characters)';
        }
    }

    /**
     * Validate description field.
     *
     * @param array<string,?string> $metaData
     * @param array<int,string>     $issues
     */
    private function validateDescription(array $metaData, array &$issues): void
    {
        if (($metaData['description'] ?? '') === '') {
            $issues[] = 'Description is missing';

            return;
        }

        $descriptionLength = mb_strlen($this->safeCastToString($metaData['description']));

        if ($descriptionLength < 70) {
            $issues[] = 'Description is too short (minimum 70 characters)';
        } elseif ($descriptionLength > 160) {
            $issues[] = 'Description is too long (maximum 160 characters)';
        }
    }

    /**
     * Validate keywords field.
     *
     * @param array<string,?string> $metaData
     * @param array<int,string>     $issues
     */
    private function validateKeywords(array $metaData, array &$issues): void
    {
        if (($metaData['keywords'] ?? '') === '') {
            $issues[] = 'Keywords are missing';
        }
    }

    /**
     * Validate canonical URL field.
     *
     * @param array<string,?string> $metaData
     * @param array<int,string>     $issues
     */
    private function validateCanonical(array $metaData, array &$issues): void
    {
        if (($metaData['canonical'] ?? '') === '') {
            $issues[] = 'Canonical URL is missing';
        }
    }

    /**
     * Add model name to keywords.
     *
     * @param array<int,string> $keywords
     */
    private function addModelNameToKeywords(Model $model, array &$keywords): void
    {
        if (isset($model->name)) {
            $keywords[] = $this->safeCastToString($model->name);
        }
    }

    /**
     * Add product-specific keywords if model is a Product.
     *
     * @param array<int,string> $keywords
     */
    private function addProductSpecificKeywords(Model $model, array &$keywords): void
    {
        if (! $model instanceof Product) {
            return;
        }

        $this->addCategoryKeyword($model, $keywords);
        $this->addBrandKeyword($model, $keywords);
    }

    /**
     * Add category keyword if available.
     *
     * @param array<int,string> $keywords
     */
    private function addCategoryKeyword(Product $product, array &$keywords): void
    {
        if ($product->category && ($product->category->name ?? '') !== '') {
            $keywords[] = $this->safeCastToString($product->category->name);
        }
    }

    /**
     * Add brand keyword if available.
     *
     * @param array<int,string> $keywords
     */
    private function addBrandKeyword(Product $product, array &$keywords): void
    {
        if ($product->brand && ($product->brand->name ?? '') !== '') {
            $keywords[] = $this->safeCastToString($product->brand->name);
        }
    }

    /**
     * Add generic keywords.
     *
     * @param array<int,string> $keywords
     */
    private function addGenericKeywords(array &$keywords): void
    {
        $keywords[] = 'price comparison';
        $keywords[] = 'best price';
        $keywords[] = 'deals';
    }

    /**
     * Format and clean keywords array.
     *
     * @param array<int,string> $keywords
     */
    private function formatKeywords(array $keywords): string
    {
        $keywords = array_unique($keywords);

        return implode(', ', $keywords);
    }

    /**
     * Generate structured data for a product (alias for generateProductSchema).
     *
     * @return array<string, mixed>
     */
    public function generateStructuredData(Product $product): array
    {
        return $this->generateProductSchema($product);
    }

    /**
     * Generate breadcrumb structured data.
     *
     * @param array<int, array{name: string, url: string}> $breadcrumbs
     *
     * @return array<string, mixed>
     */
    public function generateBreadcrumbData(array $breadcrumbs): array
    {
        $itemListElement = [];
        $position = 1;

        foreach ($breadcrumbs as $breadcrumb) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $this->safeCastToString($breadcrumb['name'] ?? ''),
                'item' => $this->safeCastToString($breadcrumb['url'] ?? ''),
            ];
            ++$position;
        }

        return [
            '@context' => 'https://schema.org/',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement,
        ];
    }

    /**
     * Safely cast a value to a string.
     */
    private function safeCastToString(float|int|object|string|null $value): string
    {
        if (\is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (\is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }
}
