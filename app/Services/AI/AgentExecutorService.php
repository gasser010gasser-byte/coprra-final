<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Events\AI\AgentLifecycleEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for executing agent lifecycle operations.
 */
class AgentExecutorService
{
    public function __construct(
        private readonly AgentRegistryService $registry
    ) {
    }

    /**
     * Initialize an agent and set it to active state.
     */
    public function initializeAgent(string $agentId): bool
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state) {
            Log::error('❌ Cannot initialize unregistered agent', ['agent_id' => $agentId]);

            return false;
        }

        try {
            $this->registry->restoreAgentState($agentId);

            $this->registry->updateAgentState($agentId, [
                'status' => 'active',
                'initialized_at' => Carbon::now()->toISOString(),
                'last_heartbeat' => Carbon::now()->toISOString(),
            ]);

            Log::info('✅ Agent initialized successfully', ['agent_id' => $agentId]);

            event(new AgentLifecycleEvent(
                $agentId,
                'initialized',
                'unregistered',
                'healthy',
                []
            ));

            return true;
        } catch (\Exception $e) {
            $this->registry->updateAgentState($agentId, [
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            Log::error('❌ Agent initialization failed', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Pause an agent.
     */
    public function pauseAgent(string $agentId): bool
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state) {
            return false;
        }

        $previousStatus = $state['status'];
        $this->registry->updateAgentState($agentId, [
            'status' => 'paused',
            'paused_at' => Carbon::now()->toISOString(),
        ]);

        Log::info('⏸️ Agent paused', ['agent_id' => $agentId]);

        event(new AgentLifecycleEvent(
            $agentId,
            'paused',
            $previousStatus,
            'paused',
            ['reason' => 'manual']
        ));

        return true;
    }

    /**
     * Resume a paused agent.
     */
    public function resumeAgent(string $agentId): bool
    {
        $state = $this->registry->getAgentState($agentId);

        if (! $state || 'paused' !== $state['status']) {
            return false;
        }

        $this->registry->updateAgentState($agentId, [
            'status' => 'active',
            'resumed_at' => Carbon::now()->toISOString(),
        ]);

        Log::info('▶️ Agent resumed', ['agent_id' => $agentId]);

        event(new AgentLifecycleEvent(
            $agentId,
            'resumed',
            'paused',
            'healthy',
            ['reason' => 'manual']
        ));

        return true;
    }

    /**
     * Clean up agent resources.
     */
    public function cleanupAgent(string $agentId): void
    {
        try {
            Log::info('🧹 Cleaning up agent resources', ['agent_id' => $agentId]);

            $this->registry->unregisterAgent($agentId);

            $cacheKeys = [
                "ai_agent_state_{$agentId}",
                "ai_agent_heartbeat_{$agentId}",
                "ai_agent_metrics_{$agentId}",
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            $stateFile = "agent_states/{$agentId}.json";
            if (Storage::disk('local')->exists($stateFile)) {
                Storage::disk('local')->delete($stateFile);
            }

            Log::info('✅ Agent cleanup completed', ['agent_id' => $agentId]);
        } catch (\Exception $e) {
            Log::error('❌ Agent cleanup failed', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
