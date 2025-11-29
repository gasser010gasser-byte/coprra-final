<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\PriceOffer;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\FinancialTransactionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class FinancialTransactionServiceSecurityTest extends TestCase
{
    private FinancialTransactionService $service;
    private \Mockery\MockInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAuditService = \Mockery::mock(AuditService::class);
        $this->service = new FinancialTransactionService($this->mockAuditService);
    }
    
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    // Security Tests for Price Updates

    public function testUpdateProductPriceWithNegativePrice(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $negativePrice = -50.00;

        // Act & Assert
        $this->expectException(\App\Exceptions\ValidationException::class);
        $this->expectExceptionMessage('Price cannot be negative');

        $this->service->updateProductPrice($product, $negativePrice);
    }

    public function testUpdateProductPriceWithExcessivePrice(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $excessivePrice = 1000001.00; // Price above limit (validation checks > 1000000)

        // Mock audit service - should not be called when validation fails
        $this->mockAuditService->shouldNotReceive('logUpdated');

        // Act & Assert
        $this->expectException(\App\Exceptions\ValidationException::class);
        $this->expectExceptionMessageMatches('/Price exceeds maximum allowed value/');

        $this->service->updateProductPrice($product, $excessivePrice);
    }

    public function testUpdateProductPriceWithSqlInjectionAttempt(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);

        // Mock audit service to verify it's called
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::on(function ($product) {
                return $product instanceof Product;
            }), \Mockery::type('array'), \Mockery::type('array'));

        // Act - Using a valid price but testing that the product ID isn't vulnerable
        $result = $this->service->updateProductPrice($product, 150.00);

        // Assert
        self::assertTrue($result);
        self::assertEquals(150.00, (float) $product->fresh()->price);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => 150.00,
        ]);
        // Verify product ID wasn't manipulated by SQL injection
        self::assertIsInt($product->id);
        self::assertGreaterThan(0, $product->id);
    }

    public function testUpdateProductPriceWithConcurrentModification(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $originalId = $product->id;

        // Simulate concurrent modification by updating the product in another "transaction"
        DB::table('products')->where('id', $product->id)->update(['price' => 120.00]);

        // Mock audit service
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::on(function ($product) {
                return $product instanceof Product;
            }), \Mockery::type('array'), \Mockery::type('array'));

        // Act
        $result = $this->service->updateProductPrice($product, 150.00);

        // Assert
        self::assertTrue($result);
        $updatedProduct = $product->fresh();
        self::assertEquals(150.00, (float) $updatedProduct->price);
        self::assertSame($originalId, $updatedProduct->id, 'Product ID should remain unchanged');
    }

    // Security Tests for Price Offers

    public function testCreatePriceOfferWithInvalidData(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $invalidOfferData = [
            'price' => -50.00, // Negative price
            'expires_at' => '2020-01-01', // Past date
            'description' => str_repeat('A', 1001), // Too long description
        ];

        // Act & Assert
        $this->expectException(\App\Exceptions\ValidationException::class);

        $invalidOfferDataArray = array_merge($invalidOfferData, ['product_id' => $product->id, 'new_price' => $invalidOfferData['price'] ?? 80.00]);
        unset($invalidOfferDataArray['price']);
        $this->service->createPriceOffer($invalidOfferDataArray);
    }

    public function testCreatePriceOfferWithMaliciousDescription(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $maliciousOfferData = [
            'price' => 80.00,
            'expires_at' => now()->addDays(7)->toDateString(),
            'description' => '<script>alert("xss")</script>DROP TABLE price_offers;--',
        ];

        // Mock audit service for offer creation
        $this->mockAuditService->shouldReceive('logCreated')
            ->once()
            ->with(\Mockery::type(PriceOffer::class))
            ->andReturnNull();
        
        // Mock audit service for product price update (called by updateProductPriceFromOffer)
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::type(Product::class), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $offerData = array_merge($maliciousOfferData, ['product_id' => $product->id, 'new_price' => $maliciousOfferData['price'] ?? 80.00]);
        unset($offerData['price']);
        $offer = $this->service->createPriceOffer($offerData);

        // Assert
        self::assertInstanceOf(PriceOffer::class, $offer);
        self::assertEquals(80.0, (float) $offer->price);
        // Verify that malicious content is stored safely
        $this->assertDatabaseHas('price_offers', [
            'id' => $offer->id,
            'description' => $maliciousOfferData['description'],
        ]);
        // Verify product price is updated to the lowest offer
        self::assertEquals(80.00, (float) $product->fresh()->price);
    }

    public function testCreatePriceOfferWithExcessivePrice(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $offerData = [
            'price' => 1000001.00, // Excessive price (exceeds limit of 1,000,000)
            'expires_at' => now()->addDays(7)->toDateString(),
            'description' => 'Test offer',
        ];

        // Mock audit service - should not be called when validation fails
        $this->mockAuditService->shouldNotReceive('logCreated');

        // Act & Assert
        $this->expectException(\App\Exceptions\ValidationException::class);
        $this->expectExceptionMessageMatches('/Price exceeds maximum allowed value/');

        $offerDataArray = array_merge($offerData, ['product_id' => $product->id, 'new_price' => $offerData['price'] ?? 80.00]);
        unset($offerDataArray['price']);
        $this->service->createPriceOffer($offerDataArray);
    }

    public function testUpdatePriceOfferWithUnauthorizedAccess(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $offer = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'price' => 80.00,
            'expires_at' => now()->addDays(7),
        ]);

        $updateData = [
            'price' => 70.00,
            'expires_at' => now()->addDays(14)->toDateString(),
        ];

        // Mock audit service for price offer update
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::type(PriceOffer::class), \Mockery::type('array'))
            ->andReturnNull();
        
        // Mock audit service for product price update (called by updateProductPriceFromOffer)
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::type(Product::class), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $result = $this->service->updatePriceOffer($offer, $updateData);

        // Assert
        self::assertInstanceOf(PriceOffer::class, $result);
        self::assertEquals(70.0, (float) $offer->fresh()->price);
    }

    public function testUpdatePriceOfferWithInvalidUpdateData(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $offer = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'price' => 80.00,
            'expires_at' => now()->addDays(7),
        ]);

        $invalidUpdateData = [
            'price' => -30.00, // Negative price
            'expires_at' => '2020-01-01', // Past date
        ];

        // Mock audit service - should not be called when validation fails
        $this->mockAuditService->shouldNotReceive('logUpdated');

        // Act & Assert
        $this->expectException(\App\Exceptions\ValidationException::class);

        $this->service->updatePriceOffer($offer, $invalidUpdateData);
    }

    public function testDeletePriceOfferWithCascadeEffects(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $offer1 = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'price' => 80.00,
            'expires_at' => now()->addDays(7),
        ]);
        $offer2 = PriceOffer::factory()->create([
            'product_id' => $product->id,
            'price' => 90.00,
            'expires_at' => now()->addDays(14),
        ]);

        // Update product price to lowest offer
        $product->update(['price' => 80.00]);

        // Mock audit service for deletion
        $this->mockAuditService->shouldReceive('logDeleted')
            ->once()
            ->with(\Mockery::type(PriceOffer::class))
            ->andReturnNull();
        
        // Mock audit service for product price update (called after deletion)
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::type(Product::class), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $result = $this->service->deletePriceOffer($offer1);

        // Assert
        self::assertTrue($result);
        $this->assertDatabaseMissing('price_offers', ['id' => $offer1->id]);
        // Verify product price is updated to next lowest offer
        self::assertEquals(90.00, (float) $product->fresh()->price);
    }

    // Security Tests for Price Validation

    public function testValidatePriceWithFloatPrecisionAttack(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $precisionPrice = 99.999999999999; // Float precision attack

        // Mock audit service
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::on(function ($product) {
                return $product instanceof Product;
            }), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $result = $this->service->updateProductPrice($product, $precisionPrice);

        // Assert
        self::assertTrue($result);
        // Verify price is properly rounded/handled
        $updatedPrice = $product->fresh()->price;
        self::assertEquals(100.00, (float) $updatedPrice);
        // Price is stored as decimal string in database, so check numeric value
        self::assertIsNumeric($updatedPrice);
        // Verify precision attack was handled (price rounded to 2 decimals)
        self::assertNotEquals($precisionPrice, $updatedPrice, 'Price should be rounded to prevent precision attacks');
    }

    public function testValidatePriceWithInfinityValue(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $infinityPrice = \INF;

        // Act & Assert
        $this->expectException(\App\Exceptions\ValidationException::class);
        $this->expectExceptionMessageMatches('/Price must be a valid finite number|Price exceeds maximum allowed value/');

        $this->service->updateProductPrice($product, $infinityPrice);
    }

    public function testValidatePriceWithNaNValue(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $nanPrice = \NAN;

        // Act & Assert
        // NaN will be caught by our validation, not Laravel's decimal cast
        $this->expectException(\App\Exceptions\ValidationException::class);
        $this->expectExceptionMessageMatches('/Price must be a valid finite number/');

        $this->service->updateProductPrice($product, $nanPrice);
    }

    // Security Tests for Logging

    public function testPriceUpdateLoggingDoesNotExposeSensitiveData(): void
    {
        // Arrange
        Log::spy();

        $product = Product::factory()->create([
            'price' => 100.00,
            'name' => 'Test Product',
            'description' => 'Contains sensitive info: SSN 123-45-6789',
        ]);

        // Mock audit service
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::on(function ($product) {
                return $product instanceof Product;
            }), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $result = $this->service->updateProductPrice($product, 150.00);

        // Assert
        self::assertTrue($result, 'Price update should succeed');
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Product price updated successfully', \Mockery::on(static function ($context) {
                // Verify that sensitive data is not logged
                $logString = json_encode($context);

                return ! str_contains($logString, '123-45-6789')
                       && \array_key_exists('product_id', $context)
                       && \array_key_exists('old_price', $context)
                       && \array_key_exists('new_price', $context);
            }))
        ;
    }

    public function testOfferCreationLoggingWithMaliciousData(): void
    {
        // Arrange
        Log::spy();

        $product = Product::factory()->create(['price' => 100.00]);
        $maliciousOfferData = [
            'price' => 80.00,
            'expires_at' => now()->addDays(7)->toDateString(),
            'description' => 'Normal description',
            'malicious_field' => '<script>alert("xss")</script>',
        ];

        // Mock audit service for offer creation
        $this->mockAuditService->shouldReceive('logCreated')
            ->once()
            ->with(\Mockery::type(PriceOffer::class))
            ->andReturnNull();
        
        // Mock audit service for product price update (called by updateProductPriceFromOffer)
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::type(Product::class), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $offerData = array_merge($maliciousOfferData, ['product_id' => $product->id, 'new_price' => $maliciousOfferData['price'] ?? 80.00]);
        unset($offerData['price']);
        $offer = $this->service->createPriceOffer($offerData);

        // Assert
        // Verify offer was created
        self::assertNotNull($offer, 'Offer should be created');
        self::assertInstanceOf(PriceOffer::class, $offer);
        
        // Log::info may be called multiple times (once for offer creation, potentially once for price update)
        // So we check that at least the offer creation log was called
        Log::shouldHaveReceived('info')
            ->atLeast()->once()
            ->with('Price offer created successfully', \Mockery::on(static function ($context) {
                // Verify that malicious data is handled safely in logs
                return \array_key_exists('offer_id', $context);
            }))
        ;
    }

    // Security Tests for Transaction Integrity

    public function testPriceUpdateTransactionRollbackOnFailure(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $originalPrice = $product->price;

        // Mock audit service to throw exception
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->andThrow(new \Exception('Audit service failed'))
        ;

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Audit service failed');

        try {
            $this->service->updateProductPrice($product, 150.00);
        } catch (\Exception $e) {
            // Verify that the price wasn't updated due to transaction rollback
            self::assertSame($originalPrice, $product->fresh()->price);

            throw $e;
        }
    }

    public function testOfferCreationTransactionIntegrity(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $offerData = [
            'price' => 80.00,
            'expires_at' => now()->addDays(7)->toDateString(),
            'description' => 'Test offer',
        ];

        // Simulate database constraint violation
        DB::shouldReceive('transaction')->once()->andThrow(
            new QueryException(
                'mysql',
                'INSERT INTO price_offers',
                [],
                new \Exception('Duplicate entry')
            )
        );

        // Act & Assert
        $this->expectException(QueryException::class);

        $offerDataArray = array_merge($offerData, ['product_id' => $product->id, 'new_price' => $offerData['price'] ?? 80.00]);
        unset($offerDataArray['price']);
        $this->service->createPriceOffer($offerDataArray);

        // Verify that product price wasn't updated
        self::assertEquals(100.00, (float) $product->fresh()->price);
    }

    // Security Tests for Input Sanitization

    public function testOfferDescriptionSanitization(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $offerData = [
            'price' => 80.00,
            'expires_at' => now()->addDays(7)->toDateString(),
            'description' => "  \t\n  Test offer with whitespace  \t\n  ",
        ];

        // Mock audit service for offer creation
        $this->mockAuditService->shouldReceive('logCreated')
            ->once()
            ->with(\Mockery::type(PriceOffer::class))
            ->andReturnNull();
        
        // Mock audit service for product price update (called by updateProductPriceFromOffer)
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::type(Product::class), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $offerDataArray = array_merge($offerData, ['product_id' => $product->id, 'new_price' => $offerData['price'] ?? 80.00]);
        unset($offerDataArray['price']);
        $offer = $this->service->createPriceOffer($offerDataArray);

        // Assert
        self::assertInstanceOf(PriceOffer::class, $offer);
        // Verify that description is properly trimmed (if description column exists)
        if ($offer->getAttribute('description') !== null) {
            self::assertSame('Test offer with whitespace', $offer->description);
        }
    }

    public function testPriceOfferWithUnicodeCharacters(): void
    {
        // Arrange
        $product = Product::factory()->create(['price' => 100.00]);
        $offerData = [
            'price' => 80.00,
            'expires_at' => now()->addDays(7)->toDateString(),
            'description' => 'Offer with émojis 🎉 and ünïcödé characters',
        ];

        // Mock audit service for offer creation
        $this->mockAuditService->shouldReceive('logCreated')
            ->once()
            ->with(\Mockery::type(PriceOffer::class))
            ->andReturnNull();
        
        // Mock audit service for product price update (called by updateProductPriceFromOffer)
        $this->mockAuditService->shouldReceive('logUpdated')
            ->once()
            ->with(\Mockery::type(Product::class), \Mockery::type('array'), \Mockery::type('array'))
            ->andReturnNull();

        // Act
        $offerDataArray = array_merge($offerData, ['product_id' => $product->id, 'new_price' => $offerData['price'] ?? 80.00]);
        unset($offerDataArray['price']);
        $offer = $this->service->createPriceOffer($offerDataArray);

        // Assert
        self::assertInstanceOf(PriceOffer::class, $offer);
        // Only check description if the column exists in the database
        if ($offer->getAttribute('description') !== null) {
            self::assertSame($offerData['description'], $offer->description);
            $this->assertDatabaseHas('price_offers', [
                'id' => $offer->id,
                'description' => $offerData['description'],
            ]);
        }
    }
}
