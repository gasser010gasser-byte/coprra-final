<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPurchase extends Model
{
    use HasFactory;

    protected $table = 'user_purchases';

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'purchased_at',
        'quantity',
        'price',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    /**
     * Get the user that made the purchase.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that was purchased.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

