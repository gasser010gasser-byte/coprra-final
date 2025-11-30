<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the application home page.
     */
    public function index(): Response|View
    {
        try {
            // Cache featured products for 60 minutes
            $featuredProducts = Cache::remember('home_featured_products', 3600, function () {
                // Get latest active products (is_featured column may not exist)
                $products = Product::query()
                    ->where('is_active', true)
                    ->with(['category:id,name,slug', 'brand:id,name,slug'])
                    ->latest()
                    ->limit(8)
                    ->get();

                return $products;
            });

            // Cache top categories for 60 minutes
            $categories = Cache::remember('home_top_categories', 3600, function () {
                try {
                    return Category::query()
                        ->where('is_active', true)
                        ->withCount('products')
                        ->having('products_count', '>', 0)
                        ->orderBy('products_count', 'desc')
                        ->limit(6)
                        ->get();
                } catch (\Exception $e) {
                    // Fallback if withCount fails
                    return Category::query()
                        ->where('is_active', true)
                        ->limit(6)
                        ->get();
                }
            });

            // Cache top brands for 60 minutes
            $brands = Cache::remember('home_top_brands', 3600, function () {
                try {
                    return Brand::query()
                        ->where('is_active', true)
                        ->withCount('products')
                        ->having('products_count', '>', 0)
                        ->orderBy('products_count', 'desc')
                        ->limit(6)
                        ->get();
                } catch (\Exception $e) {
                    // Fallback if withCount fails
                    return Brand::query()
                        ->where('is_active', true)
                        ->limit(6)
                        ->get();
                }
            });

            return view('home', [
                'featuredProducts' => $featuredProducts,
                'categories' => $categories,
                'brands' => $brands,
            ]);
        } catch (\Exception $e) {
            // Return empty data if there's an error
            return view('home', [
                'featuredProducts' => collect(),
                'categories' => collect(),
                'brands' => collect(),
            ]);
        }
    }
}
