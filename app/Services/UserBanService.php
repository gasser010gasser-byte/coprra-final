<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;

final readonly class UserBanService
{
    private const BAN_REASONS = [
        'spam' => 'إرسال رسائل مزعجة',
        'abuse' => 'إساءة استخدام',
        'fraud' => 'احتيال',
        'violation' => 'انتهاك شروط الاستخدام',
        'security' => 'مخاطر أمنية',
        'other' => 'أسباب أخرى',
    ];

    public function __construct(
        private AuthManager $auth,
        private LoggerInterface $logger,
        private string $userModel = User::class
    ) {
    }

    /**
     * Check if a user is currently banned.
     */
    public function isUserBanned(User $user): bool
    {
        if (!$user->is_blocked) {
            return false;
        }

        // If ban_expires_at is null, it's a permanent ban
        if (null === $user->ban_expires_at) {
            return true;
        }

        // Check if temporary ban has expired
        return $user->ban_expires_at->isFuture();
    }

    /**
     * Ban a user with specified reason and duration.
     */
    public function banUser(User $user, string $reason, ?string $description = null, ?Carbon $expiresAt = null): bool
    {
        if (!$this->isValidBanReason($reason)) {
            return false;
        }

        if (!$this->canBanUser($user)) {
            return false;
        }

        $user->update([
            'is_blocked' => true,
            'ban_reason' => $reason,
            'ban_description' => $description,
            'ban_expires_at' => $expiresAt,
            'banned_at' => now(),
            'banned_by' => Auth::id() ?? $this->auth->id(),
        ]);

        $this->logger->info('User banned', [
            'user_id' => $user->id,
            'reason' => $reason,
            'expires_at' => $expiresAt ? $expiresAt->toDateTimeString() : null,
            'banned_by' => Auth::id() ?? $this->auth->id(),
        ]);

        return true;
    }

    /**
     * Unban a user.
     */
    public function unbanUser(User $user): bool
    {
        if (!$this->canUnbanUser($user)) {
            return false;
        }

        $user->update([
            'is_blocked' => false,
            'ban_reason' => null,
            'ban_description' => null,
            'ban_expires_at' => null,
            'unbanned_at' => now(),
            'unbanned_by' => Auth::id() ?? $this->auth->id(),
        ]);

        $this->logger->info('User unbanned', [
            'user_id' => $user->id,
            'unbanned_by' => Auth::id() ?? $this->auth->id(),
        ]);

        return true;
    }

    /**
     * Get ban information for a user.
     */
    public function getBanInfo(User $user): ?array
    {
        if (!$this->isUserBanned($user)) {
            return null;
        }

        return [
            'reason' => $user->ban_reason,
            'description' => $user->ban_description,
            'banned_at' => $user->banned_at,
            'expires_at' => $user->ban_expires_at,
            'is_permanent' => null === $user->ban_expires_at,
        ];
    }

    /**
     * Get all banned users.
     *
     * @return Collection<int, User>
     */
    public function getBannedUsers(): Collection
    {
        return User::where('is_blocked', true)
            ->where(static function ($query): void {
                $query->whereNull('ban_expires_at')
                    ->orWhere('ban_expires_at', '>', now())
                ;
            })
            ->get()
        ;
    }

    /**
     * Get users with expired bans.
     *
     * @return Collection<int, User>
     */
    public function getUsersWithExpiredBans(): Collection
    {
        return User::where('is_blocked', true)
            ->where('ban_expires_at', '<=', now())
            ->whereNotNull('ban_expires_at')
            ->get()
        ;
    }

    /**
     * Clean up expired bans.
     */
    public function cleanupExpiredBans(): int
    {
        $expiredUsers = $this->getUsersWithExpiredBans();
        $count = 0;

        foreach ($expiredUsers as $user) {
            if ($this->unbanUser($user)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Get ban statistics.
     *
     * @return array<string, array<string, string>|int>
     */
    public function getBanStatistics(): array
    {
        $bannedUsersQuery = User::where('is_blocked', true);

        $totalBanned = $bannedUsersQuery->count();
        $permanentBans = (clone $bannedUsersQuery)->whereNull('ban_expires_at')->count();
        $temporaryBans = (clone $bannedUsersQuery)->whereNotNull('ban_expires_at')->count();
        $expiredBans = (clone $bannedUsersQuery)->where('ban_expires_at', '<=', now())->count();

        return [
            'total_banned' => $totalBanned,
            'permanent_bans' => $permanentBans,
            'temporary_bans' => $temporaryBans,
            'expired_bans' => $expiredBans,
            'ban_reasons' => self::BAN_REASONS,
        ];
    }

    /**
     * Get available ban reasons.
     *
     * @return array<string, string>
     */
    public function getBanReasons(): array
    {
        return self::BAN_REASONS;
    }

    /**
     * Check if a user can be banned.
     */
    public function canBanUser(User $user): bool
    {
        // Can't ban if already banned
        if ($this->isUserBanned($user)) {
            return false;
        }

        // Add additional logic here if needed (e.g., role-based restrictions)
        return true;
    }

    /**
     * Check if a user can be unbanned.
     * Users with expired bans or active bans can be unbanned.
     */
    public function canUnbanUser(User $user): bool
    {
        // Can unbanned if currently blocked (including expired bans)
        return $user->is_blocked === true;
    }

    /**
     * Get ban history for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBanHistory(User $user): array
    {
        // This would typically come from a ban_history table
        // For now, return current ban info if exists
        $banInfo = $this->getBanInfo($user);

        return $banInfo ? [$banInfo] : [];
    }

    /**
     * Extend a user's ban duration.
     */
    public function extendBan(User $user, Carbon $newExpiry): bool
    {
        if (!$this->isUserBanned($user)) {
            return false;
        }

        // Can't extend a permanent ban
        if (null === $user->ban_expires_at) {
            return false;
        }

        $user->update([
            'ban_expires_at' => $newExpiry,
        ]);

        $this->logger->info('Ban extended', [
            'user_id' => $user->id,
            'new_expiry' => $newExpiry->toDateTimeString(),
            'extended_by' => Auth::id() ?? $this->auth->id(),
        ]);

        return true;
    }

    /**
     * Reduce a user's ban duration.
     */
    public function reduceBan(User $user, Carbon $newExpiry): bool
    {
        if (!$this->isUserBanned($user)) {
            return false;
        }

        // Can't reduce a permanent ban
        if (null === $user->ban_expires_at) {
            return false;
        }

        $user->update([
            'ban_expires_at' => $newExpiry,
        ]);

        $this->logger->info('Ban reduced', [
            'user_id' => $user->id,
            'new_expiry' => $newExpiry->toDateTimeString(),
            'reduced_by' => Auth::id() ?? $this->auth->id(),
        ]);

        return true;
    }

    private function isValidBanReason(string $reason): bool
    {
        return '' !== $reason && \array_key_exists($reason, self::BAN_REASONS);
    }
}
