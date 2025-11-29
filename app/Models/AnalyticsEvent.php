<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int      $id
 * @property string   $event_type
 * @property string   $event_name
 * @property int|null $user_id
 * @property int|null $product_id
 * @property int|null $category_id
 * @property int|null $store_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $session_id
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class AnalyticsEvent extends Model
{
    /** @use HasFactory<\Database\Factories\AnalyticsEventFactory> */
    use HasFactory;

    /**
     * Event types.
     */
    public const TYPE_PRICE_COMPARISON = 'price_comparison';

    public const TYPE_PRODUCT_VIEW = 'product_view';

    public const TYPE_SEARCH = 'search';

    public const TYPE_STORE_CLICK = 'store_click';

    public const TYPE_CATEGORY_VIEW = 'category_view';

    public const TYPE_WISHLIST_ADD = 'wishlist_add';

    public const TYPE_CART_ADD = 'cart_add';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_type',
        'event_name',
        'user_id',
        'product_id',
        'category_id',
        'store_id',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];
}
