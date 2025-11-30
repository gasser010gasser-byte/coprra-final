<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BehaviorAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly BehaviorAnalysisService $behaviorAnalysisService
    ) {
    }

    /**
     * Get user analytics.
     */
    public function userAnalytics(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $analytics = $this->behaviorAnalysisService->getUserAnalytics($user);

        return response()->json(['analytics' => $analytics]);
    }

    /**
     * Get site analytics.
     */
    public function siteAnalytics(): JsonResponse
    {
        $analytics = $this->behaviorAnalysisService->getSiteAnalytics();

        return response()->json(['analytics' => $analytics]);
    }

    /**
     * Track user behavior.
     */
    public function trackBehavior(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|max:50',
            'data' => 'nullable|array',
        ]);

        if (! is_array($validated)) {
            return response()->json(['error' => 'Invalid validation result'], 400);
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $action = $validated['action'] ?? '';
        $data = $validated['data'] ?? [];

        $this->behaviorAnalysisService->trackUserBehavior($user, $action, $data);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل السلوك بنجاح',
        ]);
    }
}
