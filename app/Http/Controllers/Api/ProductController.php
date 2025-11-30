<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductController extends BaseApiController
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/products",
     *     summary="List products",
     *     description="Get a list of products with optional search",
     *     operationId="listProducts",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Search by product name",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of products",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Product")),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $query = Product::query()->where('is_active', true);

            // Search by name if provided
            if ($request->has('name')) {
                $name = $request->input('name');
                if (\is_string($name) && '' !== $name) {
                    $query->where('name', 'like', "%{$name}%");
                }
            }

            $products = $query->with(['category:id,name', 'brand:id,name'])
                ->limit(20)
                ->get();

            return $this->success(
                $products->map(fn(Product $product) => $this->formatProductResponse($product))->all(),
                'Products retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverError('An error occurred while retrieving products', $e);
        }
    }

    /**
     * @OA\Get(
     *     path="/products/{id}",
     *     summary="Get product by ID",
     *     description="Get a single product by its ID",
     *     operationId="getProduct",
     *     tags={"Products"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $product = Product::with(['category:id,name', 'brand:id,name', 'stores:id,name'])
                ->where('is_active', true)
                ->findOrFail($id);

            return $this->success(
                $this->formatProductResponse($product),
                'Product retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Product not found', [
                'error_code' => 'PRODUCT_NOT_FOUND',
                'resource' => [
                    'type' => 'product',
                    'id' => $id,
                    'action_attempted' => 'get',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverError('An error occurred while retrieving the product', $e);
        }
    }

    /**
     * @OA\Post(
     *     path="/products",
     *     summary="Create product",
     *     description="Create a new product (Admin only)",
     *     operationId="createProduct",
     *     tags={"Products"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/ProductCreateRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'category_id' => 'nullable|exists:categories,id',
                'brand_id' => 'nullable|exists:brands,id',
                'is_active' => 'boolean',
            ]);

            $validated['slug'] = Str::slug($validated['name']);

            $product = Product::create($validated);

            return $this->created(
                $this->formatProductResponse($product->load(['category:id,name', 'brand:id,name'])),
                'Product created successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            return $this->serverError('An error occurred while creating the product', $e);
        }
    }

    /**
     * @OA\Put(
     *     path="/products/{id}",
     *     summary="Update product",
     *     description="Update an existing product (Admin only)",
     *     operationId="updateProduct",
     *     tags={"Products"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/ProductUpdateRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Product")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            // Capture old values for audit trail
            $oldValues = $product->getAttributes();
            $oldValues = array_intersect_key($oldValues, array_flip([
                'name',
                'description',
                'price',
                'sku',
                'slug',
                'is_active',
                'category_id',
                'brand_id',
                'meta_title',
                'meta_description',
            ]));

            $validated = $request->validated();

            try {
                $slugData = $this->updateProductSlug($validated, $id);
                $validated['slug'] = $slugData['slug'];
            } catch (\Exception $e) {
                // If slug generation fails, use existing slug or generate basic one
                \Illuminate\Support\Facades\Log::warning('Failed to generate product slug', [
                    'product_id' => $id,
                    'error' => $e->getMessage(),
                ]);
                $validated['slug'] = $validated['slug'] ?? $product->slug ?? 'product-' . $id;
                $slugData = [
                    'slug' => $validated['slug'],
                    'original_slug' => $product->slug ?? '',
                    'conflict_resolved' => false,
                    'final_slug' => $validated['slug'],
                ];
            }

            $product->update($validated);

            // Create audit trail
            $newValues = array_intersect_key($product->getAttributes(), array_flip([
                'name',
                'description',
                'price',
                'sku',
                'slug',
                'is_active',
                'category_id',
                'brand_id',
                'meta_title',
                'meta_description',
            ]));

            // Log audit trail, but don't fail the request if audit logging fails
            try {
                if ($this->auditService && method_exists($this->auditService, 'log')) {
                    $this->auditService->log('product_updated', $product, $oldValues, $newValues);
                }
            } catch (\Exception $auditException) {
                // Log the audit failure but don't break the request
                \Illuminate\Support\Facades\Log::warning('Failed to log product update audit', [
                    'product_id' => $product->id,
                    'error' => $auditException->getMessage(),
                ]);
            }

            // Reload product with relationships for response
            try {
                $product->load(['category:id,name', 'brand:id,name']);
            } catch (\Exception $e) {
                // If loading relationships fails, continue without them
                \Illuminate\Support\Facades\Log::warning('Failed to load product relationships', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $responseData = $this->formatProductResponse($product);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to format product response', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);

                return $this->serverError('Failed to format product response', $e);
            }

            // Add updated_by information
            $user = Auth::user();
            if ($user) {
                try {
                    $responseData['updated_by'] = [
                        'id' => $user->id,
                        'name' => $user->name ?? '',
                        'email' => $user->email ?? '',
                    ];
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to add updated_by info', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Add slug generation info to data
            try {
                $responseData['slug_generation'] = [
                    'original_slug' => $slugData['original_slug'] ?? '',
                    'final_slug' => $slugData['final_slug'] ?? '',
                    'conflicts_resolved' => ($slugData['conflict_resolved'] ?? false) ? 1 : 0,
                    'generation_method' => ($slugData['conflict_resolved'] ?? false) ? 'conflict_resolution' : 'standard',
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to add slug generation info', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Build response with audit trail
            try {
                $response = response()->json([
                    'success' => true,
                    'message' => 'Product updated successfully',
                    'data' => $responseData,
                    'audit' => [
                        'action' => 'product_updated',
                        'user_id' => $user ? $user->id : null,
                        'changes' => [
                            'old_values' => $oldValues,
                            'new_values' => $newValues,
                        ],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ], 200);

                return $response;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to build response', [
                    'error' => $e->getMessage(),
                ]);

                return $this->serverError('Failed to build response', $e);
            }
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Product not found', [
                'error_code' => 'PRODUCT_NOT_FOUND',
                'resource' => [
                    'type' => 'product',
                    'id' => $id,
                    'action_attempted' => 'update',
                ],
                'suggestions' => [
                    'check_id' => 'Verify the product ID is correct',
                    'verify_permissions' => 'Ensure you have permission to update products',
                    'alternative_actions' => ['List all products', 'Search for products'],
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            return $this->serverError('An error occurred while updating the product', $e);
        }
    }

    /**
     * Delete a product.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return $this->success(null, 'Product deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Product not found');
        } catch (\Exception $e) {
            return $this->serverError('An error occurred while deleting the product', $e);
        }
    }

    /**
     * Update product slug with conflict resolution.
     *
     * @return array{slug: string, original_slug: string, conflict_resolved: bool, final_slug: string}
     */
    private function updateProductSlug(array $validated, int $id): array
    {
        $product = Product::find($id);
        if (!$product) {
            // Product doesn't exist, return default slug data
            return [
                'slug' => $validated['slug'] ?? 'product-' . $id,
                'original_slug' => '',
                'conflict_resolved' => false,
                'final_slug' => $validated['slug'] ?? 'product-' . $id,
            ];
        }
        $originalSlug = $product->slug ?? '';

        if (!isset($validated['name'])) {
            // If name is not being updated, keep existing slug or generate from current product
            if (isset($validated['slug']) && $validated['slug'] !== '') {
                return [
                    'slug' => $validated['slug'],
                    'original_slug' => $originalSlug,
                    'conflict_resolved' => false,
                    'final_slug' => $validated['slug'],
                ];
            }

            // Fallback to existing product slug
            if ($product && $product->slug) {
                return [
                    'slug' => $product->slug,
                    'original_slug' => $originalSlug,
                    'conflict_resolved' => false,
                    'final_slug' => $product->slug,
                ];
            }

            $fallbackSlug = 'product-' . $id;

            return [
                'slug' => $fallbackSlug,
                'original_slug' => $originalSlug,
                'conflict_resolved' => false,
                'final_slug' => $fallbackSlug,
            ];
        }

        $nameValue = $validated['name'];
        $nameString = \is_string($nameValue) ? $nameValue : '';
        $baseSlug = Str::slug($nameString);

        // Ensure slug is not empty
        if ($baseSlug === '') {
            $baseSlug = $product && $product->slug ? $product->slug : 'product-' . $id;
        }

        $slug = $baseSlug;
        $counter = 1;
        $conflictResolved = false;

        while (Product::where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            ++$counter;
            $conflictResolved = true;
        }

        return [
            'slug' => $slug,
            'original_slug' => $originalSlug,
            'conflict_resolved' => $conflictResolved,
            'final_slug' => $slug,
        ];
    }

    /**
     * Format product data for response.
     *
     * @return array<array<int|string>|bool|int|mixed|string|* @method static \App\Models\Brand create(array<string, string|bool|null>
     *
     * @psalm-return array{id: int, name: string, slug: string, description: string, price: string, created_at: mixed|null, updated_at: mixed|null, image_url: string|null, is_active: bool, category_id: int, brand_id: int, category: array{id: int, name: string}|null, brand: array{id: int, name: string}|null, stores: array<never, never>|mixed}
     */
    private function formatProductResponse(Product $product): array
    {
        $response = [
            'id' => $product->id,
            'name' => $product->name ? htmlspecialchars((string) $product->name, \ENT_QUOTES, 'UTF-8') : '',
            'slug' => $product->slug ?? '',
            'description' => $product->description ? htmlspecialchars((string) $product->description, \ENT_QUOTES, 'UTF-8') : '',
            'price' => $product->price ?? 0,
            'sku' => $product->sku ?? '',
            'meta_title' => $product->meta_title ?? '',
            'meta_description' => $product->meta_description ?? '',
            'created_at' => $product->created_at ? $product->created_at->toIso8601String() : null,
            'updated_at' => $product->updated_at ? $product->updated_at->toIso8601String() : null,
            'image_url' => $product->image ? asset('storage/' . $product->image) : null,
            'is_active' => $product->is_active ?? false,
            'category_id' => $product->category_id ?? null,
            'brand_id' => $product->brand_id ?? null,
            'category' => null,
            'brand' => null,
            'stores' => [],
        ];

        if ($product->relationLoaded('category') && $product->category) {
            $response['category'] = [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ];
        }

        if ($product->relationLoaded('brand') && $product->brand) {
            $response['brand'] = [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
            ];
        }

        if ($product->relationLoaded('stores') && $product->stores) {
            $response['stores'] = $product->stores->map(static function ($store): array {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                ];
            })->all();
        }

        return $response;
    }

    /**
     * Autocomplete endpoint for live search.
     *
     * @return JsonResponse
     */
    public function autocomplete(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|min:2',
        ]);

        $query = $validated['q'] ?? '';

        if (empty($query)) {
            return $this->success([], 'No results');
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'slug']);

        $results = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'url' => route('products.show', $product->slug),
            ];
        })->all();

        return $this->success($results, 'Autocomplete results');
    }
}
