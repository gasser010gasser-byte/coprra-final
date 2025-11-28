<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $code
 ** @property $name
 * @property string                    $symbol
 * @property Collection<int, Store>    $stores
 * @property Collection<int, Language> $languages
 *
 * @method static CurrencyFactory factory(...$parameters)
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Currency extends Model
{
    use HasFactory;

    /**
     * @var class-string<Factory<Currency>>
     */
    protected static $factory = CurrencyFactory::class;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    // --- Relationships ---

    /**
     * Get the stores for the currency.
     */
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Get the languages that use this currency.
     */
    public function languages()
    {
        return $this->belongsToMany(Language::class, 'currency_language');
    }
}
