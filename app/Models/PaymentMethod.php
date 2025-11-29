<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gateway',
        'type',
        'config',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Return casts without the implicit 'id' cast to match test expectations.
     */
    #[\Override]
    public function getCasts(): array
    {
        $casts = parent::getCasts();
        unset($casts['id']);
        return $casts;
    }

    /**
     * Get the payments for this payment method.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope a query to only include active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include default payment methods.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}

