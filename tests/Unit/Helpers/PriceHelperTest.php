<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\PriceHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversClass(PriceHelper::class)]
final class PriceHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    #[Test]
    public function testPriceFormattingAndCurrency(): void
    {
        // Test price formatting with different currencies
        $priceHelper = new PriceHelper();

        // Test USD formatting
        $formattedUSD = $priceHelper->formatPrice(1234.56, 'USD');
        self::assertSame('$1,234.56', $formattedUSD);

        // Test EUR formatting
        $formattedEUR = $priceHelper->formatPrice(1234.56, 'EUR');
        self::assertSame('€1,234.56', $formattedEUR);

        // Test GBP formatting
        $formattedGBP = $priceHelper->formatPrice(1234.56, 'GBP');
        self::assertSame('£1,234.56', $formattedGBP);

        // Test zero price
        $formattedZero = $priceHelper->formatPrice(0, 'USD');
        self::assertSame('$0.00', $formattedZero);

        // Test negative price
        $formattedNegative = $priceHelper->formatPrice(-50.25, 'USD');
        self::assertSame('-$50.25', $formattedNegative);

        // Test large numbers
        $formattedLarge = $priceHelper->formatPrice(1000000.99, 'USD');
        self::assertSame('$1,000,000.99', $formattedLarge);

        // Test decimal precision
        $formattedPrecision = $priceHelper->formatPrice(19.999, 'USD');
        self::assertSame('$20.00', $formattedPrecision); // Should round to 2 decimal places
    }

    #[Test]
    public function testPriceCalculationsAndDiscounts(): void
    {
        // Test price calculations including discounts and taxes
        $priceHelper = new PriceHelper();

        // Test percentage discount calculation
        $originalPrice = 100.00;
        $discountPercent = 20;
        $discountedPrice = $priceHelper->applyPercentageDiscount($originalPrice, $discountPercent);
        self::assertSame(80.00, $discountedPrice);
        self::assertIsFloat($discountedPrice);

        // Test fixed amount discount
        $fixedDiscount = 15.50;
        $discountedPriceFixed = $priceHelper->applyFixedDiscount($originalPrice, $fixedDiscount);
        self::assertSame(84.50, $discountedPriceFixed);

        // Test tax calculation
        $taxRate = 8.25; // 8.25% tax
        $priceWithTax = $priceHelper->addTax($originalPrice, $taxRate);
        self::assertSame(108.25, $priceWithTax);

        // Test compound calculations (discount then tax)
        $finalPrice = $priceHelper->addTax($discountedPrice, $taxRate);
        self::assertSame(86.60, round($finalPrice, 2));

        // Test bulk pricing tiers
        $bulkPrices = $priceHelper->calculateBulkPrice(100.00, 5); // 5 items
        self::assertArrayHasKey('unit_price', $bulkPrices);
        self::assertArrayHasKey('total_price', $bulkPrices);
        self::assertArrayHasKey('discount_applied', $bulkPrices);
        self::assertSame(500.00, $bulkPrices['total_price']); // No bulk discount for 5 items

        $bulkPricesLarge = $priceHelper->calculateBulkPrice(100.00, 50); // 50 items
        self::assertTrue($bulkPricesLarge['discount_applied']);
        self::assertLessThan(5000.00, $bulkPricesLarge['total_price']); // Should have discount
    }

    #[Test]
    public function testPriceValidationAndEdgeCases(): void
    {
        // Test price validation and edge cases
        $priceHelper = new PriceHelper();

        // Test valid price validation
        self::assertTrue($priceHelper->isValidPrice(10.50));
        self::assertTrue($priceHelper->isValidPrice(0));
        self::assertTrue($priceHelper->isValidPrice(999999.99));

        // Test invalid price validation
        self::assertFalse($priceHelper->isValidPrice(-1));
        self::assertFalse($priceHelper->isValidPrice('invalid'));
        self::assertFalse($priceHelper->isValidPrice(null));

        // Test price comparison
        self::assertTrue($priceHelper->isPriceGreater(100.00, 50.00));
        self::assertFalse($priceHelper->isPriceGreater(50.00, 100.00));
        self::assertFalse($priceHelper->isPriceGreater(50.00, 50.00));

        // Test price equality with floating point precision
        self::assertTrue($priceHelper->isPriceEqual(10.50, 10.50));
        self::assertTrue($priceHelper->isPriceEqual(10.499999, 10.50, 0.01)); // Within tolerance
        self::assertFalse($priceHelper->isPriceEqual(10.40, 10.50));

        // Test currency conversion with rate
        $convertedPrice = PriceHelper::convertCurrency(100.00, 'USD', 'EUR');
        // Note: Actual conversion depends on exchange rates in database
        self::assertIsFloat($convertedPrice);

        // Test price range validation
        self::assertTrue($priceHelper->isPriceInRange(50.00, 10.00, 100.00));
        self::assertFalse($priceHelper->isPriceInRange(150.00, 10.00, 100.00));
        self::assertFalse($priceHelper->isPriceInRange(5.00, 10.00, 100.00));

        // Test subscription pricing calculations
        $monthlyPrice = 29.99;
        $annualPrice = $priceHelper->calculateAnnualPrice($monthlyPrice, 2); // 2 months free
        self::assertSame(299.90, $annualPrice); // 10 months * 29.99

        $monthlySavings = $priceHelper->calculateMonthlySavings($monthlyPrice, $annualPrice);
        self::assertSame(59.98, round($monthlySavings, 2)); // 2 months savings
    }
}
