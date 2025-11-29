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
     * Boot the model.
     */
    #[\Override]
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function (Brand $brand): void {
            // Always generate slug from name if name is provided and slug is not set
            // Try multiple ways to access name attribute
            $name = $brand->name ?? $brand->attributes['name'] ?? null;
            $slug = $brand->slug ?? $brand->attributes['slug'] ?? null;
            if (!empty($name) && empty($slug)) {
                $brand->generateSlug();
            }
        });

        static::updating(static function (Brand $brand): void {
            // Generate slug if name changed or if slug is being set to null/empty
            if ($brand->isDirty('name') || 
                ($brand->isDirty('slug') && (empty($brand->slug) || $brand->slug === null))) {
                $brand->generateSlug();
            }
        });
    }

    /**
     * @SuppressWarnings("UnusedPrivateMethod")
     */
    private function generateSlug(): void
    {
        // Try multiple ways to access name attribute
        $name = $this->name ?? $this->attributes['name'] ?? null;

        // Always generate slug from name if name is provided
        if (!empty($name) && is_string($name)) {
            $expectedSlug = str($name)->slug()->toString();

            if (empty($expectedSlug)) {
                return;
            }

            // Always generate slug if:
            // 1. Slug is null or empty
            // 2. Name is dirty (being changed)
            // 3. Slug doesn't match expected slug from name
            $currentSlug = $this->slug ?? $this->attributes['slug'] ?? null;

            if (empty($currentSlug) || $currentSlug === '' ||
                $this->isDirty('name') ||
                ($currentSlug !== $expectedSlug)) {
                // Set in attributes array (this is what gets saved to DB)
                $this->attributes['slug'] = $expectedSlug;
                // Also set the property for immediate access
                $this->slug = $expectedSlug;
            }
        }
    }
}
