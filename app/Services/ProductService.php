<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\Contracts\CacheServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $repository,
        private readonly CacheServiceContract $cache
    ) {
    }

    /**
     * Get paginated active products with caching.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getPaginatedProducts(int $perPage = 15): LengthAwarePaginator
    {
        $pageParam = request()->get('page', 1);
        $page = is_numeric($pageParam) ? (int) $pageParam : 1;

        $result = $this->cache->remember(
            "products.page.".$page,
            3600,
            function () use ($perPage) {
                return $this->repository->getPaginatedActive($perPage);
            },
            ['products']
        );

        if ($result instanceof LengthAwarePaginator) {
            return $result;
        }

        return new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            $page,
            ['path' => url()->current(), 'pageName' => 'page']
        );
    }

    /**
     * Search products.
     *
     * @param array<string, float|int|string> $filters
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function searchProducts(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Don't cache search results as they're likely to be unique per user
        return $this->repository->search($query, $filters, $perPage);
    }

    /**
     * Get product by slug with caching.
     *
     * @throws \InvalidArgumentException If slug is invalid
     */
    public function getBySlug(string $slug): ?Product
    {
        return $this->repository->findBySlug($slug);
    }

    /**
     * Get related products for a given product.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     *
     * @throws \InvalidArgumentException If limit is invalid
     */
    public function getRelatedProducts(Product $product, int $limit = 4): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getRelated($product, $limit);
    }
}
