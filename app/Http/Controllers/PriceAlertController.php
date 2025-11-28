<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePriceAlertRequest;
use App\Models\PriceAlert;
use App\Models\Product;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceAlertController extends Controller
{
    private const UNAUTHORIZED_MESSAGE = 'Unauthorized action.';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Guard $auth): View
    {
        $priceAlerts = PriceAlert::where('user_id', $auth->id())
            ->with(['product.category', 'product.brand'])
            ->latest()
            ->paginate(15);

        return view('price-alerts.index', compact('priceAlerts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        $product = null;
        if ($request->has('product_id')) {
            $product = Product::findOrFail($request->input('product_id'));
        }

        return view('price-alerts.create', ['product' => $product]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Guard $auth): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'target_price' => 'required|numeric|min:0.01',
            'repeat_alert' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Check if user already has an alert for this product
        $existingAlert = PriceAlert::where('user_id', $auth->id())
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existingAlert) {
            return redirect()->route('price-alerts.index')
                ->with('error', 'You already have a price alert for this product.');
        }

        PriceAlert::create([
            'user_id' => $auth->id(),
            'product_id' => $validated['product_id'],
            'target_price' => $validated['target_price'],
            'repeat_alert' => $request->boolean('repeat_alert', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('price-alerts.index')
            ->with('success', 'Price alert created successfully!');
    }

    /**
     * Display the specified price alert.
     */
    public function show(PriceAlert $priceAlert, Guard $auth): View
    {
        if ($priceAlert->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        // Eager load relationships to prevent N+1 queries
        $priceAlert->load(['product.priceOffers']);

        return view('price-alerts.show', compact('priceAlert'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PriceAlert $priceAlert, Guard $auth): View
    {
        if ($priceAlert->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        return view('price-alerts.edit', compact('priceAlert'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePriceAlertRequest $request, PriceAlert $priceAlert, Guard $auth): RedirectResponse
    {
        if ($priceAlert->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        $priceAlert->update([
            'target_price' => $request->input('target_price'),
            'repeat_alert' => $request->boolean('repeat_alert'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('price-alerts.index')
            ->with('success', 'Price alert updated successfully!')
        ;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PriceAlert $priceAlert, Guard $auth): RedirectResponse
    {
        if ($priceAlert->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        $priceAlert->delete();

        return redirect()->route('price-alerts.index')
            ->with('success', 'Price alert deleted successfully!');
    }

    /**
     * Toggle the active status of the price alert.
     */
    public function toggle(PriceAlert $priceAlert, Guard $auth): RedirectResponse
    {
        if ($priceAlert->user_id !== $auth->id()) {
            abort(403, self::UNAUTHORIZED_MESSAGE);
        }

        $priceAlert->update([
            'is_active' => ! $priceAlert->is_active,
        ]);

        return redirect()->route('price-alerts.index')
            ->with('success', 'Price alert ' . ($priceAlert->is_active ? 'activated' : 'deactivated') . ' successfully!');
    }
}
