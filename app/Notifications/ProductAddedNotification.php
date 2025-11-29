<?php

/** @psalm-suppress UnusedClass */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductAddedNotification extends Notification
{
    use Queueable;

    protected Product $product;

    /**
     * Create a new notification instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<int|string>
     *
     * @psalm-return array{product_id: int, product_name: string}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
        ];
    }
}
