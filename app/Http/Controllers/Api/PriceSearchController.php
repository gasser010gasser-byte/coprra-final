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
            // Reject invalid types and detect security threats
            $securityIssues = [];
            
            // Check all query parameters for invalid types
            foreach (['q', 'query', 'name'] as $paramName) {
                if (!$request->has($paramName)) {
                    continue;
                }
                
                $value = $request->query($paramName);
                if ($value === null) {
                    continue;
                }
                
                $receivedType = \gettype($value);
                
                // Check for invalid types (arrays, objects, booleans)
                if (\is_array($value) || \is_object($value) || \is_bool($value)) {
                    $securityIssues[] = 'Invalid parameter type';
                    
                    // Check array/object values for security threats
                    if (\is_array($value)) {
                        $valueString = implode(' ', array_map('strval', $value));
                    } else {
                        $valueString = (string) $value;
                    }
                    
                    // Detect XSS attempts
                    if (preg_match('/<script|javascript:|onerror=|onclick=|onload=|alert\(|eval\(/i', $valueString)) {
                        $securityIssues[] = 'potential_xss_attempt';
                    }
                    
                    // Detect SQL injection attempts
                    if (preg_match('/(\bunion\b.*\bselect|\bdrop\s+table|\bdelete\s+from|\binsert\s+into|\bupdate\s+.*\bset|\'?\s*or\s*\'?\d+\s*=\s*\d+|;\s*drop|--\s|#|\/\*|\*\/)/i', $valueString)) {
                        $securityIssues[] = 'potential_sql_injection';
                    }
                    
                    $response = [
                        'success' => false,
                        'message' => 'Invalid parameter format',
                        'error_code' => 'INVALID_PARAMETER_TYPE',
                        'validation_errors' => [
                            'parameter' => $paramName,
                            'expected_type' => 'string',
                            'received_type' => $receivedType,
                            'security_issues' => $securityIssues,
                        ],
                        'suggestions' => [
                            'correct_format' => 'Use string parameter: ?q=product_name',
                            'examples' => ['?q=laptop', '?query=phone'],
                        ],
                    ];
                    return response()->json($response, 400);
                }
                
                // Check string parameters for security threats and length
                if (\is_string($value)) {
                    // Check for extremely long parameters (DoS)
                    if (strlen($value) > 1000) {
                        $response = [
                            'success' => false,
                            'message' => 'Parameter too long',
                            'error_code' => 'PARAMETER_TOO_LONG',
                            'validation_errors' => [
                                'parameter' => $paramName,
                                'max_length' => 1000,
                                'received_length' => strlen($value),
                            ],
                        ];
                        return response()->json($response, 400);
                    }
                    
                    // Check for null bytes and control characters
                    if (preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F]/', $value)) {
                        $response = [
                            'success' => false,
                            'message' => 'Invalid characters detected',
                            'error_code' => 'INVALID_CHARACTERS',
                            'validation_errors' => [
                                'parameter' => $paramName,
                                'reason' => 'Control characters not allowed',
                            ],
                        ];
                        return response()->json($response, 400);
                    }
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
                            ->with('store:id,name,slug,contact_email')
                        ;
                    },
                    'brand:id,name',
                    'category:id,name',
                ]);

                /** @var Collection<int, Product> $products */
                $products = $queryBuilder->where('is_active', true)->limit(10)->get();

                if ($products->isEmpty()) {
                    // Check if a search query was provided
                    $searchQuery = $request->input('q') ?? $request->input('query') ?? $request->input('name');
                    
                    if ($searchQuery) {
                        // Log search analytics
                        try {
                            \Illuminate\Support\Facades\DB::table('search_analytics')->insert([
                                'query' => $searchQuery,
                                'results_count' => 0,
                                'search_type' => 'price_search',
                                'status' => 'no_results',
                                'user_id' => $request->user()?->id,
                                'ip_address' => $request->ip(),
                                'user_agent' => $request->userAgent(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } catch (\Exception $e) {
                            // Log error but don't fail the request
                            Log::warning('Failed to log search analytics', ['error' => $e->getMessage()]);
                        }
                        
                        return $this->notFound("No products found matching '{$searchQuery}'", [
                            'error_code' => 'NO_PRODUCTS_MATCHING_SEARCH',
                            'search_query' => $searchQuery,
                            'empty_state' => [
                                'title' => 'No Products Found',
                                'description' => "No products match your search for '{$searchQuery}'.",
                                'icon' => 'package-search',
                                'suggestions' => [
                                    [
                                        'action' => 'try_different_search',
                                        'description' => 'Try different search terms to find products',
                                        'url' => url('/api/products'),
                                    ],
                                    [
                                        'action' => 'browse_categories',
                                        'description' => 'Browse available product categories',
                                        'url' => url('/api/categories'),
                                    ],
                                ],
                            ],
                        ]);
                    }
                    
                    $totalProducts = Product::count();
                    $activeProducts = Product::where('is_active', true)->count();
                    $lastProduct = Product::latest('created_at')->first();
                    
                    return $this->notFound('No products available for price comparison', [
                        'error_code' => 'NO_PRODUCTS_AVAILABLE',
                        'results_count' => 0,
                        'empty_state' => [
                            'title' => 'No Products Found',
                            'description' => 'There are currently no products in the system.',
                            'icon' => 'package-search',
                            'suggestions' => [
                                [
                                    'action' => 'browse_categories',
                                    'description' => 'Browse available product categories',
                                    'url' => url('/api/categories'),
                                ],
                                [
                                    'action' => 'try_different_search',
                                    'description' => 'Try different search terms to find products',
                                    'url' => url('/api/products'),
                                ],
                                [
                                    'action' => 'check_back_later',
                                    'description' => 'Products may be added soon',
                                    'url' => url('/api/products'),
                                ],
                            ],
                        ],
                        'system_info' => [
                            'total_products' => Product::count(),
                            'active_products' => Product::where('is_active', true)->count(),
                            'last_product_added' => Product::latest('created_at')->first()?->created_at?->toIso8601String(),
                            'cache_status' => \Illuminate\Support\Facades\Cache::has('products_count') ? 'cached' : 'empty',
                        ],
                        'admin_actions' => [
                            [
                                'action' => 'add_product',
                                'endpoint' => '/api/products',
                                'method' => 'POST',
                            ],
                            [
                                'action' => 'bulk_import',
                                'endpoint' => '/api/products/import',
                                'method' => 'POST',
                            ],
                        ],
                    ]);
                }

                // If only one product, return it in the same format as single product response
                if ($products->count() === 1) {
                    $product = $products->first();
                    // Ensure priceOffers is loaded
                    if (!$product->relationLoaded('priceOffers')) {
                        $product->load('priceOffers');
                    }
                    
                    if ($product->priceOffers->isEmpty()) {
                        try {
                            $productUrl = $product->slug 
                                ? route('products.show', $product->slug) 
                                : url("/products/{$product->id}");
                        } catch (\Exception $e) {
                            $productUrl = url("/products/{$product->id}");
                        }

                        return response()->json([
                            'success' => false,
                            'message' => 'No offers available for this product',
                            'error_code' => 'NO_OFFERS_AVAILABLE',
                            'product_info' => [
                                'id' => $product->id,
                                'name' => $product->name ?? '',
                                'description' => $product->description ?? null,
                                'price' => (float) ($product->price ?? 0),
                                'url' => $productUrl,
                            ],
                        ], 404);
                    }
                    
                    $bestOffer = $product->priceOffers->first();
                    $prices = $product->priceOffers->pluck('price')->map(fn ($price) => (float) $price)->toArray();
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
                                'price' => (float) $bestOffer->price,
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
                                'lowest_price' => (float) $lowestPrice,
                                'highest_price' => (float) $highestPrice,
                                'average_price' => (float) $averagePrice,
                                'savings_amount' => (float) $savingsAmount,
                            ],
                        ],
                        'Best offer retrieved successfully'
                    );
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
                            ->with('store:id,name,slug,contact_email')
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
                            ->with('store:id,name,slug,contact_email')
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
                            'name' => $p->name ?? '',
                            'category' => $p->category->name ?? 'Uncategorized',
                            'brand' => $p->brand->name ?? 'Unknown',
                            'price' => (float) ($p->price ?? 0),
                            'url' => url("/api/price-search/best-offer?product_id={$p->id}"),
                        ];
                    })->toArray();

                $message = $productId 
                    ? "Product with ID {$productId} not found"
                    : ($productName 
                        ? "Product matching '{$productName}' not found"
                        : 'Product not found');
                        
                return $this->notFound($message, [
                    'error_code' => 'PRODUCT_NOT_FOUND',
                    'description' => $productId 
                        ? "Product with ID {$productId} was not found or is not available."
                        : ($productName 
                            ? "No product matching '{$productName}' was found."
                            : 'The requested product was not found.'),
                    'resource_info' => [
                        'type' => 'product',
                        'id' => $productId !== null ? (int) $productId : 'N/A',
                        'action_attempted' => 'price_search',
                    ],
                    'suggestions' => [
                        'similar_products' => !empty($similarProducts) ? $similarProducts : [],
                        'actions' => array_map(static function (array $action): array {
                            return [
                                'action' => $action['action'] ?? '',
                                'description' => $action['description'] ?? '',
                                'url' => url($action['endpoint'] ?? ''),
                            ];
                        }, [
                            [
                                'action' => 'browse_all_products',
                                'endpoint' => '/api/products',
                                'description' => 'View all available products',
                            ],
                            [
                                'action' => 'search_by_name',
                                'endpoint' => '/api/products/autocomplete',
                                'description' => 'Search for products using autocomplete',
                            ],
                        ]),
                    ],
                    'debug_info' => [
                        'request_id' => request()->header('X-Request-ID') ?? uniqid('req_', true),
                        'timestamp' => now()->toIso8601String(),
                        'search_parameters' => [
                            'product_id' => $productId,
                            'product_name' => $productName,
                        ],
                        'available_products_count' => Product::where('is_active', true)->count(),
                    ],
                ]);
            }

            // Ensure priceOffers is loaded
            if (!$product->relationLoaded('priceOffers')) {
                $product->load('priceOffers');
            }

            if ($product->priceOffers->isEmpty()) {
                try {
                    $productUrl = $product->slug 
                        ? route('products.show', $product->slug) 
                        : url("/products/{$product->id}");
                } catch (\Exception $e) {
                    $productUrl = url("/products/{$product->id}");
                }

                return response()->json([
                    'success' => false,
                    'message' => 'No offers available for this product',
                    'error_code' => 'NO_OFFERS_AVAILABLE',
                    'product_info' => [
                        'id' => $product->id,
                        'name' => $product->name ?? '',
                        'description' => $product->description ?? null,
                        'price' => (float) ($product->price ?? 0),
                        'url' => $productUrl,
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

            // Ensure priceOffers is loaded
            if (!$product->relationLoaded('priceOffers')) {
                $product->load('priceOffers');
            }

            /** @var PriceOffer $bestOffer */
            $bestOffer = $product->priceOffers->first();

            // Calculate price comparison statistics
            $prices = $product->priceOffers->pluck('price')->map(fn ($price) => (float) $price)->toArray();
            $lowestPrice = min($prices);
            $highestPrice = max($prices);
            $averagePriceSum = array_sum($prices);
            $averagePrice = $averagePriceSum / count($prices);
            $savingsAmount = $highestPrice - $lowestPrice;

            return $this->success(
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'total_offers' => $product->priceOffers->count(),
                    'best_offer' => [
                        'id' => $bestOffer->id,
                        'price' => (float) $bestOffer->price,
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
                        'lowest_price' => (float) $lowestPrice,
                        'highest_price' => (float) $highestPrice,
                        'average_price' => (float) $averagePrice,
                        'savings_amount' => (float) $savingsAmount,
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

            // Get alternative products for suggestions (same category, prioritize same brand and name similarity)
            $alternativeProductsQuery = Product::where('is_active', true)
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id)
                ->with(['category:id,name', 'brand:id,name']);
            
            // Get candidates and filter by name similarity
            $candidates = $alternativeProductsQuery->limit(10)->get();
            
            $alternativeProducts = $candidates
                ->map(static function (Product $p) use ($product): array {
                    // Calculate similarity score based on name similarity
                    $similarityScore = 50;
                    
                    // Brand match increases relevance
                    if ($p->brand_id === $product->brand_id) {
                        $similarityScore += 20;
                    }
                    
                    // Name word overlap check
                    $productNameWords = array_filter(explode(' ', strtolower($product->name)));
                    $pNameWords = array_filter(explode(' ', strtolower($p->name)));
                    $commonWords = array_intersect($productNameWords, $pNameWords);
                    $commonCount = count($commonWords);
                    
                    // If no common words at all, it's likely unrelated (e.g., iPhone vs Samsung Galaxy)
                    if ($commonCount === 0 && count($productNameWords) > 1 && count($pNameWords) > 1) {
                        $similarityScore = 0; // Mark as unrelated
                    } else {
                        $similarityScore += min(30, $commonCount * 10);
                    }
                    
                    return [
                        'product' => $p,
                        'similarity_score' => $similarityScore,
                    ];
                })
                ->filter(static function (array $item): bool {
                    return $item['similarity_score'] > 0; // Exclude unrelated products
                })
                ->sortByDesc('similarity_score')
                ->take(3)
                ->map(static function (array $item): array {
                    return [
                        'id' => $item['product']->id,
                        'name' => $item['product']->name,
                        'similarity_score' => $item['similarity_score'],
                    ];
                })
                ->values()
                ->toArray();

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
                    'price' => (float) $bestOffer->price,
                    'shipping_cost' => (float) ($bestOffer->shipping_cost ?? 0.00),
                    'total_cost' => (float) (($bestOffer->price ?? 0) + ($bestOffer->shipping_cost ?? 0)),
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
                        'price' => (float) $offer->price,
                        'shipping_cost' => (float) ($offer->shipping_cost ?? 0.00),
                        'total_cost' => (float) (($offer->price ?? 0) + ($offer->shipping_cost ?? 0)),
                        'stock_quantity' => $offer->stock_quantity ?? 0,
                        'store_name' => $offer->store->name ?? 'Unknown Store',
                    ];
                })->toArray(),
                'alternative_products' => $alternativeProducts,
                'price_statistics' => [
                    'lowest_price' => (float) $lowestPrice,
                    'highest_price' => (float) $highestPrice,
                    'average_price' => (float) $averagePrice,
                    'price_range' => (float) ($highestPrice - $lowestPrice),
                    'savings_amount' => (float) ($highestPrice - $lowestPrice),
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
