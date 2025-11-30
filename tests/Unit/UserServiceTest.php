<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserBanService;
use Carbon\Carbon;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Psr\Log\LoggerInterface;
use Tests\TestCase as BaseTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserServiceTest extends BaseTestCase
{
    use RefreshDatabase;

    private UserBanService $userBanService;
    private LoggerInterface $logger;
    private AuthManager $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        // Use Auth facade mock instead of AuthManager mock since id() is called via facade
        Auth::shouldReceive('id')->andReturn(1);
        $this->auth = app(AuthManager::class);
        $this->userBanService = new UserBanService($this->auth, $this->logger);
    }

    public function testIsUserBannedReturnsFalseForNonBannedUser(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $result = $this->userBanService->isUserBanned($user);

        self::assertFalse($result);
    }

    public function testIsUserBannedReturnsTrueForPermanentlyBannedUser(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => null,
        ]);

        $result = $this->userBanService->isUserBanned($user);

        self::assertTrue($result);
    }

    public function testIsUserBannedReturnsTrueForTemporarilyBannedUser(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        $result = $this->userBanService->isUserBanned($user);

        self::assertTrue($result);
    }

    public function testIsUserBannedReturnsFalseForExpiredBan(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->userBanService->isUserBanned($user);

        self::assertFalse($result);
    }

    public function testBanUserSuccessfully(): void
    {
        $this->logger->expects(self::once())->method('info');

        $user = User::factory()->create(['is_blocked' => false]);
        $expiresAt = Carbon::now()->addDays(7);

        $result = $this->userBanService->banUser($user, 'spam', 'Sending spam messages', $expiresAt);

        self::assertTrue($result);
        $user->refresh();
        self::assertTrue($user->is_blocked);
        self::assertSame('spam', $user->ban_reason);
        self::assertSame('Sending spam messages', $user->ban_description);
        self::assertSame($expiresAt->toDateTimeString(), $user->ban_expires_at->toDateTimeString());
        self::assertNotNull($user->banned_at);
        self::assertSame(1, $user->banned_by);
    }

    public function testBanUserFailsWithInvalidReason(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $result = $this->userBanService->banUser($user, 'invalid_reason');

        self::assertFalse($result);
        $user->refresh();
        self::assertFalse($user->is_blocked);
    }

    public function testBanUserFailsWhenAlreadyBanned(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        $result = $this->userBanService->banUser($user, 'spam');

        self::assertFalse($result);
    }

    public function testUnbanUserSuccessfully(): void
    {
        $this->logger->expects(self::once())->method('info');

        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_reason' => 'spam',
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        $result = $this->userBanService->unbanUser($user);

        self::assertTrue($result);
        $user->refresh();
        self::assertFalse($user->is_blocked);
        self::assertNull($user->ban_reason);
        self::assertNull($user->ban_description);
        self::assertNull($user->ban_expires_at);
        self::assertNotNull($user->unbanned_at);
        self::assertSame(1, $user->unbanned_by);
    }

    public function testUnbanUserFailsWhenNotBanned(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $result = $this->userBanService->unbanUser($user);

        self::assertFalse($result);
    }

    public function testGetBanInfoReturnsNullForNonBannedUser(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $result = $this->userBanService->getBanInfo($user);

        self::assertNull($result);
    }

    public function testGetBanInfoReturnsCorrectDataForBannedUser(): void
    {
        $bannedAt = Carbon::now()->subDays(1);
        $expiresAt = Carbon::now()->addDays(7);

        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_reason' => 'spam',
            'ban_description' => 'Sending spam messages',
            'banned_at' => $bannedAt,
            'ban_expires_at' => $expiresAt,
        ]);

        $result = $this->userBanService->getBanInfo($user);

        self::assertIsArray($result);
        self::assertArrayHasKey('reason', $result);
        self::assertArrayHasKey('description', $result);
        self::assertArrayHasKey('banned_at', $result);
        self::assertArrayHasKey('expires_at', $result);
        self::assertArrayHasKey('is_permanent', $result);
        self::assertSame('spam', $result['reason']);
        self::assertSame('Sending spam messages', $result['description']);
        self::assertFalse($result['is_permanent']);
    }

    public function testGetBanInfoForPermanentBan(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_reason' => 'fraud',
            'ban_expires_at' => null,
        ]);

        $result = $this->userBanService->getBanInfo($user);

        self::assertIsArray($result);
        self::assertTrue($result['is_permanent']);
        self::assertNull($result['expires_at']);
    }

    public function testGetBannedUsersReturnsCorrectUsers(): void
    {
        // Create non-banned user
        User::factory()->create(['is_blocked' => false]);

        // Create permanently banned user
        $permanentBan = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => null,
        ]);

        // Create temporarily banned user
        $temporaryBan = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        // Create expired ban user
        User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->userBanService->getBannedUsers();

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(2, $result);
        self::assertTrue($result->contains($permanentBan));
        self::assertTrue($result->contains($temporaryBan));
    }

    public function testGetUsersWithExpiredBans(): void
    {
        // Create active ban
        User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        // Create expired ban
        $expiredUser = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->userBanService->getUsersWithExpiredBans();

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(1, $result);
        self::assertTrue($result->contains($expiredUser));
    }

    public function testCleanupExpiredBans(): void
    {
        $this->logger->expects(self::exactly(2))->method('info');

        // Create expired bans
        User::factory()->count(2)->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->userBanService->cleanupExpiredBans();

        self::assertSame(2, $result);
        self::assertSame(0, $this->userBanService->getUsersWithExpiredBans()->count());
    }

    public function testGetBanStatistics(): void
    {
        // Create various ban scenarios
        User::factory()->create(['is_blocked' => false]); // Not banned
        User::factory()->create(['is_blocked' => true, 'ban_expires_at' => null]); // Permanent
        User::factory()->create(['is_blocked' => true, 'ban_expires_at' => Carbon::now()->addDays(1)]); // Temporary
        User::factory()->create(['is_blocked' => true, 'ban_expires_at' => Carbon::now()->subDays(1)]); // Expired

        $result = $this->userBanService->getBanStatistics();

        self::assertIsArray($result);
        self::assertArrayHasKey('total_banned', $result);
        self::assertArrayHasKey('permanent_bans', $result);
        self::assertArrayHasKey('temporary_bans', $result);
        self::assertArrayHasKey('expired_bans', $result);
        self::assertArrayHasKey('ban_reasons', $result);
        self::assertSame(3, $result['total_banned']);
        self::assertSame(1, $result['permanent_bans']);
        self::assertSame(2, $result['temporary_bans']);
        self::assertSame(1, $result['expired_bans']);
        self::assertIsArray($result['ban_reasons']);
    }

    public function testGetBanReasons(): void
    {
        $result = $this->userBanService->getBanReasons();

        self::assertIsArray($result);
        self::assertArrayHasKey('spam', $result);
        self::assertArrayHasKey('abuse', $result);
        self::assertArrayHasKey('fraud', $result);
        self::assertArrayHasKey('violation', $result);
        self::assertArrayHasKey('security', $result);
        self::assertArrayHasKey('other', $result);
    }

    public function testCanBanUserReturnsTrueForNonBannedUser(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $result = $this->userBanService->canBanUser($user);

        self::assertTrue($result);
    }

    public function testCanBanUserReturnsFalseForBannedUser(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        $result = $this->userBanService->canBanUser($user);

        self::assertFalse($result);
    }

    public function testCanUnbanUserReturnsTrueForBannedUser(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        $result = $this->userBanService->canUnbanUser($user);

        self::assertTrue($result);
    }

    public function testCanUnbanUserReturnsFalseForNonBannedUser(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $result = $this->userBanService->canUnbanUser($user);

        self::assertFalse($result);
    }

    public function testGetBanHistoryReturnsEmptyForNonBannedUser(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);

        $result = $this->userBanService->getBanHistory($user);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testGetBanHistoryReturnsDataForBannedUser(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_reason' => 'spam',
            'ban_expires_at' => Carbon::now()->addDays(1),
        ]);

        $result = $this->userBanService->getBanHistory($user);

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertArrayHasKey('reason', $result[0]);
    }

    public function testExtendBanSuccessfully(): void
    {
        Auth::shouldReceive('id')->andReturn(1);
        $this->logger->expects(self::once())->method('info');

        $originalExpiry = Carbon::now()->addDays(1);
        $newExpiry = Carbon::now()->addDays(7);

        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => $originalExpiry,
        ]);

        $result = $this->userBanService->extendBan($user, $newExpiry);

        self::assertTrue($result);
        $user->refresh();
        self::assertSame($newExpiry->toDateTimeString(), $user->ban_expires_at->toDateTimeString());
    }

    public function testExtendBanFailsForNonBannedUser(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);
        $newExpiry = Carbon::now()->addDays(7);

        $result = $this->userBanService->extendBan($user, $newExpiry);

        self::assertFalse($result);
    }

    public function testExtendBanFailsForPermanentBan(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => null,
        ]);
        $newExpiry = Carbon::now()->addDays(7);

        $result = $this->userBanService->extendBan($user, $newExpiry);

        self::assertFalse($result);
    }

    public function testReduceBanSuccessfully(): void
    {
        Auth::shouldReceive('id')->andReturn(1);
        $this->logger->expects(self::once())->method('info');

        $originalExpiry = Carbon::now()->addDays(7);
        $newExpiry = Carbon::now()->addDays(1);

        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => $originalExpiry,
        ]);

        $result = $this->userBanService->reduceBan($user, $newExpiry);

        self::assertTrue($result);
        $user->refresh();
        self::assertSame($newExpiry->toDateTimeString(), $user->ban_expires_at->toDateTimeString());
    }

    public function testReduceBanFailsForNonBannedUser(): void
    {
        $user = User::factory()->create(['is_blocked' => false]);
        $newExpiry = Carbon::now()->addDays(1);

        $result = $this->userBanService->reduceBan($user, $newExpiry);

        self::assertFalse($result);
    }

    public function testReduceBanFailsForPermanentBan(): void
    {
        $user = User::factory()->create([
            'is_blocked' => true,
            'ban_expires_at' => null,
        ]);
        $newExpiry = Carbon::now()->addDays(1);

        $result = $this->userBanService->reduceBan($user, $newExpiry);

        self::assertFalse($result);
    }
}
