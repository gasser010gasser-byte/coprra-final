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
                    $response = [
                        'success' => false,
                        'message' => 'Invalid parameter format',
                        'error_code' => 'INVALID_PARAMETER_TYPE',
                        'validation_errors' => [
                            'parameter' => 'q',
                            'expected_type' => 'string',
                            'received_type' => \gettype($value),
                            'security_issues' => ['Invalid parameter type'],
                        ],
                        'suggestions' => [
                            'correct_format' => 'Use string parameter: ?q=product_name',
                            'examples' => ['?q=laptop', '?query=phone'],
                        ],
                    ];
                    return response()->json($response, 400);
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
                    return $this->notFound('No products available for price comparison', [
                        'error_code' => 'NO_PRODUCTS_AVAILABLE',
                        'empty_state' => [
                            'title' => 'No Products Found',
                            'description' => 'There are currently no products in the system.',
                            'icon' => 'package-search',
                            'suggestions' => [
                                [
                                    'action' => 'Check back later',
                                    'description' => 'Products may be added soon',
                                    'url' => route('products.index'),
                                ],
                            ],
                        ],
                        'system_info' => [
                            'total_products' => 0,
                            'active_products' => 0,
                            'last_product_added' => null,
                            'cache_status' => 'empty',
                        ],
                        'admin_actions' => [
                            [
                                'action' => 'Add Product',
                                'endpoint' => '/api/products',
                                'method' => 'POST',
                            ],
                        ],
                    ]);
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
                                'store' => $bestOffer->store->name ?? 'Unknown Store',
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
                // Get similar products for suggestions
                $similarProducts = Product::where('is_active', true)
                    ->where('id', '!=', $productId ?? 0)
                    ->with(['category:id,name', 'brand:id,name'])
                    ->limit(5)
                    ->get()
                    ->map(static function (Product $p): array {
                        return [
                            'id' => $p->id,
                            'name' => $p->name,
                            'category' => $p->category->name ?? 'Uncategorized',
                            'brand' => $p->brand->name ?? 'Unknown',
                            'price' => (float) $p->price,
                            'url' => route('products.show', $p->slug),
                        ];
                    })->toArray();

                return $this->notFound('Product not found', [
                    'error_code' => 'PRODUCT_NOT_FOUND',
                    'resource_info' => [
                        'type' => 'product',
                        'id' => $productId ?? 'N/A',
                        'action_attempted' => 'price_search',
                    ],
                    'suggestions' => [
                        'similar_products' => $similarProducts,
                        'actions' => [
                            [
                                'action' => 'Browse all products',
                                'endpoint' => '/api/products',
                                'method' => 'GET',
                            ],
                            [
                                'action' => 'Search products',
                                'endpoint' => '/api/products/autocomplete',
                                'method' => 'GET',
                            ],
                        ],
                    ],
                ]);
            }

            if ($product->priceOffers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No offers available for this product',
                    'error_code' => 'NO_OFFERS_AVAILABLE',
                    'product_info' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'price' => $product->price,
                        'url' => route('products.show', $product->slug ?? $product->id),
                    ],
                    'empty_state' => [
                        'title' => 'No Offers Available',
                        'description' => 'This product currently has no available offers.',
                        'suggestions' => [
                            [
                                'action' => 'Check back later',
                                'description' => 'Offers may be added soon',
                            ],
                        ],
                    ],
                ], 404);
            }

            /** @var PriceOffer $bestOffer */
            $bestOffer = $product->priceOffers->first();

            // Calculate price comparison statistics
            $prices = $product->priceOffers->pluck('price')->toArray();
            $lowestPrice = min($prices);
            $highestPrice = max($prices);
            $averagePrice = array_sum($prices) / count($prices);
            $savingsAmount = $highestPrice - $lowestPrice;

            return $this->success(
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'total_offers' => $product->priceOffers->count(),
                    'best_offer' => [
                        'id' => $bestOffer->id,
                        'price' => $bestOffer->price,
                        'store_name' => $bestOffer->store->name ?? 'Unknown Store',
                        'expires_at' => $bestOffer->expires_at ? $bestOffer->expires_at->toIso8601String() : null,
                        'stock_quantity' => $bestOffer->stock_quantity ?? 0,
                        'is_available' => (bool) $bestOffer->is_available,
                        'store' => [
                            'id' => $bestOffer->store->id ?? null,
                            'name' => $bestOffer->store->name ?? 'Unknown Store',
                            'slug' => $bestOffer->store->slug ?? null,
                            'contact_email' => $bestOffer->store->contact_email ?? null,
                        ],
                    ],
                    'price_comparison' => [
                        'lowest_price' => $lowestPrice,
                        'highest_price' => $highestPrice,
                        'average_price' => round($averagePrice, 2),
                        'savings_amount' => $savingsAmount,
                    ],
                ],
                'Best offer retrieved successfully'
            );
        } catch (\Exception $exception) {
            Log::error('PriceSearchController@bestOffer failed: '.$exception->getMessage());

            return $this->serverError('An error occurred while finding the best offer', $exception);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $productId = $request->query('product_id') ?? $request->input('product_id');
            $productName = $request->query('product_name') ?? $request->input('product_name');

            if (!$productId && !$productName) {
                return $this->error('Product ID or name is required', [
                    'validation_errors' => [
                        'parameter' => 'product_id or product_name',
                        'expected_type' => 'string or integer',
                    ],
                ], 400);
            }

            $product = null;
            if ($productId) {
                $product = Product::with([
                    'priceOffers' => static function ($query): void {
                        $query->where('is_available', true)
                            ->orderBy('price', 'asc')
                            ->with('store:id,name,slug');
                    },
                    'category:id,name',
                    'brand:id,name',
                ])->where('is_active', true)->find($productId);
            } elseif ($productName) {
                $product = Product::with([
                    'priceOffers' => static function ($query): void {
                        $query->where('is_available', true)
                            ->orderBy('price', 'asc')
                            ->with('store:id,name,slug');
                    },
                    'category:id,name',
                    'brand:id,name',
                ])->where('is_active', true)
                  ->where('name', 'like', '%'.$productName.'%')
                  ->first();
            }

            if (!$product) {
                return $this->notFound('Product not found', [
                    'error_code' => 'PRODUCT_NOT_FOUND',
                    'suggestions' => [
                        'Try searching with a different name',
                        'Check if the product ID is correct',
                    ],
                ]);
            }

            if ($product->priceOffers->isEmpty()) {
                return $this->notFound('No offers available for this product', [
                    'url' => route('products.show', $product->slug),
                    'empty_state' => [
                        'title' => 'No Offers Available',
                        'description' => 'This product currently has no available offers.',
                    ],
                ]);
            }

            $bestOffer = $product->priceOffers->first();
            $prices = $product->priceOffers->pluck('price')->toArray();
            $lowestPrice = min($prices);
            $highestPrice = max($prices);
            $averagePrice = array_sum($prices) / count($prices);

            // Determine match type and confidence score for fuzzy matching
            $searchQuery = $productName ?? '';
            $matchType = 'exact';
            $confidenceScore = 100;
            if ($productName && !$productId) {
                $productNameLower = strtolower($productName);
                $productNameLowerStripped = strtolower($product->name);
                if ($productNameLower === $productNameLowerStripped) {
                    $matchType = 'exact';
                    $confidenceScore = 100;
                } elseif (str_contains($productNameLowerStripped, $productNameLower)) {
                    $matchType = 'partial';
                    $confidenceScore = 85;
                } else {
                    $matchType = 'fuzzy';
                    $confidenceScore = 70;
                }
            }

            // Get alternative products for suggestions
            $alternativeProducts = Product::where('is_active', true)
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->with(['category:id,name', 'brand:id,name'])
                ->limit(3)
                ->get()
                ->map(static function (Product $p): array {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'similarity_score' => 75, // Placeholder similarity score
                    ];
                })->toArray();

            return $this->success([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku ?? null,
                'search_query' => $searchQuery,
                'match_type' => $matchType,
                'confidence_score' => $confidenceScore,
                'total_offers' => $product->priceOffers->count(),
                'best_offer' => [
                    'id' => $bestOffer->id,
                    'price' => $bestOffer->price,
                    'shipping_cost' => $bestOffer->shipping_cost ?? 0.00,
                    'total_cost' => ($bestOffer->price ?? 0) + ($bestOffer->shipping_cost ?? 0),
                    'stock_quantity' => $bestOffer->stock_quantity ?? 0,
                    'delivery_time' => $bestOffer->delivery_time ?? null,
                    'is_available' => (bool) $bestOffer->is_available,
                    'store' => [
                        'id' => $bestOffer->store->id ?? null,
                        'name' => $bestOffer->store->name ?? 'Unknown Store',
                        'slug' => $bestOffer->store->slug ?? null,
                    ],
                ],
                'all_offers' => $product->priceOffers->map(static function (PriceOffer $offer): array {
                    return [
                        'id' => $offer->id,
                        'price' => $offer->price,
                        'shipping_cost' => $offer->shipping_cost ?? 0.00,
                        'total_cost' => ($offer->price ?? 0) + ($offer->shipping_cost ?? 0),
                        'stock_quantity' => $offer->stock_quantity ?? 0,
                        'store_name' => $offer->store->name ?? 'Unknown Store',
                    ];
                })->toArray(),
                'alternative_products' => $alternativeProducts,
                'price_statistics' => [
                    'lowest_price' => $lowestPrice,
                    'highest_price' => $highestPrice,
                    'average_price' => round($averagePrice, 2),
                    'price_range' => $highestPrice - $lowestPrice,
                    'savings_amount' => $highestPrice - $lowestPrice,
                ],
            ], 'Price search completed successfully');
        } catch (\Exception $exception) {
            Log::error('PriceSearchController@search failed: '.$exception->getMessage());

            return $this->serverError('An error occurred while searching for prices', $exception);
        }
    }

    public function supportedStores(Request $request): JsonResponse
    {
        try {
            $stores = \App\Models\Store::where('is_active', true)
                ->get(['id', 'name', 'slug', 'logo_url']);

            return $this->success($stores->toArray(), 'Supported stores retrieved successfully');
        } catch (\Exception $exception) {
            Log::error('PriceSearchController@supportedStores failed: '.$exception->getMessage());

            return $this->serverError('An error occurred while retrieving supported stores', $exception);
        }
    }
}
