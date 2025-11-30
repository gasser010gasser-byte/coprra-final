<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display the authenticated user's wishlist products.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if (!$user) {
                // Return empty wishlist for unauthenticated users
                return response()->json([
                    'success' => true,
                    'count' => 0,
                    'data' => [],
                    'message' => __('Wishlist loaded successfully.'),
                ], 200);
            }

            $items = $user->wishlist()
                ->with([
                    'brand:id,name,slug',
                    'category:id,name,slug',
                ])
                ->orderByPivot('created_at', 'desc')
                ->get()
                ->map(static function (Product $product): array {
                    $pivot = $product->pivot;

                    return [
                        'id' => $product->id,
                        'name' => $product->name ?? '',
                        'slug' => $product->slug ?? '',
                        'price' => $product->price ?? 0,
                        'image' => $product->image ?? $product->image_url ?? null,
                        'brand' => $product->brand ? $product->brand->name : null,
                        'category' => $product->category ? $product->category->name : null,
                        'pivot' => [
                            'id' => $pivot->id ?? null,
                            'created_at' => $pivot->created_at ?? null,
                            'updated_at' => $pivot->updated_at ?? null,
                            'notes' => $pivot->notes ?? null,
                        ],
                    ];
                });

            return response()->json([
                'success' => true,
                'count' => $items->count(),
                'data' => $items,
                'message' => __('Wishlist loaded successfully.'),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WishlistController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading the wishlist.',
            ], 500);
        }
    }

    /**
     * Store the specified product in the user's wishlist.
     */
    public function store(Request $request, ?Product $product = null): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $productModel = $product ?? Product::query()->with(['category', 'brand'])->findOrFail((int) $request->input('product_id'));

            $alreadyExists = $user->wishlist()->where('products.id', $productModel->id)->exists();

            $user->wishlist()->syncWithoutDetaching([$productModel->id]);

            $count = $user->wishlist()->count();
            $item = [
                'id' => $productModel->id,
                'product_id' => $productModel->id,
                'name' => $productModel->name ?? '',
                'slug' => $productModel->slug ?? '',
                'price' => $productModel->price ?? 0,
                'image' => $productModel->image ?? $productModel->image_url ?? null,
                'product' => [
                    'id' => $productModel->id,
                    'name' => $productModel->name ?? '',
                    'price' => $productModel->price ?? 0,
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => $alreadyExists
                    ? __('Product is already in your wishlist.')
                    : __('Product added to your wishlist.'),
                'count' => $count,
                'data' => $item,
            ], $alreadyExists ? 200 : 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WishlistController@store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding to wishlist.',
            ], 500);
        }
    }

    /**
     * Remove the specified product from the user's wishlist.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $detached = (bool) $user->wishlist()->detach($product->id);

            return response()->json([
                'success' => true,
                'message' => $detached
                    ? __('Product removed from your wishlist.')
                    : __('Product was not in your wishlist.'),
                'count' => $user->wishlist()->count(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WishlistController@destroy failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing from wishlist.',
            ], 500);
        }
    }
}
