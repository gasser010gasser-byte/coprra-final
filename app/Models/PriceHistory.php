<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PriceHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'price_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'price',
        'old_price',
        'currency',
        'recorded_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'old_price' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // Handle transition period where effective_date might still exist and be required
        static::saving(static function (self $priceHistory): void {
            // If recorded_at is set but effective_date column exists, copy the value
            // This handles the transition period during migration
            if ($priceHistory->recorded_at) {
                $tableName = $priceHistory->getTable();

                try {
                    if (Schema::hasColumn($tableName, 'effective_date')) {
                        // Directly set the attribute to be included in the insert
                        $priceHistory->attributes['effective_date'] = $priceHistory->recorded_at;
                    }
                } catch (\Exception $e) {
                    // If schema check fails, continue without setting effective_date
                }
            }
        });
    }
}
