<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Safe test base class that handles database setup without RefreshDatabase conflicts.
 * Use this for tests that need database access but encounter transaction conflicts.
 */
abstract class SafeTestBase extends TestCase
{
    protected static bool $databaseSetup = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$databaseSetup) {
            $this->setUpDatabase();
            static::$databaseSetup = true;
        }

        // Clear data before each test instead of using transactions
        $this->clearTestData();
    }

    /**
     * Set up the database for testing.
     */
    protected function setUpDatabase(): void
    {
        $connection = config('database.default', 'sqlite');

        try {
            // Test connection
            DB::connection($connection)->select('SELECT 1');
        } catch (\Exception $e) {
            // If connection fails, use in-memory SQLite
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);
            $connection = 'sqlite';
        }

        $this->createTablesInConnection($connection);
    }

    /**
     * Create tables in a specific connection.
     */
    protected function createTablesInConnection(?string $connection = null): void
    {
        $conn = $connection ?: config('database.default', 'sqlite');
        DB::connection($conn)->statement('PRAGMA foreign_keys = OFF');

        $this->createUsersTable($conn);
        $this->createProductsTable($conn);
        $this->createCategoriesTable($conn);
        $this->createBrandsTable($conn);
        $this->createStoresTable($conn);
        $this->createAddressesTable($conn);
        $this->createOrdersTable($conn);
        $this->createOrderItemsTable($conn);
        $this->createPaymentsTable($conn);
        $this->createPriceOffersTable($conn);
        $this->createReviewsTable($conn);
        $this->createWishlistsTable($conn);
        $this->createLanguagesTable($conn);
        $this->createCurrenciesTable($conn);
        $this->createExchangeRatesTable($conn);
        $this->createMigrationsTable($conn);
        $this->createPasswordResetTokensTable($conn);
        $this->createPersonalAccessTokensTable($conn);

        DB::connection($conn)->statement('PRAGMA foreign_keys = ON');
    }

    /**
     * Clear test data before each test.
     */
    protected function clearTestData(): void
    {
        // Clear tables in reverse order to respect foreign keys
        $tables = [
            'personal_access_tokens',
            'password_reset_tokens',
            'exchange_rates',
            'currencies',
            'languages',
            'stores',
            'brands',
            'categories',
            'products',
            'users',
        ];

        foreach ($tables as $table) {
            try {
                DB::table($table)->truncate();
            } catch (\Exception $e) {
                // Table might not exist or be empty, continue
            }
        }
    }

    // Table creation methods (copied from DatabaseSetup trait)

    protected function createUsersTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'users')) {
            DB::connection($connection)->statement('
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    email_verified_at TIMESTAMP NULL,
                    password VARCHAR(255) NOT NULL,
                    remember_token VARCHAR(100) NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createProductsTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'products')) {
            DB::connection($connection)->statement('
                CREATE TABLE products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    category_id INTEGER NULL,
                    brand_id INTEGER NULL,
                    store_id INTEGER NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createCategoriesTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'categories')) {
            DB::connection($connection)->statement('
                CREATE TABLE categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NULL UNIQUE,
                    description TEXT NULL,
                    parent_id INTEGER NULL,
                    level INTEGER DEFAULT 0,
                    image_url VARCHAR(255) NULL,
                    is_active BOOLEAN DEFAULT 1,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    deleted_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createBrandsTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'brands')) {
            DB::connection($connection)->statement('
                CREATE TABLE brands (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createStoresTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'stores')) {
            DB::connection($connection)->statement('
                CREATE TABLE stores (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NULL UNIQUE,
                    description TEXT NULL,
                    logo_url VARCHAR(255) NULL,
                    website_url VARCHAR(255) NULL,
                    country_code VARCHAR(2) NULL,
                    supported_countries TEXT NULL,
                    is_active BOOLEAN DEFAULT 1,
                    priority INTEGER DEFAULT 0,
                    affiliate_base_url TEXT NULL,
                    affiliate_code VARCHAR(255) NULL,
                    api_config TEXT NULL,
                    currency_id INTEGER NULL,
                    contact_email VARCHAR(255) NULL,
                    email VARCHAR(255) NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    deleted_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createLanguagesTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'languages')) {
            DB::connection($connection)->statement('
                CREATE TABLE languages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code VARCHAR(10) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    is_active BOOLEAN DEFAULT 1,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createCurrenciesTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'currencies')) {
            DB::connection($connection)->statement('
                CREATE TABLE currencies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code VARCHAR(10) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    symbol VARCHAR(10) NOT NULL,
                    exchange_rate DECIMAL(10,6) DEFAULT 1.000000,
                    decimal_places INTEGER DEFAULT 2,
                    is_active BOOLEAN DEFAULT 1,
                    is_default BOOLEAN DEFAULT 0,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createExchangeRatesTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'exchange_rates')) {
            DB::connection($connection)->statement('
                CREATE TABLE exchange_rates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    from_currency VARCHAR(3) NOT NULL,
                    to_currency VARCHAR(3) NOT NULL,
                    rate DECIMAL(15,8) NOT NULL,
                    source VARCHAR(50) NULL,
                    fetched_at TIMESTAMP NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    UNIQUE(from_currency, to_currency)
                )
            ');
        }
    }

    protected function createMigrationsTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'migrations')) {
            DB::connection($connection)->statement('
                CREATE TABLE migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL
                )
            ');
        }
    }

    protected function createPasswordResetTokensTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'password_reset_tokens')) {
            DB::connection($connection)->statement('
                CREATE TABLE password_reset_tokens (
                    email VARCHAR(255) PRIMARY KEY,
                    token VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createPersonalAccessTokensTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'personal_access_tokens')) {
            DB::connection($connection)->statement('
                CREATE TABLE personal_access_tokens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tokenable_type VARCHAR(255) NOT NULL,
                    tokenable_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    token VARCHAR(64) UNIQUE NOT NULL,
                    abilities TEXT NULL,
                    last_used_at TIMESTAMP NULL,
                    expires_at TIMESTAMP NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createAddressesTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'addresses')) {
            DB::connection($connection)->statement('
                CREATE TABLE addresses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    street VARCHAR(255) NULL,
                    city VARCHAR(255) NULL,
                    state VARCHAR(255) NULL,
                    country VARCHAR(255) NULL,
                    postal_code VARCHAR(20) NULL,
                    is_default BOOLEAN DEFAULT 0,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createOrdersTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'orders')) {
            DB::connection($connection)->statement('
                CREATE TABLE orders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    order_number VARCHAR(255) NOT NULL UNIQUE,
                    subtotal DECIMAL(10,2) NOT NULL,
                    tax_amount DECIMAL(10,2) DEFAULT 0,
                    shipping_amount DECIMAL(10,2) DEFAULT 0,
                    discount_amount DECIMAL(10,2) DEFAULT 0,
                    total_amount DECIMAL(10,2) NOT NULL,
                    status VARCHAR(255) DEFAULT "pending",
                    currency VARCHAR(3) DEFAULT "USD",
                    shipping_address TEXT NULL,
                    billing_address TEXT NULL,
                    notes TEXT NULL,
                    order_date DATETIME NULL,
                    shipped_at DATETIME NULL,
                    delivered_at DATETIME NULL,
                    weight DECIMAL(10,2) NULL,
                    dimensions TEXT NULL,
                    tracking_number VARCHAR(255) NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createOrderItemsTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'order_items')) {
            DB::connection($connection)->statement('
                CREATE TABLE order_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    quantity INTEGER NOT NULL DEFAULT 1,
                    unit_price DECIMAL(10,2) NOT NULL,
                    total DECIMAL(10,2) NOT NULL,
                    subtotal DECIMAL(10,2) NULL,
                    price DECIMAL(10,2) NULL,
                    product_details TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createPaymentsTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'payments')) {
            DB::connection($connection)->statement('
                CREATE TABLE payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT "USD",
                    status VARCHAR(50) DEFAULT "pending",
                    method VARCHAR(50) NULL,
                    gateway VARCHAR(50) NULL,
                    transaction_id VARCHAR(255) NULL,
                    gateway_response TEXT NULL,
                    metadata TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createPriceOffersTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'price_offers')) {
            DB::connection($connection)->statement('
                CREATE TABLE price_offers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    product_sku VARCHAR(255) NULL,
                    store_id INTEGER NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT "USD",
                    product_url TEXT NULL,
                    affiliate_url TEXT NULL,
                    in_stock BOOLEAN DEFAULT 1,
                    stock_quantity INTEGER DEFAULT 0,
                    condition VARCHAR(255) NULL,
                    rating DECIMAL(2,1) NULL,
                    shipping_cost DECIMAL(8,2) NULL,
                    delivery_time VARCHAR(255) NULL,
                    expires_at DATETIME NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createReviewsTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'reviews')) {
            DB::connection($connection)->statement('
                CREATE TABLE reviews (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    title VARCHAR(255) NULL,
                    content TEXT NULL,
                    rating INTEGER NOT NULL DEFAULT 5,
                    is_verified_purchase BOOLEAN DEFAULT 0,
                    is_approved BOOLEAN DEFAULT 0,
                    helpful_votes TEXT NULL,
                    helpful_count INTEGER DEFAULT 0,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ');
        }
    }

    protected function createWishlistsTable(string $connection): void
    {
        if (! $this->tableExists($connection, 'wishlists')) {
            DB::connection($connection)->statement('
                CREATE TABLE wishlists (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    notes TEXT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    deleted_at TIMESTAMP NULL,
                    UNIQUE(user_id, product_id)
                )
            ');
        }
    }

    /**
     * Check if a table exists in the given connection.
     */
    protected function tableExists(string $connection, string $table): bool
    {
        try {
            $result = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);

            return ! empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
}
