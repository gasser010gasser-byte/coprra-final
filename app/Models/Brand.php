<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\MessageBag;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $description
 * @property string|null $logo_url
 * @property string|null $website_url
 * @property bool        $is_active
 ** @property Carbon|nullCarbon|null $created_at
 ** @property Carbon|nullCarbon|null $updated_at
 ** @property Carbon|nullCarbon|null $deleted_at
 * @property int                      $products_count
 * @property Collection<int, Product> $products
 *
 * @method static \App\Models\Brand create(array<string, string|bool* |* @method static \App\Models\Brand create(array<string, string|bool|null> $attributes = [])
 * @method static BrandFactory      factory(...$parameters)
 *
 * @phpstan-type TFactory \Database\Factories\BrandFactory
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Brand extends ValidatableModel
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var class-string<Factory<Brand>>
     */
    protected static $factory = BrandFactory::class;

    /**
     * Validation errors.
     */
    protected ?MessageBag $errors = null;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'website_url',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be validated.
     *
     * @var array<string, string>
     */
    protected array $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'nullable|string|max:255|unique:brands,slug',
        'description' => 'nullable|string|max:1000',
        'logo_url' => 'nullable|url|max:500',
        'website_url' => 'nullable|url|max:500',
        'is_active' => 'boolean',
    ];

    // --- Relationships ---

    /**
     * Get the products for this brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // --- Scopes ---

    /**
     * Scope a query to only include active brands.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search brands by name.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%{$search}%");
    }

    // --- Methods ---

    /**
     * Get the validation rules for this model.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Set the name attribute - auto-generate slug if slug is not set or name is dirty.
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;

        // Auto-generate slug if:
        // 1. Slug is not explicitly set, OR
        // 2. Name is being changed (dirty)
        $slug = $this->attributes['slug'] ?? null;
        $nameChanged = $this->isDirty('name') || ! isset($this->original['name']) || ($this->original['name'] ?? null) !== $value;

        if ((empty($slug) || $nameChanged) && ! empty($value) && is_string($value)) {
            $generatedSlug = str($value)->slug()->toString();
            if (! empty($generatedSlug)) {
                $this->attributes['slug'] = $generatedSlug;
            }
        }
    }

    /**
     * Set the slug attribute - auto-generate from name if slug is set to null/empty.
     */
    public function setSlugAttribute($value): void
    {
        // If slug is explicitly provided (and not null/empty), use it
        if (! empty($value) && $value !== null && $value !== '') {
            $this->attributes['slug'] = $value;

            return;
        }

        // Otherwise, generate from name if name is available
        $name = $this->attributes['name'] ?? $this->name ?? null;
        if (! empty($name) && is_string($name)) {
            $generatedSlug = str($name)->slug()->toString();
            if (! empty($generatedSlug)) {
                $this->attributes['slug'] = $generatedSlug;

                return;
            }
        }

        // If no name or slug generation failed, set to null (for nullable column)
        $this->attributes['slug'] = null;
    }

    /**
     * Boot the model.
     */
    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function (Brand $brand): void {
            // Slug generation is handled by setNameAttribute mutator
            // This event is kept for any future logic
        });

        static::updating(static function (Brand $brand): void {
            // Slug generation is handled by setNameAttribute mutator
            // This event is kept for any future logic
        });
    }
}
