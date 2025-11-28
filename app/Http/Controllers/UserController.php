<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\PasswordPolicyService;
use App\Services\UserBanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly UserBanService $userBanService,
        private readonly PasswordPolicyService $passwordPolicyService
    ) {
    }

    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->applyUserFilters($request, User::with(['wishlists', 'priceAlerts', 'reviews']));

        $users = $query->paginate(15);

        return $this->paginated($users, 'Users retrieved successfully');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['wishlists.product', 'priceAlerts.product', 'reviews.product']);

        return $this->success($user, 'User retrieved successfully');
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        /** @var array<string, bool|string> $validatedData */
        $validatedData = \is_array($validated) ? $validated : [];
        $user->update($validatedData);

        return $this->success($user->fresh(), 'User updated successfully');
    }

    /**
     * Change user password.
     */
    public function changePassword(ChangePasswordRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        // Verify current password
        $currentPassword = $request->input('current_password');
        if (! Hash::check(\is_string($currentPassword) ? $currentPassword : '', $user->password)) {
            return $this->error('Current password is incorrect', null, 400);
        }

        // Validate new password against policy
        $newPassword = $request->input('new_password');
        $emptyString = '';
        $passwordValidation = $this->passwordPolicyService->validatePassword(\is_string($newPassword) ? $newPassword : $emptyString, $user->id);
        if (! isset($passwordValidation['valid']) || ! $passwordValidation['valid']) {
            return $this->error(
                'Password does not meet policy requirements',
                $passwordValidation['errors'],
                400
            );
        }

        // Update password
        $user->update([
            'password' => Hash::make(\is_string($newPassword) ? $newPassword : ''),
        ]);

        // Save password to history
        $passwordValue = $request->input('password');
        $password = \is_string($passwordValue) ? $passwordValue : '';
        $this->passwordPolicyService->savePasswordToHistory($user->id, $password);

        return $this->success(null, 'Password updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Soft delete user and related data
        $user->wishlists()->delete();
        $user->priceAlerts()->delete();
        $user->reviews()->delete();
        $user->delete();

        return $this->noContent();
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(int $userId): JsonResponse
    {
        User::findOrFail($userId);

        // Note: This would require soft deletes to be implemented in User model
        return $this->error('Soft deletes not implemented for users', null, 501);
    }

    /**
     * Get banned users.
     */
    public function banned(): JsonResponse
    {
        $bannedUsers = $this->userBanService->getBannedUsers();

        return $this->success($bannedUsers, 'Banned users retrieved successfully');
    }

    /**
     * Get user's wishlist.
     */
    public function wishlist(User $user): JsonResponse
    {
        $wishlist = $user->wishlists()->with('product.category', 'product.brand')->get();

        return $this->success($wishlist, 'User wishlist retrieved successfully');
    }

    /**
     * Get user's price alerts.
     */
    public function priceAlerts(User $user): JsonResponse
    {
        $priceAlerts = $user->priceAlerts()->with('product.category', 'product.brand')->get();

        return $this->success($priceAlerts, 'User price alerts retrieved successfully');
    }

    /**
     * Get user's reviews.
     */
    public function reviews(User $user): JsonResponse
    {
        $reviews = $user->reviews()->with('product.category', 'product.brand')->get();

        return $this->success($reviews, 'User reviews retrieved successfully');
    }

    private function applyUserFilters(Request $request, Builder $query): Builder
    {
        // Search by name or email
        if ($request->has('search')) {
            $searchInput = $request->get('search');
            $search = \is_string($searchInput) ? $searchInput : '';
            $query->where(static function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                ;
            });
        }

        // Filter by role (if role column exists) - Fixed SQL Injection vulnerability
        if ($request->has('role')) {
            $role = $request->get('role');
            if (\is_string($role)) {
                $query->where('role', $role);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->get('status');
            if ('active' === $status) {
                $query->where('is_blocked', false);
            } elseif ('blocked' === $status) {
                $query->where('is_blocked', true);
            }
        }

        return $query;
    }
}
