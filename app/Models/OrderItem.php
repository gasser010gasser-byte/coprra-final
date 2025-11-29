<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'total',
        'unit_price',
        'subtotal',
        'product_details',
    ];

    protected $casts = [
        'product_details' => 'array',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function (OrderItem $item): void {
            // Ensure price is set before calculatePrices runs
            if (!isset($item->attributes['price']) && isset($item->attributes['unit_price'])) {
                $item->attributes['price'] = $item->attributes['unit_price'];
            }
            $item->calculatePrices();
        });

        static::updating(static function (OrderItem $item): void {
            if ($item->isDirty(['quantity', 'price', 'unit_price'])) {
                $item->calculatePrices();
            }
        });
    }

    /**
     * Calculate price and total based on quantity and unit_price or price.
     */
    private function calculatePrices(): void
    {
        // Get unit_price from attributes or property
        $unitPrice = $this->attributes['unit_price'] ?? $this->unit_price ?? null;
        // Get price from attributes or property
        $price = $this->attributes['price'] ?? $this->price ?? null;
        
        // Determine base unit price - prefer unit_price, fallback to price
        $baseUnitPrice = $unitPrice ?? $price ?? 0;
        
        // Ensure price is ALWAYS set (required by database NOT NULL constraint)
        // Priority: use existing price if set, otherwise use unit_price, otherwise 0
        if ($price === null || $price === '') {
            $this->attributes['price'] = $baseUnitPrice;
            $this->price = $baseUnitPrice;
        } else {
            $this->attributes['price'] = $price;
            $this->price = $price;
        }
        
        // Ensure unit_price is set (check attributes first, then property)
        if (($unitPrice === null || $unitPrice === '') && ($price !== null && $price !== '')) {
            $this->attributes['unit_price'] = $price;
            $this->unit_price = $price;
        } elseif ($unitPrice === null || $unitPrice === '') {
            $finalPrice = $this->price ?? $baseUnitPrice;
            $this->attributes['unit_price'] = $finalPrice;
            $this->unit_price = $finalPrice;
        } else {
            $this->attributes['unit_price'] = $unitPrice;
            $this->unit_price = $unitPrice;
        }
        
        // Calculate total if quantity is set
        $quantity = $this->attributes['quantity'] ?? $this->quantity ?? null;
        if ($quantity !== null && $quantity > 0) {
            $basePrice = $this->unit_price ?? $this->price ?? 0;
            
            if ($basePrice > 0) {
                $calculatedTotal = $quantity * $basePrice;
                $this->attributes['total'] = $calculatedTotal;
                $this->total = $calculatedTotal;
                
                // Also set subtotal for backward compatibility
                if (($this->attributes['subtotal'] ?? $this->subtotal ?? null) === null) {
                    $this->attributes['subtotal'] = $calculatedTotal;
                    $this->subtotal = $calculatedTotal;
                }
            }
        }
        
        // Final safety check: ensure price is never null before saving
        if (!isset($this->attributes['price']) || $this->attributes['price'] === null) {
            $finalPrice = $this->unit_price ?? 0;
            $this->attributes['price'] = $finalPrice;
            $this->price = $finalPrice;
        }
        
        // Final safety check: ensure total is calculated if we have quantity
        if (($this->attributes['total'] ?? $this->total ?? null) === null && 
            ($quantity ?? $this->quantity ?? null) !== null && 
            ($quantity ?? $this->quantity ?? 0) > 0) {
            $basePrice = $this->unit_price ?? $this->price ?? 0;
            if ($basePrice > 0) {
                $calculatedTotal = ($quantity ?? $this->quantity) * $basePrice;
                $this->attributes['total'] = $calculatedTotal;
                $this->total = $calculatedTotal;
            }
        }
    }

    /**
     * Get the order that owns the order item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product for the order item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

