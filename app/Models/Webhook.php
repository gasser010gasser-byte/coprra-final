<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int      $id
 * @property string   $store_identifier
 * @property string   $event_type
 * @property string   $product_identifier
 * @property int|null $product_id
 * @property array<string, string|int|float|bool|* @method static \App\Models\Brand create(array<string, string|bool|null> $payload
 * @property string|null $signature
 * @property string      $status
 ** @property |null $error_message
 ** @property Carbon|nullCarbon|null $processed_at
 ** @property Carbon|nullCarbon $created_at
 ** @property Carbon|nullCarbon $updated_at
 */
class Webhook extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookFactory> */
    use HasFactory;

    /**
     * Event types.
     */
    public const EVENT_PRICE_UPDATE = 'price_update';

    public const EVENT_STOCK_UPDATE = 'stock_update';

    public const EVENT_PRODUCT_UPDATE = 'product_update';

    /**
     * Status values.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'store_identifier',
        'event_type',
        'product_identifier',
        'product_id',
        'payload',
        'signature',
        'status',
        'error_message',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the logs for the webhook.
     *
     * @return HasMany<WebhookLog, Webhook>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }

    /**
     * Mark webhook as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark webhook as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark webhook as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);
    }

    /**
     * Add log entry.
     *
     * @param  array<string, string|int|bool|* @method static \App\Models\Brand create(array<string, string|bool|null>|null  $metadata
     */
    public function addLog(string $action, string $message, ?array $metadata = null): void
    {
        $this->logs()->create([
            'action' => $action,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Scope a query to only include pending webhooks.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by store identifier.
     */
    public function scopeStore(Builder $query, string $storeIdentifier): Builder
    {
        return $query->where('store_identifier', $storeIdentifier);
    }

    /**
     * Scope a query to filter by event type.
     */
    public function scopeEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }
}
