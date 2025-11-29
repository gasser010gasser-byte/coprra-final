<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\DB;

trait DatabaseSetup
{
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

        // Disable foreign key constraints during table creation
        DB::connection($conn)->statement('PRAGMA foreign_keys=OFF;');
        DB::connection($conn)->statement('PRAGMA auto_vacuum=0;');

        // Create essential tables for testing
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
        $this->createPriceHistoriesTable($conn);
        $this->createReviewsTable($conn);
        $this->createWishlistsTable($conn);
        $this->createLanguagesTable($conn);
        $this->createCurrenciesTable($conn);
        $this->createExchangeRatesTable($conn);
        $this->createMigrationsTable($conn);
        $this->createPasswordResetTokensTable($conn);
        $this->createPersonalAccessTokensTable($conn);

        // Re-enable foreign key constraints
        DB::connection($conn)->statement('PRAGMA foreign_keys=ON;');
    }

    /**
     * Create users table.
     */
    protected function createUsersTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE,
                    email_verified_at DATETIME,
                    password VARCHAR(255) NOT NULL,
                    password_confirmed_at DATETIME,
                    is_admin BOOLEAN DEFAULT 0,
                    phone VARCHAR(20),
                    role VARCHAR(255) DEFAULT "user",
                    permissions TEXT,
                    is_blocked BOOLEAN DEFAULT 0,
                    ban_reason VARCHAR(255),
                    ban_description TEXT,
                    banned_at DATETIME,
                    banned_by INTEGER,
                    ban_expires_at DATETIME,
                    unbanned_at DATETIME,
                    unbanned_by INTEGER,
                    is_active BOOLEAN DEFAULT 1,
                    session_id VARCHAR(255),
                    remember_token VARCHAR(100),
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');
        }
    }

    /**
     * Create products table.
     */
    protected function createProductsTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='products'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL UNIQUE,
                    description TEXT,
                    price DECIMAL(10,2) NOT NULL,
                    image VARCHAR(255),
                    sku VARCHAR(255),
                    stock_quantity INTEGER DEFAULT 0,
                    category_id INTEGER,
                    brand_id INTEGER,
                    store_id INTEGER,
                    currency_id INTEGER,
                    is_active BOOLEAN DEFAULT 1,
                    is_featured BOOLEAN DEFAULT 0,
                    store_mappings TEXT,
                    created_at DATETIME,
                    updated_at DATETIME,
                    deleted_at DATETIME
                )
            ');
        }
    }

    /**
     * Create categories table.
     */
    protected function createCategoriesTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='categories'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) UNIQUE,
                    description TEXT,
                    parent_id INTEGER,
                    level INTEGER DEFAULT 0,
                    image_url VARCHAR(255),
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME,
                    updated_at DATETIME,
                    deleted_at DATETIME
                )
            ');
        }
    }

    /**
     * Create brands table.
     */
    protected function createBrandsTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='brands'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE brands (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) UNIQUE,
                    description TEXT,
                    logo_url VARCHAR(255),
                    website_url VARCHAR(255),
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME,
                    updated_at DATETIME,
                    deleted_at DATETIME
                )
            ');
        }
    }

    /**
     * Create stores table.
     */
    protected function createStoresTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='stores'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE stores (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) UNIQUE,
                    description TEXT,
                    logo_url VARCHAR(255),
                    website_url VARCHAR(255),
                    country_code VARCHAR(2),
                    supported_countries TEXT,
                    is_active BOOLEAN DEFAULT 1,
                    priority INTEGER DEFAULT 0,
                    affiliate_base_url TEXT,
                    affiliate_code VARCHAR(255),
                    api_config TEXT,
                    currency_id INTEGER,
                    contact_email VARCHAR(255),
                    created_at DATETIME,
                    updated_at DATETIME,
                    deleted_at DATETIME
                )
            ');
        }
    }

    /**
     * Create languages table.
     */
    protected function createLanguagesTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='languages'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE languages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code VARCHAR(10) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');
        }
    }

    /**
     * Create currencies table.
     */
    protected function createCurrenciesTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='currencies'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE currencies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code VARCHAR(10) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    symbol VARCHAR(10),
                    exchange_rate DECIMAL(10,4) DEFAULT 1.0000,
                    decimal_places INTEGER DEFAULT 2,
                    is_active BOOLEAN DEFAULT 1,
                    is_default BOOLEAN DEFAULT 0,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');
        }
    }

    /**
     * Create migrations table.
     */
    protected function createMigrationsTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL
                )
            ');
        }
    }

    /**
     * Create password_reset_tokens table.
     */
    protected function createPasswordResetTokensTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='password_reset_tokens'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE password_reset_tokens (
                    email VARCHAR(255) PRIMARY KEY,
                    token VARCHAR(255) NOT NULL,
                    created_at DATETIME
                )
            ');
        }
    }

    /**
     * Create personal_access_tokens table.
     */
    protected function createPersonalAccessTokensTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='personal_access_tokens'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE personal_access_tokens (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tokenable_type VARCHAR(255) NOT NULL,
                    tokenable_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    abilities TEXT,
                    last_used_at DATETIME,
                    expires_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');
        }
    }

    /**
     * Create orders table.
     */
    protected function createOrdersTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='orders'");
        if (empty($exists)) {
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
                    shipping_address TEXT,
                    billing_address TEXT,
                    notes TEXT,
                    order_date DATETIME,
                    shipped_at DATETIME,
                    delivered_at DATETIME,
                    weight DECIMAL(10,2) NULL,
                    dimensions TEXT NULL,
                    tracking_number VARCHAR(255) NULL,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');
        }
    }

    /**
     * Create price_offers table.
     */
    protected function createPriceOffersTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='price_offers'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE price_offers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    product_sku VARCHAR(255),
                    store_id INTEGER NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT "USD",
                    product_url TEXT,
                    affiliate_url TEXT,
                    in_stock BOOLEAN DEFAULT 1,
                    stock_quantity INTEGER DEFAULT 0,
                    condition VARCHAR(255),
                    rating DECIMAL(2,1),
                    reviews_count INTEGER DEFAULT 0,
                    image_url TEXT,
                    specifications TEXT,
                    description TEXT,
                    status VARCHAR(50) DEFAULT "active",
                    is_available BOOLEAN DEFAULT 1,
                    original_price DECIMAL(10,2),
                    expires_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
                )
            ');
        }
    }

    /**
     * Create price_histories table.
     */
    protected function createPriceHistoriesTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='price_histories'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE price_histories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    recorded_at DATETIME,
                    currency VARCHAR(3) DEFAULT "USD",
                    old_price DECIMAL(10,2),
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                )
            ');
        }
    }

    /**
     * Create order_items table.
     */
    protected function createOrderItemsTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='order_items'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE order_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    quantity INTEGER NOT NULL DEFAULT 1,
                    unit_price DECIMAL(10,2) NOT NULL,
                    total DECIMAL(10,2) NOT NULL,
                    subtotal DECIMAL(10,2),
                    price DECIMAL(10,2),
                    product_details TEXT,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                )
            ');
        }
    }

    /**
     * Create reviews table.
     */
    protected function createReviewsTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='reviews'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE reviews (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    title VARCHAR(255),
                    content TEXT,
                    rating INTEGER NOT NULL DEFAULT 5,
                    is_verified_purchase BOOLEAN DEFAULT 0,
                    is_approved BOOLEAN DEFAULT 0,
                    helpful_votes TEXT,
                    helpful_count INTEGER DEFAULT 0,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                )
            ');
        }
    }

    /**
     * Create wishlists table.
     */
    protected function createWishlistsTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='wishlists'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE wishlists (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    notes TEXT,
                    created_at DATETIME,
                    updated_at DATETIME,
                    deleted_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                    UNIQUE(user_id, product_id)
                )
            ');
        }
    }

    /**
     * Create exchange_rates table.
     */
    protected function createExchangeRatesTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='exchange_rates'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE exchange_rates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    from_currency VARCHAR(3) NOT NULL,
                    to_currency VARCHAR(3) NOT NULL,
                    rate DECIMAL(20,10) NOT NULL,
                    source VARCHAR(255) DEFAULT "manual",
                    fetched_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    UNIQUE(from_currency, to_currency)
                )
            ');
        }
    }

    /**
     * Create addresses table.
     */
    protected function createAddressesTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='addresses'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE addresses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    street VARCHAR(255) NOT NULL,
                    line1 VARCHAR(255),
                    city VARCHAR(255) NOT NULL,
                    state VARCHAR(255),
                    zip_code VARCHAR(20) NOT NULL,
                    country VARCHAR(2) NOT NULL,
                    is_default BOOLEAN DEFAULT 0,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ');
        }
    }

    /**
     * Create payments table.
     */
    protected function createPaymentsTable(string $connection): void
    {
        $exists = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name='payments'");
        if (empty($exists)) {
            DB::connection($connection)->statement('
                CREATE TABLE payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    payment_method_id INTEGER,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(3) DEFAULT "USD",
                    status VARCHAR(50) DEFAULT "pending",
                    method VARCHAR(50),
                    gateway VARCHAR(50),
                    transaction_id VARCHAR(255) UNIQUE,
                    gateway_response TEXT,
                    metadata TEXT,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
                )
            ');
        }
    }

    /**
     * Tear down the database after testing.
     */
    protected function tearDownDatabase(): void
    {
        // Restore any error handlers that might have been set
        restore_error_handler();
        restore_exception_handler();

        // Clear any database connections
        DB::purge();
    }
}
