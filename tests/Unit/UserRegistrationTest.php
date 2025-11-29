<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserRegistrationTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up any users created during tests
        User::query()->delete();

        parent::tearDown();
    }

    public function testUserCanBeCreatedWithValidData(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'is_admin' => false,
            'is_active' => true,
            'is_blocked' => false,
            'role' => 'user',
        ];

        $user = User::create($userData);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('John Doe', $user->name);
        self::assertSame('john@example.com', $user->email);
        self::assertFalse($user->is_admin);
        self::assertTrue($user->is_active);
        self::assertFalse($user->is_blocked);
        self::assertSame('user', $user->role);
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function testUserPasswordIsHashed(): void
    {
        $userData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('plainpassword'),
        ];

        $user = User::create($userData);

        self::assertNotSame('plainpassword', $user->password);
        self::assertTrue(Hash::check('plainpassword', $user->password));
    }

    public function testUserHasCorrectFillableFields(): void
    {
        $user = new User();
        $expectedFillable = [
            'name',
            'email',
            'password',
            'is_admin',
            'is_active',
            'is_blocked',
            'ban_reason',
            'ban_description',
            'banned_at',
            'banned_by',
            'ban_expires_at',
            'unbanned_at',
            'unbanned_by',
            'session_id',
            'role',
            'permissions',
            'password_confirmed_at',
        ];

        self::assertSame($expectedFillable, $user->getFillable());
    }

    public function testUserHasCorrectHiddenFields(): void
    {
        $user = new User();
        $expectedHidden = [
            'password',
            'remember_token',
        ];

        self::assertSame($expectedHidden, $user->getHidden());
    }

    public function testUserCanBeAdmin(): void
    {
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('adminpass'),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        self::assertTrue($adminUser->is_admin);
        self::assertSame('admin', $adminUser->role);
    }

    public function testUserCanBeBlocked(): void
    {
        $blockedUser = User::create([
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => Hash::make('password'),
            'is_blocked' => true,
            'ban_reason' => 'Violation of terms',
            'ban_description' => 'User violated community guidelines',
            'banned_at' => now(),
            'ban_expires_at' => now()->addDays(30),
        ]);

        self::assertTrue($blockedUser->is_blocked);
        self::assertSame('Violation of terms', $blockedUser->ban_reason);
        self::assertNotNull($blockedUser->banned_at);
        self::assertNotNull($blockedUser->ban_expires_at);
    }
}
