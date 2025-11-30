<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property string      $from_currency
 * @property string      $to_currency
 * @property float       $rate          * @property string $source
 * @property Carbon|null $fetched_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class ExchangeRate extends Model
{
    /** @use HasFactory<\Database\Factories\ExchangeRateFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'source',
        'fetched_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rate' => 'decimal:10',
        'fetched_at' => 'datetime',
    ];

    /**
     * Get exchange rate for a currency pair.
     */
    public static function getRate(string $fromCurrency, string $toCurrency): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $rate = self::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->latest('updated_at')
            ->first()
        ;

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Check if exchange rate is stale (older than 24 hours).
     */
    public function isStale(): bool
    {
        if (! $this->fetched_at) {
            return true;
        }

        return $this->fetched_at->diffInHours(now()) > 24;
    }

    /**
     * Scope to get stale exchange rates.
     */
    public function scopeStale($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('fetched_at')
                ->orWhere('fetched_at', '<', now()->subHours(24));
        });
    }
}
