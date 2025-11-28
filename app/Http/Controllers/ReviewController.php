<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReviewRequest;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    // تم إضافة هذا الثابت لتقليل التكرار
    private const UNAUTHORIZED_MESSAGE = 'Unauthorized action.';

    /**
     * Show the form for creating a new resource.
     */
    public function create(Product $product, Guard $auth): RedirectResponse|View
    {
        // التحقق مما إذا كان المستخدم قد قام بمراجعة هذا المنتج بالفعل
        $existingReview = $product->reviews()->where('user_id', $auth->id())->exists();

        if ($existingReview) {
            return redirect()->route('products.show', $product->slug)
                ->with('error', 'You have already reviewed this product.')
            ;
        }

        /** @var view-string $view */
        $view = 'reviews.create';

        return view($view, ['product' => $product]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\Illuminate\Http\Request $request, Guard $auth): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        // Check if user already reviewed this product
        $existingReview = Review::where('user_id', $auth->id())
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existingReview) {
            $product = Product::findOrFail($validated['product_id']);

            return redirect()->route('products.show', $product->slug)
                ->with('error', 'You have already reviewed this product.');
        }

        Review::create([
            'user_id' => $auth->id(),
            'product_id' => $validated['product_id'],
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'rating' => $validated['rating'],
            'is_approved' => true, // Auto-approve for now
        ]);

        $product = Product::findOrFail($validated['product_id']);

        return redirect()->route('products.show', $product->slug)
            ->with('success', 'Review submitted successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Review $review, Guard $auth): View|RedirectResponse
    {
        if ($review->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        return view('reviews.edit', ['review' => $review]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReviewRequest $request, Review $review, Guard $auth): RedirectResponse
    {
        // التحقق من أن المستخدم هو صاحب المراجعة
        if ($review->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE); // تم استخدام الثابت هنا
        }

        $review->update($request->validated());

        $product = \App\Models\Product::findOrFail($review->product_id);

        return redirect()->route('products.show', $product->slug)
            ->with('success', 'Review updated successfully!')
        ;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review, Guard $auth): RedirectResponse
    {
        if ($review->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        $productId = $review->product_id;
        $product = \App\Models\Product::findOrFail($productId);
        $review->delete();

        return redirect()->route('products.show', $product->slug)
            ->with('success', 'Review deleted successfully!');
    }
}
