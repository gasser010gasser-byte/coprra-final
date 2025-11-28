<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Reward;
use App\Services\PointsService;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointsController extends Controller
{
    public function __construct(
        private readonly PointsService $pointsService
    ) {
    }

    /**
     * Display the user's points and rewards.
     */
    public function index(Request $request, Guard $auth): View
    {
        $user = $auth->user();
        $availablePoints = $this->pointsService->getAvailablePoints($user->id);

        // Get user's point history
        $pointHistory = $user->points()
            ->latest()
            ->take(20)
            ->get();

        // Get available rewards
        $rewards = Reward::where('is_active', true)
            ->orderBy('points_required', 'asc')
            ->get();

        return view('account.points', [
            'availablePoints' => $availablePoints,
            'pointHistory' => $pointHistory,
            'rewards' => $rewards,
        ]);
    }

    /**
     * Redeem points manually.
     */
    public function redeem(Request $request, Guard $auth): RedirectResponse
    {
        $validated = $request->validate([
            'points' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $this->pointsService->redeemPoints(
                $auth->user(),
                $validated['points'],
                $validated['reason'] ?? 'Manual redemption'
            );

            return redirect()->route('account.points')
                ->with('success', 'Points redeemed successfully!');
        } catch (\Exception $e) {
            return redirect()->route('account.points')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get available rewards (API endpoint).
     */
    public function getRewards(): \Illuminate\Http\JsonResponse
    {
        $rewards = Reward::where('is_active', true)
            ->orderBy('points_required', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'rewards' => $rewards,
        ]);
    }

    /**
     * Redeem a specific reward.
     */
    public function redeemReward(Reward $reward, Guard $auth): RedirectResponse
    {
        $user = $auth->user();
        $availablePoints = $this->pointsService->getAvailablePoints($user->id);

        if ($availablePoints < $reward->points_required) {
            return redirect()->route('account.points')
                ->with('error', 'Insufficient points to redeem this reward.');
        }

        try {
            $this->pointsService->redeemPoints(
                $user,
                $reward->points_required,
                "Redeemed reward: {$reward->name}"
            );

            return redirect()->route('account.points')
                ->with('success', "Reward '{$reward->name}' redeemed successfully!");
        } catch (\Exception $e) {
            return redirect()->route('account.points')
                ->with('error', $e->getMessage());
        }
    }
}
