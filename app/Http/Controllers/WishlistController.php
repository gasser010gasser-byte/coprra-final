<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    /**
     * Display the authenticated user's wishlist.
     */
    public function index(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 12);
        $wishlistItems = Wishlist::with(['product', 'product.category', 'product.brand'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return view('wishlist.index', compact('wishlistItems'));
    }

    /**
     * Add a product to the user's wishlist.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = (int) auth()->id();

        $exists = Wishlist::where('user_id', $userId)
            ->where('product_id', (int) $validated['product_id'])
            ->exists();

        if ($exists) {
            return back()->with('info', 'Product is already in your wishlist.');
        }

        Wishlist::create([
            'user_id' => $userId,
            'product_id' => (int) $validated['product_id'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Product added to wishlist.');
    }

    /**
     * Remove a product from the user's wishlist.
     */
    public function remove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        Wishlist::where('user_id', auth()->id())
            ->where('product_id', (int) $validated['product_id'])
            ->delete();

        return back()->with('success', 'Product removed from wishlist.');
    }

    /**
     * Clear the authenticated user's wishlist.
     */
    public function clear(): RedirectResponse
    {
        Wishlist::where('user_id', auth()->id())->delete();

        return back()->with('success', 'Wishlist cleared.');
    }

    /**
     * Toggle wishlist state for a product.
     */
    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $userId = (int) auth()->id();
        $productId = (int) $validated['product_id'];

        $item = Wishlist::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($item) {
            $item->delete();

            return back()->with('success', 'Removed from wishlist.');
        }

        Wishlist::create([
            'user_id' => $userId,
            'product_id' => $productId,
        ]);

        return back()->with('success', 'Added to wishlist.');
    }

    /**
     * Destroy a wishlist entry by id (resource route).
     */
    public function destroy(Wishlist $wishlist): RedirectResponse
    {
        if ($wishlist->user_id !== auth()->id()) {
            abort(403);
        }

        $wishlist->delete();

        return to_route('wishlist.index')->with('success', 'Wishlist item removed.');
    }
}
