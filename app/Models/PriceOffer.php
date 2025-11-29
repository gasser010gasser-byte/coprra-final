<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PriceOfferFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $product_id
 * @property int         $store_id
 * @property float       $price
 * @property string|null $product_url
 ** @property bool $in_stock
 * @property Product $product
 * @property Store   $store
 *
 * @method static PriceOfferFactory factory(...$parameters)
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class PriceOffer extends Model
{
    use HasFactory;

    /**
     * @var class-string<Factory<PriceOffer>>
     */
    protected static $factory = PriceOfferFactory::class;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'product_sku',
        'store_id',
        'price',
        'currency',
        'product_url',
        'affiliate_url',
        'in_stock',
        'stock_quantity',
        'condition',
        'rating',
        'reviews_count',
        'image_url',
        'specifications',
        'is_available',
        'original_price',
        'description',
        'expires_at',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'specifications' => 'array',
        'in_stock' => 'boolean',
        'is_available' => 'boolean',
        'rating' => 'decimal:1',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
    ];

    /**
     * Get the store that owns the price offer.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the product that owns the price offer.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
