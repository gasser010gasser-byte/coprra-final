<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\PriceOffer;
use App\Models\Product;
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
            $this->validateOfferData($offerData);

            // Map new_price to actual persisted price column
            if (isset($offerData['new_price'])) {
                $offerData['price'] = (float) $offerData['new_price'];
                unset($offerData['new_price']);
            }

            // Default new offers to available unless explicitly provided
            $offerData['is_available'] = isset($offerData['is_available']) ? $offerData['is_available'] : true;

            $offerData['status'] = 'active';
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
            $priceOffer->delete();

            $this->logOfferDeletion($priceOffer);

            return true;
        });
    }

    private function validatePrice(float $price): void
    {
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

        if (! isset($offerData['new_price'])) {
            throw ValidationException::missingField('new_price');
        }

        if (! is_numeric($offerData['new_price']) || $offerData['new_price'] < 0) {
            throw ValidationException::invalidField('new_price', $offerData['new_price'], 'Must be a positive number');
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
        if (isset($updateData['new_price']) && (! is_numeric($updateData['new_price']) || $updateData['new_price'] < 0)) {
            throw ValidationException::invalidField('new_price', $updateData['new_price'], 'Must be a positive number');
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
