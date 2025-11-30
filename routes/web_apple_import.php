<?php

declare(strict_types=1);

/**
 * Temporary route for Apple import
 * Add this to routes/web.php temporarily.
 */

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/import-apple-now/{secret}', static function ($secret) {
    if ('START_IMPORT_2025' !== $secret) {
        abort(403, 'Invalid secret');
    }

    set_time_limit(600);

    echo "<pre style='background:#000;color:#0f0;padding:20px;'>";
    echo "🚀 APPLE IMPORT STARTING...\n\n";

    $products = [
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-15-Pro-Max/dp/B0CHX78R8V/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-15-Pro/dp/B0CHX1W1XY/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-15-Plus/dp/B0CHX2Z3WC/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-15/dp/B0CHX3Y2V6/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-14-Pro-Max/dp/B0BN94DL4R/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-14-Pro/dp/B0BN95RQQ8/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-14-Plus/dp/B0BN96FGMR/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-14/dp/B0BN97RGVP/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-13-Pro-Max/dp/B09G9BL5CP/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPhone-13/dp/B09G9D8KRQ/', 'cat' => 'Mobile Phones'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPad-10-2-inch/dp/B09G9FPHY6/', 'cat' => 'Tablets'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPad-Air/dp/B09V3HN1KC/', 'cat' => 'Tablets'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-iPad-Pro-11/dp/B0BJLFH8R3/', 'cat' => 'Tablets'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-MacBook-Air-13/dp/B0CX23GFMJ/', 'cat' => 'Laptops'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-MacBook-Pro-14/dp/B0CM5JV26D/', 'cat' => 'Laptops'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-MacBook-Pro-16/dp/B0CM5K3M9M/', 'cat' => 'Laptops'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-Watch-Series-9/dp/B0CHX7R6WJ/', 'cat' => 'Smart Watches'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-Watch-SE/dp/B0CHX8Z3XL/', 'cat' => 'Smart Watches'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-AirPods-Pro-2nd/dp/B0CHWRXH8B/', 'cat' => 'Audio Devices'],
        ['url' => 'https://www.amazon.eg/-/en/Apple-AirPods-3rd/dp/B09JQL3NWT/', 'cat' => 'Audio Devices'],
    ];

    $success = 0;
    $failed = 0;

    foreach ($products as $i => $item) {
        $num = $i + 1;
        echo "[{$num}/20] {$item['url']}\n";
        flush();

        try {
            $response = Http::timeout(20)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->get($item['url'])
            ;

            if (!$response->successful()) {
                throw new Exception('HTTP ' . $response->status());
            }

            $html = $response->body();
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $titleNodes = $xpath->query('//span[@id="productTitle"]');
            $title = 'Unknown';
            if ($titleNodes && $titleNodes->length > 0) {
                $node = $titleNodes->item(0);
                if ($node && $node->textContent !== null) {
                    $title = trim($node->textContent);
                }
            }

            $priceNodes = $xpath->query('//span[@class="a-price-whole"]');
            $price = 0;
            if ($priceNodes && $priceNodes->length > 0) {
                $node = $priceNodes->item(0);
                if ($node && $node->textContent !== null) {
                    $price = (float) str_replace(',', '', $node->textContent);
                }
            }

            $imgNodes = $xpath->query('//img[@id="landingImage"]/@src');
            $image = 'https://via.placeholder.com/800';
            if ($imgNodes && $imgNodes->length > 0) {
                $node = $imgNodes->item(0);
                if ($node && $node->nodeValue !== null) {
                    $image = $node->nodeValue;
                }
            }

            echo "   ✅ {$title}\n";
            echo "   💰 {$price} EGP\n";

            $category = Category::firstOrCreate(['name' => $item['cat']], ['slug' => Str::slug($item['cat'])]);
            $brand = Brand::firstOrCreate(['name' => 'Apple'], ['slug' => 'apple']);
            $store = Store::firstOrCreate(['name' => 'Amazon Egypt'], ['url' => 'https://www.amazon.eg', 'slug' => 'amazon-egypt']);

            $product = Product::updateOrCreate(
                ['url' => $item['url']],
                [
                    'name' => $title,
                    'slug' => Str::slug($title),
                    'price' => $price,
                    'currency' => 'EGP',
                    'image_url' => $image,
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'store_id' => $store->id,
                ]
            );

            echo "   ✅ Saved (ID: {$product->id})\n\n";
            ++$success;
        } catch (Exception $e) {
            echo "   ❌ {$e->getMessage()}\n\n";
            ++$failed;
        }

        flush();
        if ($num < 20) {
            sleep(2);
        }
    }

    echo str_repeat('=', 80) . "\n";
    echo "✅ Success: {$success}\n";
    echo "❌ Failed: {$failed}\n";
    echo "🎯 https://coprra.com/admin/products\n";
    echo '</pre>';
})->name('import-apple-temp');
