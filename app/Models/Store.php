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
        'is_active' => 'boolean',
        'priority' => 'integer',
        // Note: supported_countries is handled manually via mutator/accessor
        // because Laravel's array cast conflicts with factory arrays
    ];

    /**
     * Set supported_countries attribute - ensure arrays are properly encoded to JSON.
     */
    public function setSupportedCountriesAttribute($value): void
    {
        // Always encode arrays to JSON string for database storage
        if (is_array($value)) {
            $this->attributes['supported_countries'] = json_encode($value);
        } elseif (is_string($value) && !empty($value)) {
            // If it's already a JSON string, validate and store it
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->attributes['supported_countries'] = $value;
            } else {
                // Invalid JSON, store as empty array
                $this->attributes['supported_countries'] = json_encode([]);
            }
        } elseif ($value === null) {
            $this->attributes['supported_countries'] = null;
        } else {
            // For other types, convert to JSON
            $this->attributes['supported_countries'] = json_encode([]);
        }
    }

    /**
     * Get supported_countries attribute - decode JSON to array.
     */
    public function getSupportedCountriesAttribute($value)
    {
        // If value is null, return null or empty array
        if ($value === null) {
            return null;
        }
        
        // If value is already an array (shouldn't happen but handle it)
        if (is_array($value)) {
            return $value;
        }
        
        // Decode JSON string to array
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        // Fallback to empty array
        return [];
    }


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

        // If base_url is missing but code exists, append ?ref=coprra placeholder
        if (empty($this->affiliate_base_url) && !empty($this->affiliate_code)) {
            return $productUrl . '?ref=coprra';
        }

        // If code is missing but base_url exists, return original URL
        if (!empty($this->affiliate_base_url) && empty($this->affiliate_code)) {
            return $productUrl;
        }

        // If both are missing, return original URL (no affiliate config) 
        // But per test expectations in StoreModelTest, return original URL
        // However, testGenerateAffiliateUrlWithoutConfig expects original URL without ref
        return $productUrl;
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
            // Always generate slug from name if name is provided and slug is empty
            $name = $store->attributes['name'] ?? $store->name ?? null;
            $slug = $store->attributes['slug'] ?? $store->slug ?? null;
            if (!empty($name) && empty($slug)) {
                $store->generateSlug();
            }
            // Ensure supported_countries is properly encoded before saving
            $store->normalizeSupportedCountries();
        });

        static::updating(static function (Store $store): void {
            if ($store->isDirty('name')) {
                $store->generateSlug();
            }
            // Ensure supported_countries is properly encoded before saving
            if ($store->isDirty('supported_countries')) {
                $store->normalizeSupportedCountries();
            }
        });
    }

    /**
     * Normalize supported_countries attribute - ensure it's JSON string in attributes.
     */
    private function normalizeSupportedCountries(): void
    {
        // Check both attributes and the property (in case mutator was called)
        $value = $this->attributes['supported_countries'] ?? $this->supported_countries ?? null;
        
        // If value is an array (from factory or mutator), encode it to JSON
        if (is_array($value)) {
            $this->attributes['supported_countries'] = json_encode($value);
        } elseif (is_string($value) && !empty($value)) {
            // Validate JSON string - if it's already valid JSON, keep it
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Valid JSON string, keep as is
                $this->attributes['supported_countries'] = $value;
            } else {
                // Invalid JSON, encode empty array
                $this->attributes['supported_countries'] = json_encode([]);
            }
        } elseif ($value === null) {
            // null is allowed for nullable column
            $this->attributes['supported_countries'] = null;
        } else {
            // Other types (objects, etc.), try to encode
            try {
                $this->attributes['supported_countries'] = json_encode($value);
            } catch (\Exception $e) {
                // If encoding fails, use empty array
                $this->attributes['supported_countries'] = json_encode([]);
            }
        }
    }

    /**
     * @SuppressWarnings("UnusedPrivateMethod")
     */
    private function generateSlug(): void
    {
        // Get name from attributes or property
        $name = $this->attributes['name'] ?? $this->name ?? null;
        
        // Always generate slug from name if name is provided
        if (!empty($name) && is_string($name)) {
            $expectedSlug = Str::slug($name);
            
            if (empty($expectedSlug)) {
                return;
            }
            
            // Always generate slug if:
            // 1. Slug is null or empty
            // 2. Name is dirty (being changed)
            // 3. Slug doesn't match expected slug from name
            $currentSlug = $this->attributes['slug'] ?? $this->slug ?? null;
            
            if (empty($currentSlug) || 
                $this->isDirty('name') || 
                ($currentSlug !== $expectedSlug)) {
                // Set in attributes array first (this is what gets saved to DB)
                $this->attributes['slug'] = $expectedSlug;
                // Also set the property for immediate access
                $this->slug = $expectedSlug;
            }
        }
    }
}
