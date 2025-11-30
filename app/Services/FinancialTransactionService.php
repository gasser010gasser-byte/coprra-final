<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\PriceOffer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class FinancialTransactionService
{
    public function __construct(private AuditService $auditService)
    {
    }

    public function updateProductPrice(Product $product, float $newPrice, ?string $reason = null): bool
    {
        // @var bool $result
        return DB::transaction(function () use ($product, $newPrice, $reason): bool {
            $oldPrice = (float) $product->price;
            $this->validatePrice($newPrice);

            $product->update(['price' => $newPrice]);

            $this->logPriceUpdate($product, $oldPrice, $newPrice, $reason);

            $this->checkPriceAlerts();

            return true;
        });
    }

    /**
     * @psalm-param array{product_id:int|string, new_price:numeric-string|float, price?:float, is_available?:bool, expires_at?:string|null, status?:string} $offerData
     */
    public function createPriceOffer(array $offerData): PriceOffer
    {
        // @var PriceOffer $priceOffer
        return DB::transaction(function () use ($offerData): PriceOffer {
            // Validate before converting - validateOfferData expects new_price
            $this->validateOfferData($offerData);

            // Map new_price to actual persisted price column
            if (isset($offerData['new_price'])) {
                $offerData['price'] = (float) $offerData['new_price'];
                unset($offerData['new_price']);
            } elseif (! isset($offerData['price'])) {
                // Fallback: if price is provided directly, use it
                if (! isset($offerData['price'])) {
                    throw ValidationException::missingField('new_price');
                }
            }

            // If store_id is not provided, get it from the product (before validation)
            if (! isset($offerData['store_id']) && isset($offerData['product_id'])) {
                $product = Product::find($offerData['product_id']);
                if ($product && $product->store_id) {
                    $offerData['store_id'] = $product->store_id;
                } else {
                    // If product doesn't have store_id, get first active store or create a default one
                    $store = Store::where('is_active', true)->first();
                    if ($store) {
                        $offerData['store_id'] = $store->id;
                    } else {
                        // Create a default store if none exists (for testing purposes)
                        $store = Store::create([
                            'name' => 'Default Store',
                            'is_active' => true,
                        ]);
                        $offerData['store_id'] = $store->id;
                    }
                }
            } elseif (! isset($offerData['store_id'])) {
                // If no product_id and no store_id, get or create default store
                $store = Store::where('is_active', true)->first();
                if (! $store) {
                    $store = Store::create([
                        'name' => 'Default Store',
                        'is_active' => true,
                    ]);
                }
                $offerData['store_id'] = $store->id;
            }

            // Default new offers to available unless explicitly provided
            $offerData['is_available'] = isset($offerData['is_available']) ? $offerData['is_available'] : true;

            $offerData['status'] = 'active';

            // Trim description if present
            if (isset($offerData['description']) && is_string($offerData['description'])) {
                $offerData['description'] = trim($offerData['description']);
            }

            $newOffer = PriceOffer::query()->create($offerData);

            $this->logOfferCreation($newOffer);

            $this->updateProductPriceFromOffer($newOffer);

            return $newOffer;
        });
    }

    /**
     * @param array<string, mixed> $updateData
     */
    public function updatePriceOffer(PriceOffer $priceOffer, array $updateData): PriceOffer
    {
        // @var PriceOffer $updated
        return DB::transaction(function () use ($priceOffer, $updateData): PriceOffer {
            $this->validateOfferUpdateData($updateData);

            // Map new_price to actual persisted price column on updates
            if (isset($updateData['new_price'])) {
                $updateData['price'] = (float) $updateData['new_price'];
                unset($updateData['new_price']);
            }

            // Trim description if present
            if (isset($updateData['description']) && is_string($updateData['description'])) {
                $updateData['description'] = trim($updateData['description']);
            }

            $oldData = $priceOffer->toArray();
            $priceOffer->update($updateData);

            $this->logOfferUpdate($priceOffer, $oldData);

            $this->updateProductPriceFromOffer($priceOffer);

            return $priceOffer;
        });
    }

    public function deletePriceOffer(PriceOffer $priceOffer): bool
    {
        // @var bool $deleted
        return DB::transaction(function () use ($priceOffer): bool {
            $product = $priceOffer->product;
            $priceOffer->delete();

            $this->logOfferDeletion($priceOffer);

            // Update product price to next lowest offer after deletion
            if ($product) {
                $lowestOffer = PriceOffer::where('product_id', $product->id)
                    ->where('is_available', true)
                    ->orderBy('price')
                    ->first();

                if ($lowestOffer && $product->price !== $lowestOffer->price) {
                    $oldPrice = (float) $product->price;
                    $newPrice = (float) $lowestOffer->price;
                    $product->update(['price' => $newPrice]);
                    $this->logPriceUpdate($product, $oldPrice, $newPrice, 'Updated from price offer deletion');
                } elseif (! $lowestOffer) {
                    // No offers left, keep current price or set to original price
                    // For now, we'll keep the current price
                }
            }

            return true;
        });
    }

    private function validatePrice(float $price): void
    {
        if (! is_finite($price) || is_nan($price)) {
            throw ValidationException::invalidField('price', $price, 'Price must be a valid finite number');
        }

        if ($price < 0) {
            throw ValidationException::invalidField('price', $price, 'Price cannot be negative');
        }

        if ($price > 1000000) {
            throw ValidationException::invalidField('price', $price, 'Price exceeds maximum allowed value of 1,000,000');
        }
    }

    private function logPriceUpdate(Product $product, float $oldPrice, float $newPrice, ?string $reason): void
    {
        $this->auditService->logUpdated($product, ['price' => $oldPrice], [
            'reason' => $reason,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'price_change' => $newPrice - $oldPrice,
            'percentage_change' => $oldPrice > 0 ? ($newPrice - $oldPrice) / $oldPrice * 100 : 0,
        ]);

        Log::info('Product price updated successfully', [
            'product_id' => $product->id,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'reason' => $reason,
        ]);
    }

    private function validateOfferData(array $offerData): void
    {
        if (! isset($offerData['product_id'])) {
            throw ValidationException::missingField('product_id');
        }

        // Accept either new_price or price for validation
        $priceKey = isset($offerData['new_price']) ? 'new_price' : (isset($offerData['price']) ? 'price' : null);

        if (! $priceKey) {
            throw ValidationException::missingField('new_price');
        }

        $priceValue = (float) $offerData[$priceKey];

        // Use the same validation as validatePrice
        if (! is_finite($priceValue) || is_nan($priceValue)) {
            throw ValidationException::invalidField('new_price', $priceValue, 'Price must be a valid finite number');
        }

        if ($priceValue < 0) {
            throw ValidationException::invalidField('new_price', $priceValue, 'Must be a positive number');
        }

        if ($priceValue > 1000000) {
            throw ValidationException::invalidField('new_price', $priceValue, 'Price exceeds maximum allowed value of 1,000,000');
        }

        if (isset($offerData['expires_at']) && ! strtotime($offerData['expires_at'])) {
            throw ValidationException::invalidFormat('expires_at', 'valid date string');
        }
    }

    private function logOfferCreation(PriceOffer $priceOffer): void
    {
        $this->auditService->logCreated($priceOffer);

        Log::info('Price offer created successfully', ['offer_id' => $priceOffer->id]);
    }

    private function validateOfferUpdateData(array $updateData): void
    {
        // Check both new_price and price fields for validation
        $priceKey = isset($updateData['new_price']) ? 'new_price' : (isset($updateData['price']) ? 'price' : null);

        if ($priceKey) {
            $priceValue = (float) $updateData[$priceKey];
            if (! is_numeric($updateData[$priceKey]) || $priceValue < 0) {
                throw ValidationException::invalidField($priceKey, $updateData[$priceKey], 'Must be a positive number');
            }

            // Also check maximum price limit
            if ($priceValue > 1000000) {
                throw ValidationException::invalidField($priceKey, $priceValue, 'Price exceeds maximum allowed value of 1,000,000');
            }
        }

        if (isset($updateData['expires_at']) && ! strtotime($updateData['expires_at'])) {
            throw ValidationException::invalidFormat('expires_at', 'valid date string');
        }
    }

    private function logOfferUpdate(PriceOffer $priceOffer, array $oldData): void
    {
        $this->auditService->logUpdated($priceOffer, $oldData);

        Log::info('Price offer updated successfully', ['offer_id' => $priceOffer->id]);
    }

    private function logOfferDeletion(PriceOffer $priceOffer): void
    {
        $this->auditService->logDeleted($priceOffer);

        Log::info('Price offer deleted successfully', ['offer_id' => $priceOffer->id]);
    }

    private function updateProductPriceFromOffer(PriceOffer $priceOffer): void
    {
        $product = $priceOffer->product;
        if (! $product) {
            return;
        }

        $lowestOffer = PriceOffer::where('product_id', $product->id)
            ->where('is_available', true)
            ->orderBy('price')
            ->first()
        ;

        if ($lowestOffer && $product->price !== $lowestOffer->price) {
            // Direct update to avoid infinite recursion - we're already in a transaction
            $oldPrice = (float) $product->price;
            $newPrice = (float) $lowestOffer->price;

            $product->update(['price' => $newPrice]);
            $this->logPriceUpdate($product, $oldPrice, $newPrice, 'Updated from price offer');
        }
    }

    private function checkPriceAlerts(): void
    {
        // This would integrate with the notification system
        // to send alerts when price drops below target
    }
}
