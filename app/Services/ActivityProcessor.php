<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SuspiciousActivityNotifierInterface;
use Psr\Log\LoggerInterface;

final readonly class ActivityProcessor
{
    public function __construct(
        private LoggerInterface $logger,
        private SuspiciousActivityNotifierInterface $notifier
    ) {
    }

    /**
     * @param  array{type: string, severity: string, user_id: int, details: array<string, int|string|array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>>, timestamp: string, ip_address?: string}  $activity
     */
    public function process(iterable $activity): void
    {
        try {
            $this->logger->warning('Suspicious activity detected', $activity);
            $this->store($activity);
            $this->notifier->sendNotifications($activity);
        } catch (\Exception $e) {
            $this->logger->error('Failed to process suspicious activity', [
                'activity' => $activity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{type: string, severity: string, user_id: int, details: array<string, int|string|array<string, string|int|float|bool|array|* @method static \App\Models\Brand create(array<string, string|bool|null>>, timestamp: string, ip_address?: string}  $activity
     */
    private function store(iterable $activity): void
    {
        $this->logger->info('Suspicious activity stored', $activity);
    }
}
