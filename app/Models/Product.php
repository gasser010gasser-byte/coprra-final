<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * @property int                                                       $id
 * @property string                                                    $name
 * @property string                                                    $slug
 * @property string                                                    $description
 * @property string                                                    $price
 * @property string                                                    $sku
 * @property string|null                                               $image
 * @property bool                                                      $is_active
 * @property int                                                       $stock_quantity
 * @property int                                                       $category_id
 * @property int                                                       $brand_id
 * @property int                                                       $purchase_count
 * @property int|null                                                  $year_of_manufacture
 * @property array|null                                                $available_colors
 * @property Category                                                  $category
 * @property Brand                                                     $brand
 * @property \Illuminate\Database\Eloquent\Collection<int, PriceAlert> $priceAlerts
 * @property \Illuminate\Database\Eloquent\Collection<int, Review>     $reviews
 * @property \Illuminate\Database\Eloquent\Collection<int, Wishlist>   $wishlists
 * @property \Illuminate\Database\Eloquent\Collection<int, PriceOffer> $priceOffers
 *
 * @method static ProductFactory factory(...$parameters)
 *
 * @phpstan-type TFactory \Database\Factories\ProductFactory
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class Product extends Model
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var class-string<Factory<Product>>
     */
    protected static $factory = ProductFactory::class;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'image',
        'image_url',
        'source_url',
        'is_active',
        'stock_quantity',
        'category_id',
        'brand_id',
        'store_id',
        'year_of_manufacture',
        'available_colors',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'stock_quantity' => 'integer',
        'year_of_manufacture' => 'integer',
        'available_colors' => 'array',
        'store_mappings' => 'array',
    ];

    /**
     * @var array<string, string>|null
     */
    protected ?array $errors = null;

    // --- قواعد التحقق ---
    /**
     * @var array<string, string>
     */
    protected $rules = [
        'name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'brand_id' => 'required|integer',
        'category_id' => 'required|integer',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @param  array<string, string|int|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>  $state
     */
    public static function factory(?int $count = null, array $state = []): ProductFactory
    {
        $factory = static::newFactory();
        if ($factory && null !== $count) {
            $factory = $factory->count($count);
        }

        // Use the application's default database connection during testing
        // to keep reads and writes in the same database.
        return $factory ? $factory->state($state) : ProductFactory::new();
    }

    // --- العلاقات ---

    /**
     * @return HasMany<PriceAlert, Product>
     */
    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class);
    }

    /**
     * @return HasMany<Review, Product>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<Wishlist, Product>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Users who have saved the product to their wishlist.
     *
     * @return BelongsToMany<User, Product>
     */
    public function wishlistUsers(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'wishlists')
            ->withTimestamps()
            ->withPivot(['id', 'notes', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<PriceOffer, Product>
     */
    public function priceOffers(): HasMany
    {
        return $this->hasMany(PriceOffer::class);
    }

    /**
     * @return HasMany<OrderItem, Product>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return BelongsTo<Category, Product>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, Product>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Store, Product>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<PriceHistory, Product>
     */
    public function priceHistory(): HasMany
    {
        // Don't add orderBy here as it can cause issues with queries
        // Ordering should be done in the specific queries that need it
        return $this->hasMany(PriceHistory::class, 'product_id');
    }

    // --- Scopes ---

    /**
     * Scope a query to only include active products.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search products by name.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where('name', 'like', '%'.$term.'%');
    }

    /**
     * Scope a query to include reviews count.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopeWithReviewsCount($query)
    {
        return $query;
    }

    // --- Accessors & Mutators ---

    /**
     * Get the product image URL.
     * Falls back to image_url if image is not set.
     *
     * @return string|null
     */
    public function getImageAttribute(?string $value): ?string
    {
        // If image column has value, use it
        if ($value) {
            return $value;
        }

        // Otherwise fall back to image_url column
        return $this->getAttributeFromArray('image_url');
    }

    // --- طرق مساعدة ---

    /**
     * Get the current price of the product.
     */
    public function getCurrentPrice(): float
    {
        $availableOffer = $this->priceOffers()
            ->where('is_available', true)
            ->orderBy('price', 'asc')
            ->first()
        ;

        return $availableOffer ? (float) $availableOffer->price : (float) $this->price;
    }

    /**
     * Get the price history for this product.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PriceOffer>
     */
    public function getPriceHistory()
    {
        return $this->priceOffers()->orderBy('price', 'asc')->get();
    }

    /**
     * Get the total number of reviews for this product.
     */
    public function getTotalReviews(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Validate the product data.
     */
    public function validate(): bool
    {
        $validator = \Validator::make($this->toArray(), $this->rules());

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();

            return false;
        }

        $this->errors = [];

        return true;
    }

    /**
     * Get validation errors.
     */
    public function getErrors()
    {
        return collect($this->errors ?? []);
    }

    /**
     * Check if product is in user's wishlist.
     */
    public function isInWishlist(int $userId): bool
    {
        // Use the relation which automatically handles soft deletes
        return $this->wishlists()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get validation rules.
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Get average rating for the product.
     */
    public function getAverageRating(): float
    {
        return (float) $this->reviews()->avg('rating') ?: 0.0;
    }

    /**
     * Check if product has significant price change.
     *
     * @param float $threshold Percentage threshold (e.g., 10 for 10%)
     *
     * @return bool True if price change exceeds threshold
     */
    public function hasSignificantPriceChange(float $threshold = 10.0): bool
    {
        $currentPrice = (float) $this->price;
        if ($currentPrice <= 0) {
            return false;
        }

        // Get the oldest price from price history
        $oldestPriceHistory = $this->priceHistory()->orderBy('recorded_at', 'asc')->first();
        if (!$oldestPriceHistory) {
            return false;
        }

        $oldPrice = (float) $oldestPriceHistory->price;
        if ($oldPrice <= 0) {
            return false;
        }

        $changePercentage = abs(($currentPrice - $oldPrice) / $oldPrice * 100);

        return $changePercentage >= $threshold;
    }

    /**
     * Get formatted year display.
     */
    public function getFormattedYearAttribute(): ?string
    {
        return $this->year_of_manufacture ? (string) $this->year_of_manufacture : null;
    }

    /**
     * Get color list as comma-separated string.
     */
    public function getColorListAttribute(): string
    {
        return $this->available_colors && is_array($this->available_colors)
            ? implode(', ', $this->available_colors)
            : 'N/A';
    }

    #[\Override]
    protected static function booted(): void
    {
        parent::booted();

        // Ensure no stray attributes like 'quantity' are persisted on save
        static::saving(static function (self $product): void {
            // Remove accidental 'quantity' attribute that may be set by factories or external code
            if (isset($product->attributes['quantity'])) {
                unset($product->attributes['quantity']);
            }
        });

        // Record initial price on creation
        static::created(static function (self $product): void {
            try {
                // Only create if price exists and is valid
                $price = $product->price;
                $priceFloat = $price !== null && $price !== '' ? (float) $price : null;
                
                if ($priceFloat !== null && $priceFloat > 0) {
                    PriceHistory::create([
                        'product_id' => $product->id,
                        'price' => $priceFloat,
                        'old_price' => null,
                        'currency' => 'USD',
                        'recorded_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Log error but don't fail the creation
                if (app()->bound('log')) {
                    \Log::warning('Failed to create initial price history', [
                        'product_id' => $product->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        });

        // Record price change on update when price actually changes
        static::updated(static function (self $product): void {
            try {
                // Check if price was changed by comparing original and current values
                $oldPrice = $product->getOriginal('price');
                $newPrice = $product->price;
                
                // Convert to float for comparison
                $oldPriceFloat = $oldPrice !== null && $oldPrice !== '' ? (float) $oldPrice : null;
                $newPriceFloat = $newPrice !== null && $newPrice !== '' ? (float) $newPrice : null;
                
                // Create price history if price actually changed and new price is valid
                if ($product->wasChanged('price') && $newPriceFloat !== null && $newPriceFloat > 0 && $oldPriceFloat !== $newPriceFloat) {
                    PriceHistory::create([
                        'product_id' => $product->id,
                        'price' => $newPriceFloat,
                        'old_price' => $oldPriceFloat,
                        'currency' => 'USD',
                        'recorded_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Log error but don't fail the update
                if (app()->bound('log')) {
                    \Log::warning('Failed to create price history on update', [
                        'product_id' => $product->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        });

        static::updating(static function (self $product): void {
            $product->clearProductCachesOnUpdate();
        });

        static::deleting(static function (self $product): void {
            $product->deleteRelatedRecords();
            $product->clearProductCachesOnDelete();
        });
    }

    /**
     * @SuppressWarnings("UnusedPrivateMethod")
     */
    private function clearProductCachesOnUpdate(): void
    {
        Cache::forget("product_{$this->id}_avg_rating");
        Cache::forget("product_{$this->id}_total_reviews");
        Cache::forget("product_{$this->id}_current_price");

        /** @var Collection<int, int> $wishlists */
        $wishlists = $this->wishlists()->pluck('user_id');
        foreach ($wishlists as $userId) {
            Cache::forget("product_{$this->id}_wishlist_user_{$userId}");
        }
    }

    /**
     * @SuppressWarnings("UnusedPrivateMethod")
     */
    private function deleteRelatedRecords(): void
    {
        $this->priceOffers()->forceDelete();
        $this->reviews()->forceDelete();
        $this->wishlists()->forceDelete();
        $this->priceAlerts()->forceDelete();
    }

    /**
     * @SuppressWarnings("UnusedPrivateMethod")
     */
    private function clearProductCachesOnDelete(): void
    {
        Cache::forget("product_{$this->id}_avg_rating");
        Cache::forget("product_{$this->id}_total_reviews");
        Cache::forget("product_{$this->id}_current_price");
    }
}
