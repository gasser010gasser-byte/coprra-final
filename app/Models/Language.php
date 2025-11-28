<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property string $direction
 ** @property bool $is_active
 * @property int                                $sofinal            rt_order
 * @property Collection<int, Currency>          $currencies
 * @property Collection<int, UserLocaleSetting> $userLocaleSettings
 *
 * @method static LanguageFactory factory(...$parameters)
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Language extends Model
{
    use HasFactory;

    /**
     * @var class-string<Factory<Language>>
     */
    protected static $factory = LanguageFactory::class;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Return only explicit casts defined on the model.
     * This excludes framework-added defaults like the primary key cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    public function getCasts(): array
    {
        return $this->casts;
    }

    // --- Relationships ---

    /**
     * Get the currencies for this language.
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, 'language_currency');
    }

    /**
     * Get the user locale settings for this language.
     */
    public function userLocaleSettings(): HasMany
    {
        return $this->hasMany(UserLocaleSetting::class);
    }

    // --- Scopes ---

    /**
     * Scope a query to only include active languages.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order languages by sort_order and name.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    // --- Methods ---

    /**
     * Check if the language is right-to-left.
     */
    public function isRtl(): bool
    {
        return 'rtl' === $this->direction;
    }

    /**
     * Get the default currency for this language.
     */
    public function defaultCurrency(): ?Currency
    {
        return $this->currencies()->where('is_default', true)->first();
    }
}
