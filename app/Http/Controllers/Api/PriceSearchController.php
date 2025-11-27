<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\PriceOffer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PriceSearchController extends BaseApiController
{
    public function bestOffer(Request $request): JsonResponse
    {
        try {
            // Reject invalid types provided via query string or request body
            foreach (
                [
                    $request->input('q'),
                    $request->query('q'),
                    $request->input('query'),
                    $request->query('query'),
                    $request->input('name'),
                    $request->query('name'),
                ] as $value
            ) {
                if (\is_array($value) || \is_object($value) || \is_bool($value)) {
                    return $this->error('Search query is required. Use parameter: q, query, or name', null, 400);
                }
            }
            // Support parameters from query string, request body, or headers
            $productId = $request->query('product_id')
                ?? $request->input('product_id')
                ?? $request->header('product_id');
            $productName = $request->query('product_name')
                ?? $request->input('product_name')
                ?? $request->header('product_name');

            if ((null === $productId) && (null === $productName || '' === $productName)) {
                // If no parameters, return all products as a list
                $queryBuilder = Product::with([
                    'priceOffers' => static function ($query): void {
                        // @var \Illuminate\Database\Eloquent\Builder<\App\Models\PriceOffer> $query
                        $query->where('is_available', true)
                            ->orderBy('price', 'asc')
                            ->with('store:id,name')
                        ;
                    },
                    'brand:id,name',
                    'category:id,name',
                ]);

                /** @var Collection<int, Product> $products */
                $products = $queryBuilder->where('is_active', true)->limit(10)->get();

                if ($products->isEmpty()) {
                    return $this->notFound('No products available');
                }

                return $this->success(
                    $products->map(/**
                     * @return array<scalar>
                     *
                     * @psalm-return array{product_id: int, name: string, price: float|string, store: string, is_available: bool}
                     */
                        static function (Product $product): array {
                            $bestOffer = $product->priceOffers->first();

                            return [
                                'product_id' => $product->id,
                                'name' => $product->name,
                                'price' => $bestOffer ? $bestOffer->price : $product->price,
                                'store' => $bestOffer?->store->name ?? 'Unknown Store',
                                'is_available' => $bestOffer ? (bool) $bestOffer->is_available : true,
                            ];
                        }
                    )->toArray(),
                    'Products retrieved successfully'
                );
            }

            // Find product by ID or name
            $product = null;
            if ($productId) {
                $queryBuilder = Product::with([
                    'priceOffers' => static function ($query): void {
                        // @var \Illuminate\Database\Eloquent\Builder<\App\Models\PriceOffer> $query
                        $query->where('is_available', true)
                            ->orderBy('price', 'asc')
                            ->with('store:id,name')
                        ;
                    },
                    'brand:id,name',
                    'category:id,name',
                ]);
                $product = $queryBuilder->find($productId);
            } elseif ($productName) {
                $productNameStr = \is_string($productName) ? $productName : '';
                $queryBuilder = Product::with([
                    'priceOffers' => static function ($query): void {
                        // @var \Illuminate\Database\Eloquent\Builder<\App\Models\PriceOffer> $query
                        $query->where('is_available', true)
                            ->orderBy('price', 'asc')
                            ->with('store:id,name')
                        ;
                    },
                    'brand:id,name',
                    'category:id,name',
                ]);

                /** @var Product $product */
                $product = $queryBuilder->where('name', 'like', '%'.$productNameStr.'%')->first();
            }

            if (! $product) {
                return $this->notFound('Product not found');
            }

            if ($product->priceOffers->isEmpty()) {
                return $this->notFound('No offers available for this product');
            }

            /** @var PriceOffer $bestOffer */
            $bestOffer = $product->priceOffers->first();

            return $this->success(
                [
                    'product_id' => $product->id,
                    'price' => $bestOffer->price,
                    'store_id' => $bestOffer->store_id,
                    'store' => $bestOffer->store->name ?? 'Unknown Store',
                    'store_url' => $bestOffer->product_url,
                    'is_available' => (bool) $bestOffer->is_available,
                    'total_offers' => $product->priceOffers->count(),
                    'offers' => $product->priceOffers->map(/**
                     * @return array<scalar|* @method static \App\Models\Brand create(array<string, string|bool|null>
                     *
                     * @psalm-return array{id: int, price: float, store_id: int, store: string, store_url: string|null, is_available: bool}
                     */
                        static function (PriceOffer $offer): array {
                            return [
                                'id' => $offer->id,
                                'price' => $offer->price,
                                'store_id' => $offer->store_id,
                                'store' => $offer->store->name ?? 'Unknown Store',
                                'store_url' => $offer->product_url,
                                'is_available' => (bool) $offer->is_available,
                            ];
                        }
                    )->toArray(),
                ],
                'Best offer retrieved successfully'
            );
        } catch (\Exception $exception) {
            Log::error('PriceSearchController@bestOffer failed: '.$exception->getMessage());

            return $this->serverError('An error occurred while finding the best offer', $exception);
        }
    }

    // Either implement getCountryCode() logic or remove if truly unused
}
