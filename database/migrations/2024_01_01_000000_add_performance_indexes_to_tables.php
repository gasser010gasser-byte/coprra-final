<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to products table if exists
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! $this->indexExists('products', 'products_category_id_index')) {
                    $table->index('category_id');
                }
                if (! $this->indexExists('products', 'products_brand_id_index')) {
                    $table->index('brand_id');
                }
                if (! $this->indexExists('products', 'products_is_active_index')) {
                    $table->index('is_active');
                }
                if (! $this->indexExists('products', 'products_created_at_index')) {
                    $table->index('created_at');
                }
                // Compound index for common queries
                if (! $this->indexExists('products', 'products_category_id_is_active_index')) {
                    $table->index(['category_id', 'is_active']);
                }
            });
        }

        // Add indexes to price_offers table if exists
        if (Schema::hasTable('price_offers')) {
            Schema::table('price_offers', function (Blueprint $table) {
                if (! $this->indexExists('price_offers', 'price_offers_product_id_index')) {
                    $table->index('product_id');
                }
                if (! $this->indexExists('price_offers', 'price_offers_store_id_index')) {
                    $table->index('store_id');
                }
                if (! $this->indexExists('price_offers', 'price_offers_in_stock_index')) {
                    $table->index('in_stock');
                }
                // Compound index for price searches
                if (! $this->indexExists('price_offers', 'price_offers_product_id_in_stock_index')) {
                    $table->index(['product_id', 'in_stock']);
                }
            });
        }

        // Add indexes to orders table if exists
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! $this->indexExists('orders', 'orders_user_id_index')) {
                    $table->index('user_id');
                }
                if (! $this->indexExists('orders', 'orders_status_index')) {
                    $table->index('status');
                }
                if (! $this->indexExists('orders', 'orders_created_at_index')) {
                    $table->index('created_at');
                }
                // Compound index for user orders
                if (! $this->indexExists('orders', 'orders_user_id_status_index')) {
                    $table->index(['user_id', 'status']);
                }
            });
        }

        // Add indexes to order_items table if exists
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! $this->indexExists('order_items', 'order_items_order_id_index')) {
                    $table->index('order_id');
                }
                if (! $this->indexExists('order_items', 'order_items_product_id_index')) {
                    $table->index('product_id');
                }
            });
        }

        // Add indexes to price_alerts table if exists
        if (Schema::hasTable('price_alerts')) {
            Schema::table('price_alerts', function (Blueprint $table) {
                if (! $this->indexExists('price_alerts', 'price_alerts_user_id_index')) {
                    $table->index('user_id');
                }
                if (! $this->indexExists('price_alerts', 'price_alerts_product_id_index')) {
                    $table->index('product_id');
                }
                if (! $this->indexExists('price_alerts', 'price_alerts_is_active_index')) {
                    $table->index('is_active');
                }
                // Compound index for user alerts
                if (! $this->indexExists('price_alerts', 'price_alerts_user_id_is_active_index')) {
                    $table->index(['user_id', 'is_active']);
                }
            });
        }

        // Add indexes to wishlists table if exists
        if (Schema::hasTable('wishlists')) {
            Schema::table('wishlists', function (Blueprint $table) {
                if (! $this->indexExists('wishlists', 'wishlists_user_id_index')) {
                    $table->index('user_id');
                }
                if (! $this->indexExists('wishlists', 'wishlists_product_id_index')) {
                    $table->index('product_id');
                }
                // Compound unique index
                if (! $this->indexExists('wishlists', 'wishlists_user_id_product_id_index')) {
                    $table->index(['user_id', 'product_id']);
                }
            });
        }

        // Add indexes to reviews table if exists
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (! $this->indexExists('reviews', 'reviews_product_id_index')) {
                    $table->index('product_id');
                }
                if (! $this->indexExists('reviews', 'reviews_user_id_index')) {
                    $table->index('user_id');
                }
                if (! $this->indexExists('reviews', 'reviews_is_approved_index')) {
                    $table->index('is_approved');
                }
                // Compound index for product reviews
                if (! $this->indexExists('reviews', 'reviews_product_id_is_approved_index')) {
                    $table->index(['product_id', 'is_approved']);
                }
            });
        }

        // Add indexes to users table (additional) if exists
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! $this->indexExists('users', 'users_created_at_index')) {
                    $table->index('created_at');
                }
            });
        }

        // Add indexes to stores table if exists
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                if (! $this->indexExists('stores', 'stores_is_active_index')) {
                    $table->index('is_active');
                }
                if (! $this->indexExists('stores', 'stores_country_code_index')) {
                    $table->index('country_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from products table
        Schema::table('products', static function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['category_id', 'is_active']);
        });

        // Drop indexes from price_offers table
        Schema::table('price_offers', static function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['in_stock']);
            $table->dropIndex(['product_id', 'in_stock']);
        });

        // Drop indexes from orders table if exists
        if (Schema::hasTable('orders')) {
            Schema::table('orders', static function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropIndex(['status']);
                $table->dropIndex(['created_at']);
                $table->dropIndex(['user_id', 'status']);
            });
        }

        // Drop indexes from order_items table if exists
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', static function (Blueprint $table) {
                $table->dropIndex(['order_id']);
                $table->dropIndex(['product_id']);
            });
        }

        // Drop indexes from price_alerts table
        Schema::table('price_alerts', static function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['user_id', 'is_active']);
        });

        // Drop indexes from wishlists table
        Schema::table('wishlists', static function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['user_id', 'product_id']);
        });

        // Drop indexes from reviews table
        Schema::table('reviews', static function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_approved']);
            $table->dropIndex(['product_id', 'is_approved']);
        });

        // Drop indexes from users table
        Schema::table('users', static function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        // Drop indexes from stores table
        Schema::table('stores', static function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['country_code']);
        });
    }

    /**
     * Check if an index exists.
     *
     * Laravel 11+ removed Doctrine DBAL, so we use raw queries instead.
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        // SQLite: use PRAGMA introspection
        if ($connection instanceof SQLiteConnection) {
            $rows = $connection->select('PRAGMA index_list("'.$table.'")');
            foreach ($rows as $row) {
                $name = is_array($row) ? ($row['name'] ?? null) : ($row->name ?? null);
                if ($name === $index) {
                    return true;
                }
            }

            return false;
        }

        // MySQL/MariaDB: use information_schema
        try {
            $database = $connection->getDatabaseName();
            $indexes = $connection->select(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
                [$database, $table, $index]
            );

            return count($indexes) > 0;
        } catch (\Exception $e) {
            // If query fails, assume index doesn't exist (safe to try creating)
            return false;
        }
    }
};
