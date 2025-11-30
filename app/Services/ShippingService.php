<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use Carbon\Carbon;

/**
 * ShippingService
 * خدمة مبسطة للتعامل مع الشحن.
 */
final class ShippingService
{
    private const FREE_SHIPPING_THRESHOLD = 150.0;
    private const STANDARD_SHIPPING_COST = 5.99;
    private const EXPRESS_SHIPPING_COST = 12.99;
    private const INTERNATIONAL_SHIPPING_COST = 25.99;
    private const WEIGHT_RATE = 0.5; // $0.50 per kg

    /**
     * إرجاع تكلفة شحن افتراضية.
     */
    public function getDefaultShippingCost(): float
    {
        return self::STANDARD_SHIPPING_COST;
    }

    /**
     * Calculate shipping cost for an order and address.
     */
    public function calculateShippingCost(Order $order, Address $address): float
    {
        // Get subtotal - use subtotal if available, otherwise calculate from order total
        $subtotal = $order->subtotal ?? ($order->total_amount ?? 0);

        // Check if international shipping (default to US if country is not set)
        $country = $address->country ?? 'US';
        $isInternational = !empty($country) && strtoupper($country) !== 'US';

        // Free shipping for domestic orders above threshold (international always has cost)
        if (!$isInternational && $subtotal >= self::FREE_SHIPPING_THRESHOLD) {
            return 0.0;
        }

        $baseCost = $isInternational ? self::INTERNATIONAL_SHIPPING_COST : self::STANDARD_SHIPPING_COST;

        // Add weight-based cost
        $weight = $order->weight ?? 0;
        $weightCost = $weight * self::WEIGHT_RATE;

        $totalCost = $baseCost + $weightCost;

        // Ensure international shipping always has a cost
        if ($isInternational && $totalCost <= 0) {
            $totalCost = self::INTERNATIONAL_SHIPPING_COST;
        }

        return round($totalCost, 2);
    }

    /**
     * Get available shipping methods.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableShippingMethods(Order $order, Address $address): array
    {
        return [
            [
                'id' => 'standard',
                'name' => 'Standard Shipping',
                'cost' => $this->calculateShippingCost($order, $address),
                'estimated_days' => 5,
            ],
            [
                'id' => 'express',
                'name' => 'Express Shipping',
                'cost' => self::EXPRESS_SHIPPING_COST,
                'estimated_days' => 2,
            ],
        ];
    }

    /**
     * Validate shipping address.
     */
    public function validateShippingAddress(Address $address): bool
    {
        return !empty($address->street) &&
            !empty($address->city) &&
            !empty($address->country) &&
            !empty($address->zip_code);
    }

    /**
     * Estimate delivery date.
     */
    public function estimateDeliveryDate(Order $order, string $shippingMethod = 'standard'): \DateTime
    {
        $days = match ($shippingMethod) {
            'express' => 2,
            'standard' => 5,
            default => 7,
        };

        return Carbon::now()->addDays($days)->toDateTime();
    }

    /**
     * Get tracking information.
     *
     * @return array<string, mixed>
     */
    public function getTrackingInfo(Order $order): array
    {
        $trackingNumber = $order->tracking_number ?? null;

        if (!$trackingNumber) {
            return [];
        }

        return [
            'tracking_number' => $trackingNumber,
            'status' => 'in_transit',
            'estimated_delivery' => $order->delivered_at ? $order->delivered_at->format('Y-m-d') : null,
        ];
    }

    /**
     * Validate zip code format.
     */
    public function validateZipCode(string $zipCode, string $country = 'US'): bool
    {
        return match ($country) {
            'US' => (bool) preg_match('/^\d{5}(-\d{4})?$/', $zipCode),
            'CA' => (bool) preg_match('/^[A-Z]\d[A-Z] ?\d[A-Z]\d$/', $zipCode),
            default => strlen($zipCode) >= 4 && strlen($zipCode) <= 10,
        };
    }

    /**
     * Calculate dimensional weight.
     */
    public function calculateDimensionalWeight(array $dimensions): float
    {
        $length = $dimensions['length'] ?? 0;
        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;

        // Dimensional weight formula: (L x W x H) / 166 (for inches) or / 5000 (for cm)
        return round(($length * $width * $height) / 166, 2);
    }

    /**
     * Calculate billable weight (greater of actual or dimensional).
     */
    public function calculateBillableWeight(Order $order): float
    {
        $actualWeight = $order->weight ?? 0;
        $dimensions = $order->dimensions ?? [];

        if (empty($dimensions)) {
            return $actualWeight;
        }

        $dimensionalWeight = $this->calculateDimensionalWeight($dimensions);

        return max($actualWeight, $dimensionalWeight);
    }
}
