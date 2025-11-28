<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;

/**
 * @property int                $id
 * @property string             $name
 * @property string             $slug
 * @property string|null        $description
 * @property string|null        $logo_url
 * @property string|null        $website_url
 * @property string|null        $country_code
 * @property array<string>|null $supported_countries
 * @property bool               $is_active
 * @property int                $priority
 * @property string|null        $affiliate_base_url
 * @property string|null        $affiliate_code
 * @property array<string, string|* @method static \App\Models\Brand create(array<string, string|bool|null>|null $api_config
 * @property int|null $currency_id
 ** @property Carbon|nullCarbon|null $created_at
 ** @property Carbon|nullCarbon|null $updated_at
 ** @property Carbon|nullCarbon|null $deleted_at
 * @property int $price_offers_count
 ** @property $priceOffers
 * @property Collection<int, Product> $products
 * @property Currency|null            $currency
 *
 * @method static StoreFactory factory(...$parameters)
 *
 * @phpstan-type TFactory \Database\Factories\StoreFactory
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Store extends ValidatableModel
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var class-string<Factory<Store>>
     */
    protected static $factory = StoreFactory::class;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'website_url',
        'country_code',
        'supported_countries',
        'is_active',
        'priority',
        'affiliate_base_url',
        'affiliate_code',
        'api_config',
        'currency_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'api_config' => 'array',
        'supported_countries' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected ?MessageBag $errors = null;

    /**
     * The attributes that should be validated.
     *
     * @var array<string, string>
     */
    protected array $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'nullable|string|max:255|unique:stores,slug',
        'description' => 'nullable|string|max:1000',
        'logo_url' => 'nullable|url|max:500',
        'website_url' => 'nullable|url|max:500',
        'country_code' => 'nullable|string|max:2',
        'supported_countries' => 'nullable|array',
        'is_active' => 'boolean',
        'priority' => 'integer|min:0',
        'affiliate_base_url' => 'nullable|url|max:500',
        'affiliate_code' => 'nullable|string|max:100',
        'api_config' => 'nullable|array',
        'currency_id' => 'nullable|exists:currencies,id',
    ];

    // --- Relationships ---

    /**
     * Get the price offers for this store.
     */
    public function priceOffers(): HasMany
    {
        return $this->hasMany(PriceOffer::class);
    }

    /**
     * Get the products for this store.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the currency for this store.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // --- Scopes ---

    /**
     * Scope a query to only include active stores.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search stores by name.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%{$search}%");
    }

    // --- Methods ---

    /**
     * Generate affiliate URL for a product URL.
     *
     * Currently uses placeholder mechanism (?ref=coprra) until real affiliate links are available.
     */
    public function generateAffiliateUrl(string $productUrl): string
    {
        // If real affiliate configuration exists, use it
        if (! empty($this->affiliate_base_url) && ! empty($this->affiliate_code)) {
            $encodedUrl = str_replace(':', '%3A', $productUrl);

            return str_replace(
                ['{AFFILIATE_CODE}', '{URL}'],
                [$this->affiliate_code, $encodedUrl],
                $this->affiliate_base_url
            );
        }

        // Placeholder solution: append ?ref=coprra parameter
        // Check if URL already has query parameters
        $separator = strpos($productUrl, '?') !== false ? '&' : '?';

        return $productUrl . $separator . 'ref=coprra';
    }

    /**
     * Get the validation rules for this model.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Boot the model.
     */
    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function (Store $store): void {
            $store->generateSlug();
        });

        static::updating(static function (Store $store): void {
            if ($store->isDirty('name')) {
                $store->generateSlug();
            }
        });
    }

    /**
     * @SuppressWarnings("UnusedPrivateMethod")
     */
    private function generateSlug(): void
    {
        $this->slug = Str::slug($this->name);
    }
}
