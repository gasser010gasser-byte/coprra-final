<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\MessageBag;

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

        // Register events directly in boot for testing compatibility
        static::creating(function (Category $category) {
            // Generate slug if not set
            if (empty($category->slug) && ! empty($category->name)) {
                $baseSlug = \Illuminate\Support\Str::slug($category->name);
                $slug = $baseSlug;
                $count = 1;

                // Ensure slug is unique
                while (static::where('slug', $slug)->where('id', '!=', $category->id ?? 0)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    ++$count;
                }

                $category->slug = $slug;
            }

            // Set level if not set
            if (is_null($category->level)) {
                if ($category->parent_id) {
                    $parent = static::find($category->parent_id);
                    $category->level = $parent ? ($parent->level + 1) : 0;
                } else {
                    $category->level = 0;
                }
            }
        });

        static::updating(function (Category $category) {
            // Update slug if name changed
            if ($category->isDirty('name') && ! empty($category->name)) {
                $baseSlug = \Illuminate\Support\Str::slug($category->name);
                $slug = $baseSlug;
                $count = 1;

                // Ensure slug is unique
                while (static::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    ++$count;
                }

                $category->slug = $slug;
            }

            // Update level if parent changed
            if ($category->isDirty('parent_id')) {
                if ($category->parent_id) {
                    $parent = static::find($category->parent_id);
                    $category->level = $parent ? ($parent->level + 1) : 0;
                } else {
                    $category->level = 0;
                }
            }
        });
    }

    /**
     * Use slug for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
