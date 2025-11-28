<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Contracts\CacheServiceContract;
use App\Services\SEOService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CacheServiceContract $cache,
        private readonly SEOService $seoService
    ) {
    }

    /**
     * Display a listing of active categories.
     */
    public function index(Request $request): View
    {
        try {
            $perPage = (int) $request->query('per_page', 12);
            $page = (int) $request->query('page', 1);

            $cacheKey = sprintf('categories:paginated:per_page:%d:page:%d', $perPage, $page);
            $categories = $this->cache->remember(
                $cacheKey,
                3600,
                function () use ($perPage) {
                    return Category::query()
                        ->active()
                        ->withCount('products')
                        ->orderBy('name')
                        ->paginate($perPage);
                },
                ['categories']
            );

            return view('categories.index_clean', compact('categories'));
        } catch (\Throwable $e) {
            Log::error('Categories index failure', [
                'exception' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500);
        }
    }

    /**
     * Display a single category and its products.
     */
    public function show(string $slug, Request $request): View
    {
        try {
            $category = $this->cache->remember(
                'categories:by_slug:'.$slug,
                3600,
                static function () use ($slug) {
                    return Category::query()->active()->where('slug', $slug)->firstOrFail();
                },
                ['categories']
            );

            $perPage = (int) $request->query('per_page', 12);
            $page = (int) $request->query('page', 1);
            $productsKey = sprintf('category:%d:products:per_page:%d:page:%d', $category->id, $perPage, $page);

            $products = $this->cache->remember(
                $productsKey,
                3600,
                function () use ($category, $perPage) {
                    return Product::query()
                        ->active()
                        ->with(['category', 'brand'])
                        ->where('category_id', $category->id)
                        ->orderBy('name')
                        ->paginate($perPage);
                },
                ['products', 'categories']
            );

            $wishlistProductIds = auth()->check()
                ? auth()->user()->wishlist()->pluck('products.id')->all()
                : [];

            $seoMeta = $this->seoService->generateMetaData($category, 'Category');

            return view('categories.show', [
                'category' => $category,
                'products' => $products,
                'wishlistProductIds' => $wishlistProductIds,
                'seoMeta' => $seoMeta,
            ]);
        } catch (\Throwable $e) {
            Log::error('Category show failure', [
                'exception' => $e->getMessage(),
                'slug' => $slug,
                'url' => $request->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500);
        }
    }
}
