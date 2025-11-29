<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $description
 * @property int|null    $parent_id
 * @property int         $level
 * @property bool        $is_active
 ** @property Carbon|nullCarbon|null $created_at
 ** @property Carbon|nullCarbon|null $updated_at
 ** @property Carbon|nullCarbon|null $deleted_at
 * @property Category|null             $parent
 * @property Collection<int, Category> $childfinal ren
 **  @property Collection<int, Product> $products
 *
 * @method static \App\Models\Category create(array<string, string|int|bool|* @method static \App\Models\Brand create(array<string, string|bool|null> $attributes = [])
 * @method static CategoryFactory      factory(...$parameters)
 *
 * @phpstan-type TFactory \Database\Factories\CategoryFactory
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Category extends ValidatableModel
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var class-string<Factory<Category>>
     */
    protected static $factory = CategoryFactory::class;

    // Use $errors from ValidatableModel (MessageBag). Do not redeclare here.

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'level',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    /**
     * The attributes that should be validated.
     *
     * @var array<string, string>
     */
    protected array $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'nullable|string|max:255|unique:categories,slug',
        'description' => 'nullable|string|max:1000',
        'parent_id' => 'nullable|exists:categories,id',
        'level' => 'integer|min:0',
        'is_active' => 'boolean',
    ];

    // --- Relationships ---

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the products for the category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // --- Scopes ---

    /**
     * Scope a query to only include active categories.
     *
     * @param mixed $query
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search categories by name.
     *
     * @param mixed $query
     * @param mixed $term
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'like', '%'.$term.'%');
    }

    // --- Methods ---

    /**
     * Get the validation rules for the model.
     */
    public function getRules()
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

        static::creating(/**
         * @return true
         */
            static fn (Category $category): bool => $category->handleCreatingEvent()
        );

        static::updating(/**
         * @return true
         */
            static fn (Category $category): bool => $category->handleUpdatingEvent()
        );
    }

    private function handleCreatingEvent(): bool
    {
        // Always generate slug from name if name is provided and slug is empty
        $name = $this->getAttribute('name') ?? $this->attributes['name'] ?? $this->name ?? null;
        $slug = $this->getAttribute('slug') ?? $this->attributes['slug'] ?? $this->slug ?? null;
        if (!empty($name) && (empty($slug) || $slug === '')) {
            $this->generateSlug();
        }
        // Calculate level based on parent or set default
        $parentId = $this->getAttribute('parent_id') ?? $this->attributes['parent_id'] ?? $this->parent_id ?? null;
        $level = $this->getAttribute('level') ?? $this->attributes['level'] ?? $this->level ?? null;
        if (null !== $parentId || null === $level) {
            $this->calculateLevel();
        }

        return true;
    }

    private function handleUpdatingEvent(): bool
    {
        if ($this->isDirty('name')) {
            $this->generateSlug();
        }

        if ($this->isDirty('parent_id')) {
            $this->calculateLevel();
        }

        return true;
    }

    private function generateSlug(): void
    {
        // Get name from attributes or property
        $name = $this->getAttribute('name') ?? $this->attributes['name'] ?? $this->name ?? null;
        
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
            $currentSlug = $this->getAttribute('slug') ?? $this->attributes['slug'] ?? $this->slug ?? null;
            
            if (empty($currentSlug) || $currentSlug === '' || 
                $this->isDirty('name') || 
                ($currentSlug !== $expectedSlug)) {
                // Set in attributes array first (this is what gets saved to DB)
                $this->attributes['slug'] = $expectedSlug;
                // Also set the property for immediate access
                $this->slug = $expectedSlug;
                // Use setAttribute to ensure it's properly set
                $this->setAttribute('slug', $expectedSlug);
            }
        }
    }

    private function calculateLevel(): void
    {
        // Get parent_id from attributes or property
        $parentId = $this->attributes['parent_id'] ?? $this->parent_id ?? null;
        
        // Recalculate level based on parent when applicable
        if (null !== $parentId) {
            // Query the database directly to get parent level
            // Don't use relationship loading during creating event as it may fail
            $parent = self::find($parentId);
            
            // If parent exists, calculate level based on parent's level
            if ($parent) {
                $calculatedLevel = (int) $parent->level + 1;
                $this->attributes['level'] = $calculatedLevel;
                $this->level = $calculatedLevel;
            } else {
                // Parent doesn't exist yet, set to 0
                $this->attributes['level'] = 0;
                $this->level = 0;
            }

            return;
        }

        // No parent: set default only if not explicitly provided
        $currentLevel = $this->attributes['level'] ?? $this->level ?? null;
        if (null === $currentLevel) {
            $this->attributes['level'] = 0;
            $this->level = 0;
        }
    }

    /**
     * Use slug for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
