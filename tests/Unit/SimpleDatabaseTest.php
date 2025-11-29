<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\SimpleTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class SimpleDatabaseTest extends SimpleTestCase
{
    /**
     * Test basic database connectivity.
     */
    public function testDatabaseConnection(): void
    {
        // Test that we can connect to the database
        self::assertTrue(null !== \DB::connection()->getPdo());
    }

    /**
     * Test creating a simple user.
     */
    public function testCreateUser(): void
    {
        $user = $this->createTestUser([
            'name' => 'Ahmed Test',
            'email' => 'ahmed@test.com',
        ]);

        self::assertSame('Ahmed Test', $user['name']);
        self::assertSame('ahmed@test.com', $user['email']);
        self::assertNotEmpty($user['password']);
    }

    /**
     * Test creating a simple product.
     */
    public function testCreateProduct(): void
    {
        $product = $this->createTestProduct([
            'name' => 'iPhone 15',
            'price' => 999.99,
        ]);

        self::assertSame('iPhone 15', $product['name']);
        self::assertSame(999.99, $product['price']);
    }

    /**
     * Test database table existence.
     */
    public function testTablesExist(): void
    {
        // Check if essential tables exist
        $connection = config('database.default');
        if ($connection === 'sqlite') {
            $tables = \DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $tableNames = array_column($tables, 'name');
        } else {
            $tables = \DB::select("SHOW TABLES");
            $tableKey = 'Tables_in_'.config('database.connections.'.$connection.'.database');
            $tableNames = array_column($tables, $tableKey);
        }

        self::assertContains('users', $tableNames);
        self::assertContains('products', $tableNames);
        self::assertContains('migrations', $tableNames);
    }

    /**
     * Test multiple operations without transaction conflicts.
     */
    public function testMultipleOperations(): void
    {
        // Create multiple users
        $user1 = $this->createTestUser(['email' => 'user1@test.com']);
        $user2 = $this->createTestUser(['email' => 'user2@test.com']);

        // Create multiple products
        $product1 = $this->createTestProduct(['name' => 'Product 1']);
        $product2 = $this->createTestProduct(['name' => 'Product 2']);

        // Verify all operations succeeded
        self::assertSame('user1@test.com', $user1['email']);
        self::assertSame('user2@test.com', $user2['email']);
        self::assertSame('Product 1', $product1['name']);
        self::assertSame('Product 2', $product2['name']);
    }
}
