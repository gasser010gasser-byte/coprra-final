<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCartRequest;
use App\Models\Product;
use Darryldecode\Cart\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * CartController - Shopping List / Price Tracking Tool
 *
 * This controller manages a "shopping list" or "price tracking" cart for affiliate products.
 * Users can add products to track prices, but purchases are made on external affiliate stores.
 *
 * @mixin \Darryldecode\Cart\Cart
 * @mixin \Darryldecode\Cart\CartCondition
 */
class CartController extends Controller
{
    /**
     * Display the shopping cart (shopping list) view.
     */
    public function index(): View
    {
        /** @var Cart $cartInstance */
        $cartInstance = app('cart');

        return view('cart.index', [
            'cartItems' => $cartInstance->getContent(),
            'total' => $cartInstance->getTotal(),
        ]);
    }

    /**
     * Add a product to the cart from request data.
     */
    public function addFromRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $quantity = $validated['quantity'] ?? 1;

        /** @var Cart $cartInstance */
        $cartInstance = app('cart');
        $cartInstance->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price ?? 0,
            'quantity' => $quantity,
            'attributes' => [
                'slug' => $product->slug,
                'image' => $product->image_url ?? null,
            ],
        ]);

        Session::flash('success', 'Product added to your shopping list!');

        return redirect()->back();
    }

    /**
     * Add a product to the cart by product model.
     */
    public function add(Product $product, Request $request): RedirectResponse
    {
        $quantity = $request->input('quantity', 1);

        /** @var Cart $cartInstance */
        $cartInstance = app('cart');
        $cartInstance->add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price ?? 0,
            'quantity' => max(1, (int) $quantity),
            'attributes' => [
                'slug' => $product->slug,
                'image' => $product->image_url ?? null,
            ],
        ]);

        Session::flash('success', 'Product added to your shopping list!');

        return redirect()->back();
    }

    /**
     * Update cart item quantity.
     */
    public function update(UpdateCartRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var Cart $cartInstance */
        $cartInstance = app('cart');

        // Stock check logic will be implemented when inventory management is added to the product model.

        $cartInstance->update($validated['id'], [
            'quantity' => [
                'relative' => false,
                'value' => $validated['quantity'],
            ],
        ]);

        Session::flash('success', 'Shopping list updated!');

        return redirect()->route('cart.index');
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(string $itemId): RedirectResponse
    {
        /** @var Cart $cartInstance */
        $cartInstance = app('cart');
        $cartInstance->remove($itemId);

        Session::flash('success', 'Item removed from your shopping list!');

        return redirect()->back();
    }

    /**
     * Clear all items from the cart.
     */
    public function clear(): RedirectResponse
    {
        /** @var Cart $cartInstance */
        $cartInstance = app('cart');
        $cartInstance->clear();

        Session::flash('success', 'Shopping list cleared!');

        return redirect()->back();
    }
}
