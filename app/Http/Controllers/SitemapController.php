<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index(Request $request): Response
    {
        $baseUrl = config('app.url');

        $sitemap = Sitemap::create()
            ->add(Url::create(url('/'))->setLastModificationDate(Carbon::now()))
            ->add(Url::create(route('products.index')))
            ->add(Url::create(route('categories.index')))
            ->add(Url::create(route('brands.index')))
            ->add(Url::create(url('/compare')))
            ->add(Url::create(url('/account/wishlist')))
            ->add(Url::create(route('login')));

        // Dynamic categories
        try {
            $categories = Category::query()->select(['slug', 'updated_at'])->get();
            foreach ($categories as $category) {
                $sitemap->add(
                    Url::create(route('categories.show', $category->slug))
                        ->setLastModificationDate($category->updated_at ?? Carbon::now())
                );
            }
        } catch (\Throwable $e) {
            // ignore DB errors in sitemap generation
        }

        // Dynamic brands
        try {
            $brands = Brand::query()->select(['slug', 'updated_at'])->get();
            foreach ($brands as $brand) {
                // brand show route may use slug
                $sitemap->add(
                    Url::create(route('brands.show', $brand->slug))
                        ->setLastModificationDate($brand->updated_at ?? Carbon::now())
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Dynamic products
        try {
            $products = Product::query()->select(['slug', 'updated_at'])->get();
            foreach ($products as $product) {
                $sitemap->add(
                    Url::create(route('products.show', $product->slug))
                        ->setLastModificationDate($product->updated_at ?? Carbon::now())
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $sitemap->toResponse($request);
    }
}
