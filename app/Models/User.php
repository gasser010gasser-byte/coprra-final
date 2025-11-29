<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int                       $id
 * @property string                    $name
 * @property string                    $email
 * @property string                    $password
 * @property Carbon|null               $email_verified_at
 * @property bool                      $is_admin
 * @property bool                      $is_active
 * @property bool                      $is_blocked
 * @property string|null               $ban_reason
 * @property string|null               $ban_description
 * @property Carbon|null               $banned_at
 * @property Carbon|null               $ban_expires_at
 * @property string|null               $session_id
 * @property string                    $role
 * @property Collection<int, Review>   $reviews
 * @property Collection<int, Wishlist> $wishlists
 ** @property eAlert> $priceAlerts
 * @property UserLocaleSetting|null $localeSetting
 *
 * @method static \Illuminate\Database\Eloquent\Builder|User where(string $column, string|null $operator = null, scalar|array|null $value = null, string $boolean = 'and')
 * @method static UserFactory                                factory(...$parameters)
 *
 * @phpstan-type TFactory \Database\Factories\UserFactory
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<TFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * @var class-string<Factory<User>>
     */
    protected static $factory = UserFactory::class;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_active',
        'is_blocked',
        'ban_reason',
        'ban_description',
        'banned_at',
        'ban_expires_at',
        'banned_by',
        'unbanned_at',
        'unbanned_by',
        'session_id',
        'role',
        'permissions',
        'password_confirmed_at',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the reviews for the user.
     *
     * @return HasMany<Review, User>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Intentional PHPMD violation: ElseExpression.
     *
     * @return HasMany<Wishlist, User>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Wishlist relationship returning the actual products saved by the user.
     *
     * @return BelongsToMany<Product, User>
     */
    public function wishlist(): BelongsToMany
    {
        return $this
            ->belongsToMany(Product::class, 'wishlists')
            ->withTimestamps()
            ->withPivot(['id', 'notes', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return HasMany<PriceAlert, User>
     */
    public function priceAlerts(): HasMany
    {
        return $this->hasMany(PriceAlert::class);
    }

    /**
     * Get the orders for the user.
     *
     * @return HasMany<Order, User>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the locale setting for the user.
     *
     * @return HasOne<UserLocaleSetting, User>
     */
    public function localeSetting(): HasOne
    {
        return $this->hasOne(UserLocaleSetting::class);
    }

    /**
     * Get the points for the user.
     *
     * @return HasMany<UserPoint, User>
     */
    public function points(): HasMany
    {
        return $this->hasMany(UserPoint::class);
    }

    /**
     * Get the custom notifications for the user.
     *
     * @return HasMany<Notification, User>
     */
    public function customNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role || ('admin' === $role && $this->is_admin);
    }

    /**
     * Check if user is banned.
     */
    public function isBanned(): bool
    {
        return $this->is_blocked ?? false;
    }

    /**
     * Check if user's ban has expired.
     */
    public function isBanExpired(): bool
    {
        if (! $this->is_blocked) {
            return false;
        }

        return $this->ban_expires_at && Carbon::parse($this->ban_expires_at)->isPast();
    }

    /**
     * @return array<string>
     *
     * @psalm-return array{email_verified_at: 'datetime', password: 'hashed', is_admin: 'boolean', is_active: 'boolean', is_blocked: 'boolean', banned_at: 'datetime', ban_expires_at: 'datetime', password_confirmed_at: 'datetime'}
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'is_blocked' => 'boolean',
            'banned_at' => 'datetime',
            'ban_expires_at' => 'datetime',
            'permissions' => 'array',
            'password_confirmed_at' => 'datetime',
        ];
    }

    // No model-level phone sanitization to allow DB constraints to be tested
}
