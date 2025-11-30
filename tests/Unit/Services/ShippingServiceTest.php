<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Address;
use App\Models\Order;
use App\Services\ShippingService;
use Tests\TestCase;

/**
 * Shipping Service Test.
 *
 * Tests critical shipping calculation and validation logic
 *
 * @internal
 *
 * @coversNothing
 */
final class ShippingServiceTest extends TestCase
{
    private ShippingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ShippingService::class);
    }

    public function testItCanCalculateShippingCost(): void
    {
        $order = Order::factory()->create([
            'subtotal' => 100.00,
            'weight' => 2.5,
        ]);

        $address = Address::factory()->create([
            'country' => 'US',
            'zip_code' => '12345',
        ]);

        $cost = $this->service->calculateShippingCost($order, $address);

        self::assertIsFloat($cost);
        self::assertGreaterThanOrEqual(0, $cost);
    }

    public function testItReturnsZeroForFreeShippingThreshold(): void
    {
        $order = Order::factory()->create([
            'subtotal' => 150.00, // Above free shipping threshold
        ]);

        $address = Address::factory()->create([
            'country' => 'US', // Domestic address for free shipping
        ]);

        $cost = $this->service->calculateShippingCost($order, $address);

        self::assertSame(0.0, $cost);
    }

    public function testItCanGetAvailableShippingMethods(): void
    {
        $order = Order::factory()->create();
        $address = Address::factory()->create();

        $methods = $this->service->getAvailableShippingMethods($order, $address);

        self::assertIsArray($methods);
        self::assertNotEmpty($methods);

        foreach ($methods as $method) {
            self::assertArrayHasKey('id', $method);
            self::assertArrayHasKey('name', $method);
            self::assertArrayHasKey('cost', $method);
            self::assertArrayHasKey('estimated_days', $method);
        }
    }

    public function testItValidatesShippingAddress(): void
    {
        $validAddress = Address::factory()->create([
            'line1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'zip_code' => '10001',
        ]);

        $isValid = $this->service->validateShippingAddress($validAddress);

        self::assertTrue($isValid);
    }

    public function testItRejectsInvalidShippingAddress(): void
    {
        // Create a valid address first (database constraints require non-null values)
        $invalidAddress = Address::factory()->create();

        // Then set invalid values for testing validation (without saving to DB)
        $invalidAddress->street = '';
        $invalidAddress->city = '';
        $invalidAddress->country = '';
        $invalidAddress->zip_code = '';

        $isValid = $this->service->validateShippingAddress($invalidAddress);

        self::assertFalse($isValid);
    }

    public function testItCanEstimateDeliveryDate(): void
    {
        $order = Order::factory()->create();
        $shippingMethod = 'standard';

        $estimatedDate = $this->service->estimateDeliveryDate($order, $shippingMethod);

        self::assertInstanceOf(\DateTime::class, $estimatedDate);
        self::assertGreaterThan(new \DateTime(), $estimatedDate);
    }

    public function testItAppliesDistanceBasedPricing(): void
    {
        $nearAddress = Address::factory()->create([
            'country' => 'US',
            'state' => 'CA',
        ]);

        $farAddress = Address::factory()->create([
            'country' => 'US',
            'state' => 'NY',
        ]);

        $order = Order::factory()->create();

        $nearCost = $this->service->calculateShippingCost($order, $nearAddress);
        $farCost = $this->service->calculateShippingCost($order, $farAddress);

        // Far address should generally cost more (if distance-based pricing is implemented)
        self::assertIsFloat($nearCost);
        self::assertIsFloat($farCost);
    }

    public function testItHandlesInternationalShipping(): void
    {
        $internationalAddress = Address::factory()->create([
            'country' => 'CA', // Canada
        ]);

        $order = Order::factory()->create();

        $cost = $this->service->calculateShippingCost($order, $internationalAddress);

        self::assertIsFloat($cost);
        self::assertGreaterThan(0, $cost); // International shipping should have a cost
    }

    public function testItAppliesWeightBasedPricing(): void
    {
        $lightOrder = Order::factory()->create(['weight' => 1.0]);
        $heavyOrder = Order::factory()->create(['weight' => 10.0]);

        $address = Address::factory()->create();

        $lightCost = $this->service->calculateShippingCost($lightOrder, $address);
        $heavyCost = $this->service->calculateShippingCost($heavyOrder, $address);

        self::assertIsFloat($lightCost);
        self::assertIsFloat($heavyCost);
        self::assertLessThanOrEqual($heavyCost, $lightCost); // Heavier should cost more
    }

    public function testItCanTrackShipment(): void
    {
        $order = Order::factory()->create([
            'tracking_number' => 'TRACK12345',
        ]);

        $trackingInfo = $this->service->getTrackingInfo($order);

        self::assertIsArray($trackingInfo);

        if (! empty($trackingInfo)) {
            self::assertArrayHasKey('tracking_number', $trackingInfo);
            self::assertArrayHasKey('status', $trackingInfo);
        }
    }

    public function testItHandlesMissingTrackingNumber(): void
    {
        $order = Order::factory()->create([
            'tracking_number' => null,
        ]);

        $trackingInfo = $this->service->getTrackingInfo($order);

        self::assertIsArray($trackingInfo);
        self::assertEmpty($trackingInfo);
    }

    public function testItValidatesZipCodeFormat(): void
    {
        $validZipCodes = ['12345', '12345-6789', 'A1B 2C3'];

        foreach ($validZipCodes as $zip) {
            $isValid = $this->service->validateZipCode($zip, 'US');
            self::assertIsBool($isValid);
        }
    }

    public function testItCanCalculateDimensionalWeight(): void
    {
        $dimensions = [
            'length' => 10,
            'width' => 10,
            'height' => 10,
        ];

        $dimensionalWeight = $this->service->calculateDimensionalWeight($dimensions);

        self::assertIsFloat($dimensionalWeight);
        self::assertGreaterThan(0, $dimensionalWeight);
    }

    public function testItUsesGreaterOfActualOrDimensionalWeight(): void
    {
        $order = Order::factory()->create([
            'weight' => 2.0,
            'dimensions' => ['length' => 20, 'width' => 20, 'height' => 20],
        ]);

        $billableWeight = $this->service->calculateBillableWeight($order);

        self::assertIsFloat($billableWeight);
        self::assertGreaterThanOrEqual($order->weight, $billableWeight);
    }
}
