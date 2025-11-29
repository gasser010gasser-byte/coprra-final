<?php

declare(strict_types=1);

namespace Tests\Unit\COPRRA;

use App\Services\StoreAdapterManager;
use App\Services\StoreAdapters\AmazonAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversClass(StoreAdapterManager::class)]
final class StoreAdapterManagerTest extends TestCase
{
    protected StoreAdapterManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new StoreAdapterManager([]);
    }

    public function testItRegistersDefaultAdapters(): void
    {
        $adapters = $this->manager->getAllAdapters();

        // The manager now registers 5 adapters: amazon, ebay, noon, jumia, bestbuy
        self::assertCount(5, $adapters);
        self::assertArrayHasKey('amazon', $adapters);
        self::assertArrayHasKey('ebay', $adapters);
        self::assertArrayHasKey('noon', $adapters);
        self::assertArrayHasKey('jumia', $adapters);
        self::assertArrayHasKey('bestbuy', $adapters);
    }

    public function testItGetsAdapterByIdentifier(): void
    {
        $adapter = $this->manager->getAdapter('amazon');

        self::assertInstanceOf(AmazonAdapter::class, $adapter);
        self::assertSame('Amazon', $adapter->getStoreName());
    }

    public function testItReturnsNullForUnknownAdapter(): void
    {
        $adapter = $this->manager->getAdapter('unknown_store');

        self::assertNull($adapter);
    }

    public function testItChecksIfStoreIsSupported(): void
    {
        self::assertTrue($this->manager->isStoreSupported('amazon'));
        self::assertTrue($this->manager->isStoreSupported('ebay'));
        self::assertTrue($this->manager->isStoreSupported('noon'));
        self::assertFalse($this->manager->isStoreSupported('unknown'));
    }

    public function testItGetsSupportedStores(): void
    {
        $stores = $this->manager->getSupportedStores();

        self::assertIsArray($stores);
        self::assertContains('amazon', $stores);
        self::assertContains('ebay', $stores);
        self::assertContains('noon', $stores);
    }

    public function testItValidatesAmazonIdentifier(): void
    {
        self::assertTrue($this->manager->validateIdentifier('amazon', 'B08N5WRWNW'));
        self::assertFalse($this->manager->validateIdentifier('amazon', 'invalid'));
        self::assertFalse($this->manager->validateIdentifier('amazon', '123'));
    }

    public function testItValidatesEbayIdentifier(): void
    {
        self::assertTrue($this->manager->validateIdentifier('ebay', '123456789012'));
        self::assertFalse($this->manager->validateIdentifier('ebay', 'abc'));
        self::assertFalse($this->manager->validateIdentifier('ebay', '123'));
    }

    public function testItValidatesNoonIdentifier(): void
    {
        self::assertTrue($this->manager->validateIdentifier('noon', 'N12345678'));
        self::assertFalse($this->manager->validateIdentifier('noon', '12345678'));
        self::assertFalse($this->manager->validateIdentifier('noon', 'ABC123'));
    }

    public function testItGetsProductUrlForAmazon(): void
    {
        $url = $this->manager->getProductUrl('amazon', 'B08N5WRWNW');

        self::assertStringContainsString('amazon.com', $url);
        self::assertStringContainsString('B08N5WRWNW', $url);
    }

    public function testItGetsProductUrlForEbay(): void
    {
        $url = $this->manager->getProductUrl('ebay', '123456789012');

        self::assertStringContainsString('ebay.com', $url);
        self::assertStringContainsString('123456789012', $url);
    }

    public function testItGetsProductUrlForNoon(): void
    {
        $url = $this->manager->getProductUrl('noon', 'N12345678');

        self::assertStringContainsString('noon.com', $url);
        self::assertStringContainsString('N12345678', $url);
    }

    public function testItReturnsNullForUnknownStoreUrl(): void
    {
        $url = $this->manager->getProductUrl('unknown', '123');

        self::assertNull($url);
    }

    public function testItGetsStatistics(): void
    {
        $stats = $this->manager->getStatistics();

        self::assertIsArray($stats);
        self::assertArrayHasKey('total_adapters', $stats);
        self::assertArrayHasKey('available_adapters', $stats);
        self::assertArrayHasKey('adapters', $stats);
        // The manager now registers 5 adapters: amazon, ebay, noon, jumia, bestbuy
        self::assertSame(5, $stats['total_adapters']);
    }

    public function testItGetsAdapterRateLimits(): void
    {
        $adapter = $this->manager->getAdapter('amazon');
        $limits = $adapter->getRateLimits();

        self::assertIsArray($limits);
        self::assertArrayHasKey('requests_per_minute', $limits);
        self::assertArrayHasKey('requests_per_hour', $limits);
        self::assertArrayHasKey('requests_per_day', $limits);
    }

    public function testAmazonAdapterHasCorrectProperties(): void
    {
        $adapter = $this->manager->getAdapter('amazon');

        self::assertSame('Amazon', $adapter->getStoreName());
        self::assertSame('amazon', $adapter->getStoreIdentifier());
    }

    public function testEbayAdapterHasCorrectProperties(): void
    {
        $adapter = $this->manager->getAdapter('ebay');

        self::assertSame('eBay', $adapter->getStoreName());
        self::assertSame('ebay', $adapter->getStoreIdentifier());
    }

    public function testNoonAdapterHasCorrectProperties(): void
    {
        $adapter = $this->manager->getAdapter('noon');

        self::assertSame('Noon', $adapter->getStoreName());
        self::assertSame('noon', $adapter->getStoreIdentifier());
    }
}
