<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Events\AI\AgentLifecycleEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service for monitoring agent health and heartbeats.
 */
class AgentHealthService
{
    public function __construct(
        private readonly AgentRegistryService $registry
    ) {
    }

    /**
     * Record agent heartbeat to indicate it's alive.
     *
     * @param array<string, mixed> $metrics
     */
    public function recordHeartbeat(string $agentId, array $metrics = []): void
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state) {
            return;
        }

        $healthScore = $this->calculateHealthScore($metrics);

        $this->registry->updateAgentState($agentId, [
            'last_heartbeat' => Carbon::now()->toISOString(),
            'metrics' => $metrics,
            'health_score' => $healthScore,
        ]);
    }

    /**
     * Get comprehensive health status for all agents.
     *
     * @return array<string, mixed>
     */
    public function getAgentHealthStatus(): array
    {
        $agents = $this->registry->getAgentStates();

        $healthStatus = [
            'overall_health' => 'healthy',
            'total_agents' => \count($agents),
            'active_agents' => 0,
            'paused_agents' => 0,
            'failed_agents' => 0,
            'agents' => [],
        ];

        foreach ($agents as $agentId => $agent) {
            $isHealthy = $this->isAgentHealthy($agentId);

            $healthStatus['agents'][$agentId] = [
                'id' => $agentId,
                'type' => $agent['type'],
                'status' => $agent['status'],
                'health_score' => $agent['health_score'],
                'is_healthy' => $isHealthy,
                'last_heartbeat' => $agent['last_heartbeat'],
                'uptime' => $this->calculateUptime($agent),
                'error_count' => $agent['error_count'] ?? 0,
                'restart_count' => $agent['restart_count'] ?? 0,
            ];

            switch ($agent['status']) {
                case 'active':
                    $healthStatus['active_agents']++;

                    break;

                case 'paused':
                    $healthStatus['paused_agents']++;

                    break;

                case 'failed':
                    $healthStatus['failed_agents']++;

                    break;
            }
        }

        if ($healthStatus['failed_agents'] > 0) {
            $healthStatus['overall_health'] = 'degraded';
        }

        if ($healthStatus['failed_agents'] > $healthStatus['active_agents']) {
            $healthStatus['overall_health'] = 'unhealthy';
        }

        return $healthStatus;
    }

    /**
     * Mark an agent as failed.
     */
    public function markAgentAsFailed(string $agentId, string $reason = 'unknown'): void
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state) {
            Log::warning('⚠️ Attempted to mark non-existent agent as failed', [
                'agent_id' => $agentId,
                'reason' => $reason,
            ]);

            return;
        }

        $previousStatus = $state['status'];
        $failureCount = ($state['failure_count'] ?? 0) + 1;

        $this->registry->updateAgentState($agentId, [
            'status' => 'failed',
            'failed_at' => Carbon::now()->toISOString(),
            'failure_reason' => $reason,
            'failure_count' => $failureCount,
        ]);

        Log::error('💥 Agent marked as failed', [
            'agent_id' => $agentId,
            'reason' => $reason,
            'failure_count' => $failureCount,
        ]);

        event(new AgentLifecycleEvent(
            $agentId,
            'failed',
            $previousStatus,
            'failed',
            [
                'error' => $reason,
                'failure_count' => $failureCount,
                'auto_recovery' => true,
            ]
        ));
    }

    /**
     * Detect and handle state corruption.
     */
    public function detectStateCorruption(string $agentId): bool
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state) {
            return false;
        }

        $corruptionDetected = false;
        $corruptionReasons = [];

        if (isset($state['last_heartbeat'], $state['registered_at'])) {
            try {
                $lastHeartbeat = Carbon::parse($state['last_heartbeat']);
                $registeredAt = Carbon::parse($state['registered_at']);

                if ($lastHeartbeat->lt($registeredAt)) {
                    $corruptionDetected = true;
                    $corruptionReasons[] = 'last_heartbeat_before_registration';
                }
            } catch (\Exception $e) {
                $corruptionDetected = true;
                $corruptionReasons[] = 'invalid_timestamp_format';
            }
        }

        if ('healthy' === $state['status'] && isset($state['failed_at'])) {
            $corruptionDetected = true;
            $corruptionReasons[] = 'healthy_status_with_failure_timestamp';
        }

        if ('failed' === $state['status'] && ! isset($state['failure_reason'])) {
            $corruptionDetected = true;
            $corruptionReasons[] = 'failed_status_without_reason';
        }

        if (isset($state['last_heartbeat'])) {
            $lastHeartbeat = Carbon::parse($state['last_heartbeat']);
            if ($lastHeartbeat->diffInMinutes(Carbon::now()) > 10 && 'healthy' === $state['status']) {
                $corruptionDetected = true;
                $corruptionReasons[] = 'stale_heartbeat_with_healthy_status';
            }
        }

        if ($corruptionDetected) {
            Log::error('🚨 State corruption detected', [
                'agent_id' => $agentId,
                'corruption_reasons' => $corruptionReasons,
            ]);

            event(new AgentLifecycleEvent(
                $agentId,
                'state_corruption_detected',
                $state['status'],
                'corrupted',
                [
                    'corruption_reasons' => $corruptionReasons,
                    'detected_at' => Carbon::now()->toISOString(),
                    'auto_repair' => true,
                ]
            ));

            $this->repairCorruptedState($agentId, $corruptionReasons);
        }

        return $corruptionDetected;
    }

    /**
     * Check if an agent is healthy.
     */
    private function isAgentHealthy(string $agentId): bool
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state) {
            return false;
        }

        if ('active' !== $state['status']) {
            return false;
        }

        if ($state['health_score'] < 50) {
            return false;
        }

        $lastHeartbeat = Carbon::parse($state['last_heartbeat']);
        if ($lastHeartbeat->diffInMinutes(Carbon::now()) > 5) {
            return false;
        }

        return true;
    }

    /**
     * Calculate agent uptime.
     *
     * @param array<string, mixed> $agent
     */
    private function calculateUptime(array $agent): string
    {
        if (! isset($agent['initialized_at'])) {
            return '0s';
        }

        $startTime = Carbon::parse($agent['initialized_at']);

        return $startTime->diffForHumans(Carbon::now(), true);
    }

    /**
     * Calculate health score based on metrics.
     *
     * @param array<string, mixed> $metrics
     */
    private function calculateHealthScore(array $metrics): int
    {
        $healthScore = 100;

        if (isset($metrics['error_rate']) && $metrics['error_rate'] > 0) {
            $healthScore -= min(50, $metrics['error_rate'] * 100);
        }

        if (isset($metrics['avg_response_time']) && $metrics['avg_response_time'] > 5000) {
            $healthScore -= min(30, ($metrics['avg_response_time'] - 5000) / 1000 * 10);
        }

        if (isset($metrics['circuit_breaker_open']) && $metrics['circuit_breaker_open']) {
            $healthScore -= 40;
        }

        return max(0, (int) $healthScore);
    }

    /**
     * Repair corrupted agent state.
     *
     * @param array<string> $corruptionReasons
     */
    private function repairCorruptedState(string $agentId, array $corruptionReasons): void
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state) {
            return;
        }

        $repairActions = [];

        foreach ($corruptionReasons as $reason) {
            $updates = match ($reason) {
                'last_heartbeat_before_registration' => [
                    'last_heartbeat' => $state['registered_at'],
                    '_repair' => 'fixed_heartbeat_timestamp',
                ],
                'invalid_timestamp_format' => [
                    'last_heartbeat' => Carbon::now()->toISOString(),
                    'registered_at' => $state['registered_at'] ?? Carbon::now()->toISOString(),
                    '_repair' => 'fixed_timestamp_format',
                ],
                'healthy_status_with_failure_timestamp' => [
                    'failed_at' => null,
                    'failure_reason' => null,
                    'failure_count' => null,
                    '_repair' => 'removed_failure_data',
                ],
                'failed_status_without_reason' => [
                    'failure_reason' => 'unknown_failure_during_state_repair',
                    'failed_at' => Carbon::now()->toISOString(),
                    '_repair' => 'added_failure_reason',
                ],
                'stale_heartbeat_with_healthy_status' => [
                    'status' => 'failed',
                    'failure_reason' => 'missed_heartbeat_threshold_exceeded',
                    'failed_at' => Carbon::now()->toISOString(),
                    '_repair' => 'marked_as_failed_due_to_stale_heartbeat',
                ],
                default => [],
            };

            if (isset($updates['_repair'])) {
                $repairActions[] = $updates['_repair'];
                unset($updates['_repair']);
            }

            $this->registry->updateAgentState($agentId, $updates);
        }

        Log::info('🔧 State corruption repaired', [
            'agent_id' => $agentId,
            'repair_actions' => $repairActions,
        ]);

        $state = $this->registry->getAgentState($agentId);

        event(new AgentLifecycleEvent(
            $agentId,
            'state_repaired',
            'corrupted',
            $state['status'] ?? 'healthy',
            [
                'repair_actions' => $repairActions,
                'repaired_at' => Carbon::now()->toISOString(),
            ]
        ));
    }
}
