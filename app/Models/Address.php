<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'street',
        'line1',
        'city',
        'state',
        'zip_code',
        'country',
        'is_default',
    ];

    /**
     * Get line1 attribute (alias for street).
     */
    public function getLine1Attribute(): ?string
    {
        return $this->street;
    }

    /**
     * Set line1 attribute (alias for street).
     */
    public function setLine1Attribute(?string $value): void
    {
        // Only set street if line1 is not null (street is required NOT NULL)
        if ($value !== null) {
            $this->street = $value;
        }
    }

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the user that owns the address.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
