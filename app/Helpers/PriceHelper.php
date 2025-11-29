<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Currency;
use App\Services\ExchangeRateService;

final class PriceHelper
{
    /**
     * Format price with currency symbol.
     */
    public static function formatPrice(float $price, ?string $currencyCode = null): string
    {
        $currencyCode ??= config('coprra.default_currency', 'USD');

        $symbol = self::getCurrencySymbol($currencyCode);
        $formatted = number_format(abs($price), 2);
        
        // Handle negative prices: put minus sign before currency symbol
        if ($price < 0) {
            return '-'.$symbol.$formatted;
        }

        return $symbol.$formatted;
    }

    /**
     * Format price with currency symbol (static version for backward compatibility).
     */
    public static function formatPriceStatic(float $price, ?string $currencyCode = null): string
    {
        $currencyCode ??= config('coprra.default_currency', 'USD');

        $symbol = self::getCurrencySymbol($currencyCode);

        return $symbol.number_format($price, 2);
    }

    /**
     * Calculate price difference percentage.
     */
    public static function calculatePriceDifference(float $originalPrice, float $comparePrice): float
    {
        if ($originalPrice <= 0) {
            return 0.0;
        }

        return ($comparePrice - $originalPrice) / $originalPrice * 100;
    }

    /**
     * Get price difference as formatted string.
     */
    public static function getPriceDifferenceString(float $originalPrice, float $comparePrice): string
    {
        $difference = self::calculatePriceDifference($originalPrice, $comparePrice);
        if ($difference > 0) {
            return '+'.number_format($difference, 1).'%';
        }

        if ($difference < 0) {
            return number_format($difference, 1).'%';
        }

        return '0%';
    }

    /**
     * Apply percentage discount to a price.
     */
    public function applyPercentageDiscount(float $price, float $percentage): float
    {
        if ($percentage < 0 || $percentage > 100) {
            return $price;
        }

        return $price * (1 - ($percentage / 100));
    }

    /**
     * Apply fixed amount discount to a price.
     */
    public function applyFixedDiscount(float $price, float $discount): float
    {
        if ($discount < 0) {
            return $price;
        }

        return max(0, $price - $discount);
    }

    /**
     * Add tax to a price.
     */
    public function addTax(float $price, float $taxRate): float
    {
        if ($taxRate < 0) {
            return $price;
        }

        return $price * (1 + ($taxRate / 100));
    }

    /**
     * Calculate bulk price with discount tiers.
     *
     * @return array<string, mixed>
     */
    public function calculateBulkPrice(float $unitPrice, int $quantity): array
    {
        $totalPrice = $unitPrice * $quantity;
        $discountApplied = false;
        $discountPercent = 0;

        // Apply bulk discount tiers
        if ($quantity >= 50) {
            $discountPercent = 10; // 10% discount for 50+ items
            $discountApplied = true;
        } elseif ($quantity >= 20) {
            $discountPercent = 5; // 5% discount for 20+ items
            $discountApplied = true;
        }

        if ($discountApplied) {
            $totalPrice = $this->applyPercentageDiscount($totalPrice, $discountPercent);
        }

        return [
            'unit_price' => $unitPrice,
            'total_price' => round($totalPrice, 2),
            'discount_applied' => $discountApplied,
            'discount_percent' => $discountPercent,
        ];
    }

    /**
     * Validate if a price is valid (positive and finite).
     */
    public function isValidPrice(mixed $price): bool
    {
        if (!is_numeric($price)) {
            return false;
        }

        $priceFloat = (float) $price;

        return $priceFloat >= 0 && is_finite($priceFloat) && !is_nan($priceFloat);
    }

    /**
     * Check if first price is greater than second price.
     */
    public function isPriceGreater(float $price1, float $price2): bool
    {
        return $price1 > $price2;
    }

    /**
     * Check if two prices are equal within tolerance.
     */
    public function isPriceEqual(float $price1, float $price2, float $tolerance = 0.01): bool
    {
        return abs($price1 - $price2) <= $tolerance;
    }

    /**
     * Check if price is within range.
     */
    public function isPriceInRange(float $price, float $min, float $max): bool
    {
        return $price >= $min && $price <= $max;
    }

    /**
     * Calculate annual price from monthly price with free months.
     */
    public function calculateAnnualPrice(float $monthlyPrice, int $freeMonths = 0): float
    {
        $monthsToCharge = 12 - $freeMonths;

        return round($monthlyPrice * $monthsToCharge, 2);
    }

    /**
     * Calculate monthly savings from annual subscription.
     */
    public function calculateMonthlySavings(float $monthlyPrice, float $annualPrice): float
    {
        $annualEquivalent = $monthlyPrice * 12;

        return round($annualEquivalent - $annualPrice, 2);
    }

    /**
     * Check if price is a good deal (below average).
     *
     * @param array<float> $allPrices
     */
    public static function isGoodDeal(float $price, array $allPrices): bool
    {
        if ([] === $allPrices) {
            return false;
        }

        $average = array_sum($allPrices) / \count($allPrices);

        // Consider a price a good deal if it's strictly below the average
        return $price < $average;
    }

    /**
     * Get best price from array of prices.
     *
     * @param array<float> $prices
     */
    public static function getBestPrice(array $prices): ?float
    {
        if ([] === $prices) {
            return null;
        }

        return min($prices);
    }

    /**
     * Convert price between currencies using database exchange rates (static version).
     */
    public static function convertCurrencyStatic(float $amount, string $fromCurrency, string $toCurrency): float
    {
        // Prefer Currency model exchange_rate values set in DB/tests
        /** @var Currency|null $from */
        $from = Currency::where('code', $fromCurrency)->first();

        /** @var Currency|null $to */
        $to = Currency::where('code', $toCurrency)->first();

        if ($from && $to && is_numeric($from->exchange_rate) && is_numeric($to->exchange_rate)) {
            $fromRate = (float) $from->exchange_rate;
            $toRate = (float) $to->exchange_rate;

            if ($fromRate > 0) {
                return round($amount / $fromRate * $toRate, 2);
            }
        }

        // Fallback to ExchangeRateService (DB/cache/config/API)
        $service = app(ExchangeRateService::class);

        return $service->convert($amount, $fromCurrency, $toCurrency);
    }

    /**
     * Convert price between currencies using database exchange rates.
     */
    public static function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        return self::convertCurrencyStatic($amount, $fromCurrency, $toCurrency);
    }

    /**
     * Format price range.
     * Accepts numeric strings or integers and safely casts to float.
     */
    public static function formatPriceRange(float|int|string $minPrice, float|int|string $maxPrice, ?string $currencyCode = null): string
    {
        $symbol = self::getCurrencySymbol($currencyCode);

        $min = is_numeric($minPrice) ? (float) $minPrice : 0.0;
        $max = is_numeric($maxPrice) ? (float) $maxPrice : 0.0;

        if ($min === $max) {
            return $symbol.number_format($min, 2);
        }

        return $symbol.number_format($min, 2).' - '.$symbol.number_format($max, 2);
    }

    private static function getCurrencySymbol(?string $currencyCode): string
    {
        $currencyCode ??= config('coprra.default_currency', 'USD');

        /** @var Currency|null $currency */
        $currency = Currency::where('code', $currencyCode)->first();

        if (! $currency) {
            // Fallback to common currency symbols
            $symbols = [
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'SAR' => 'ر.س',
                'AED' => 'د.إ',
            ];

            return $symbols[$currencyCode] ?? ($currencyCode ?? 'USD');
        }

        return (string) $currency->symbol;
    }
}
