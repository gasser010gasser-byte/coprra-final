<?php

/** @psalm-suppress UnusedClass */

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PriceAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
     * @return array{product_id?: int, current_price: float, target_price: float}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->alert->product_id,
            'current_price' => $this->currentPrice,
            'target_price' => (float) $this->alert->target_price,
        ];
    }
}
