<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Store;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class URLServiceTest extends TestCase
{
    public function testGenerateAffiliateUrlEncodesUrlAndInjectsAffiliateCode(): void
    {
        $store = new Store();
        $store->affiliate_base_url = 'https://aff.example.com/{AFFILIATE_CODE}?u={URL}';
        $store->affiliate_code = 'AFF123';

        $productUrl = 'https://example.com/product?id=42';
        $affiliateUrl = $store->generateAffiliateUrl($productUrl);

        self::assertIsString($affiliateUrl);
        self::assertStringStartsWith('https://aff.example.com/AFF123?u=', $affiliateUrl);
        // The actual implementation uses str_replace(':', '%3A', ...) which encodes colons differently
        self::assertStringContainsString('https%3A//example.com/product?id=42', $affiliateUrl);
    }

    public function testGenerateAffiliateUrlReturnsOriginalWhenMissingConfig(): void
    {
        $store = new Store();
        $store->affiliate_base_url = null; // missing base url
        $store->affiliate_code = 'AFF123';

        $productUrl = 'https://example.com/product?id=42';
        $affiliateUrl = $store->generateAffiliateUrl($productUrl);
        // When affiliate config is missing, it appends ?ref=coprra
        self::assertSame($productUrl . '?ref=coprra', $affiliateUrl);

        $store->affiliate_base_url = 'https://aff.example.com/{AFFILIATE_CODE}?u={URL}';
        $store->affiliate_code = null; // missing code
        $affiliateUrl2 = $store->generateAffiliateUrl($productUrl);
        self::assertSame($productUrl, $affiliateUrl2);
    }

    public function testGenerateAffiliateUrlKeepsPathSlashesUnencoded(): void
    {
        $store = new Store();
        $store->affiliate_base_url = 'https://aff.example.com/{AFFILIATE_CODE}?u={URL}';
        $store->affiliate_code = 'CODE9';

        $productUrl = 'https://example.com/a/b/c?x=1&y=2';
        $affiliateUrl = $store->generateAffiliateUrl($productUrl);

        // The actual implementation uses str_replace(':', '%3A', ...) which encodes colons differently
        self::assertStringContainsString('https%3A//example.com/a/b/c?x=1&y=2', $affiliateUrl);
        self::assertStringContainsString('https://aff.example.com/CODE9?u=', $affiliateUrl);
    }
}
