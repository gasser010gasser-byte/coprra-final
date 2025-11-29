<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class StoreModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function testStoreHasFillableAttributes(): void
    {
        // Arrange
        $attributes = [
            'name' => 'Test Store',
            'slug' => 'test-store',
            'description' => 'A test store',
            'logo_url' => 'https://example.com/logo.png',
            'website_url' => 'https://example.com',
            'country_code' => 'US',
            'supported_countries' => ['US', 'CA'],
            'is_active' => true,
            'priority' => 1,
            'affiliate_base_url' => 'https://affiliate.com/{AFFILIATE_CODE}?url={URL}',
            'affiliate_code' => 'TEST123',
            'api_config' => ['key' => 'value'],
            'currency_id' => null,
        ];

        // Act
        $store = Store::create($attributes);

        // Assert
        self::assertInstanceOf(Store::class, $store);
        self::assertSame('Test Store', $store->name);
        self::assertSame('test-store', $store->slug);
    }

    #[Test]
    public function testStoreCastsAttributesCorrectly(): void
    {
        // Arrange
        $store = Store::factory()->create([
            'is_active' => '1', // string
            'priority' => '5', // string
            'supported_countries' => ['US', 'CA'], // Pass as array, will be encoded
            'api_config' => ['key' => 'value'], // Pass as array, will be cast
        ]);

        // Refresh to ensure casts are applied - need to reload from DB
        $storeId = $store->id;
        $store = Store::find($storeId);

        // Act & Assert
        self::assertIsBool($store->is_active);
        self::assertTrue($store->is_active);
        self::assertIsInt($store->priority);
        self::assertSame(5, $store->priority);
        self::assertIsArray($store->supported_countries);
        self::assertSame(['US', 'CA'], $store->supported_countries);
        // api_config should be cast to array when reading from DB
        self::assertIsArray($store->api_config);
        self::assertSame(['key' => 'value'], $store->api_config);
    }

    #[Test]
    public function testStoreRelationships(): void
    {
        // Arrange
        $store = Store::factory()->create();
        $currency = Currency::factory()->create();
        $store->currency_id = $currency->id;
        $store->save();

        Product::factory()->create(['store_id' => $store->id]);
        PriceOffer::factory()->create(['store_id' => $store->id]);

        // Act
        $store->refresh();

        // Assert
        self::assertInstanceOf(Currency::class, $store->currency);
        self::assertSame($currency->id, $store->currency->id);
        self::assertCount(1, $store->products);
        self::assertCount(1, $store->priceOffers);
    }

    #[Test]
    public function testActiveScope(): void
    {
        // Arrange
        Store::factory()->create(['is_active' => true]);
        Store::factory()->create(['is_active' => false]);

        // Act
        $activeStores = Store::active()->get();

        // Assert
        self::assertCount(1, $activeStores);
        self::assertTrue($activeStores->first()->is_active);
    }

    #[Test]
    public function testSearchScope(): void
    {
        // Arrange
        Store::factory()->create(['name' => 'Apple Store']);
        Store::factory()->create(['name' => 'Google Store']);
        Store::factory()->create(['name' => 'Microsoft Store']);

        // Act
        $results = Store::search('Apple')->get();

        // Assert
        self::assertCount(1, $results);
        self::assertSame('Apple Store', $results->first()->name);
    }

    #[Test]
    public function testGenerateAffiliateUrl(): void
    {
        // Arrange
        $store = Store::factory()->create([
            'affiliate_base_url' => 'https://affiliate.com/{AFFILIATE_CODE}?url={URL}',
            'affiliate_code' => 'TEST123',
        ]);

        // Act
        $affiliateUrl = $store->generateAffiliateUrl('https://product.com/item');

        // Assert
        $expected = 'https://affiliate.com/TEST123?url=https%3A//product.com/item';
        self::assertSame($expected, $affiliateUrl);
    }

    #[Test]
    public function testGenerateAffiliateUrlWithoutConfig(): void
    {
        // Arrange
        $store = Store::factory()->create([
            'affiliate_base_url' => null,
            'affiliate_code' => null,
        ]);

        // Act
        $affiliateUrl = $store->generateAffiliateUrl('https://product.com/item');

        // Assert
        self::assertSame('https://product.com/item', $affiliateUrl);
    }

    #[Test]
    public function testSlugGenerationOnCreate(): void
    {
        // Arrange & Act
        $store = Store::create(['name' => 'Test Store Name']);

        // Assert
        self::assertSame('test-store-name', $store->slug);
    }

    #[Test]
    public function testValidationRules(): void
    {
        // Arrange
        $store = new Store();

        // Act
        $rules = $store->getRules();

        // Assert
        self::assertIsArray($rules);
        self::assertArrayHasKey('name', $rules);
        self::assertSame('required|string|max:255', $rules['name']);
    }
}
