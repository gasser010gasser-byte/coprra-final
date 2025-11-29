<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix order_items table - add missing columns
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', static function (Blueprint $table): void {
                if (!Schema::hasColumn('order_items', 'subtotal')) {
                    $table->decimal('subtotal', 10, 2)->nullable()->after('price');
                }
                if (!Schema::hasColumn('order_items', 'unit_price')) {
                    $table->decimal('unit_price', 10, 2)->nullable()->after('subtotal');
                }
            });
        }

        // Fix products table - add rating column
        if (Schema::hasTable('products')) {
            Schema::table('products', static function (Blueprint $table): void {
                if (!Schema::hasColumn('products', 'rating')) {
                    $table->decimal('rating', 3, 1)->nullable()->after('stock_quantity');
                }
            });
        }

        // Fix price_offers table - add missing columns
        if (Schema::hasTable('price_offers')) {
            Schema::table('price_offers', static function (Blueprint $table): void {
                if (!Schema::hasColumn('price_offers', 'shipping_cost')) {
                    $table->decimal('shipping_cost', 8, 2)->nullable()->after('is_available');
                }
                if (!Schema::hasColumn('price_offers', 'delivery_time')) {
                    $table->string('delivery_time')->nullable()->after('shipping_cost');
                }
                if (!Schema::hasColumn('price_offers', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('delivery_time');
                }
            });
        }

        // Fix stores table - add email column (alias for contact_email)
        if (Schema::hasTable('stores')) {
            Schema::table('stores', static function (Blueprint $table): void {
                if (!Schema::hasColumn('stores', 'email')) {
                    $table->string('email')->nullable()->after('contact_email');
                }
            });
        }

        // Fix orders table - add weight column if missing
        if (Schema::hasTable('orders')) {
            Schema::table('orders', static function (Blueprint $table): void {
                if (!Schema::hasColumn('orders', 'weight')) {
                    $table->decimal('weight', 8, 2)->nullable()->after('delivered_at');
                }
                if (!Schema::hasColumn('orders', 'dimensions')) {
                    $table->json('dimensions')->nullable()->after('weight');
                }
            });
        }

        // Fix payments table - add missing columns
        if (Schema::hasTable('payments')) {
            Schema::table('payments', static function (Blueprint $table): void {
                if (!Schema::hasColumn('payments', 'currency')) {
                    $table->string('currency', 3)->default('USD')->after('amount');
                }
                if (!Schema::hasColumn('payments', 'method')) {
                    $table->string('method', 50)->nullable()->after('currency');
                }
                if (!Schema::hasColumn('payments', 'gateway')) {
                    $table->string('gateway', 50)->nullable()->after('method');
                }
                if (!Schema::hasColumn('payments', 'metadata')) {
                    $table->json('metadata')->nullable()->after('gateway_response');
                }
            });
        }

        // Fix wishlists table - add deleted_at column
        if (Schema::hasTable('wishlists')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                if (!Schema::hasColumn('wishlists', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', static function (Blueprint $table): void {
                $table->dropColumn(['subtotal', 'unit_price']);
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', static function (Blueprint $table): void {
                $table->dropColumn('rating');
            });
        }

        if (Schema::hasTable('price_offers')) {
            Schema::table('price_offers', static function (Blueprint $table): void {
                $table->dropColumn(['shipping_cost', 'delivery_time', 'expires_at']);
            });
        }

        if (Schema::hasTable('stores')) {
            Schema::table('stores', static function (Blueprint $table): void {
                $table->dropColumn('email');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', static function (Blueprint $table): void {
                $table->dropColumn(['weight', 'dimensions']);
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', static function (Blueprint $table): void {
                $table->dropColumn(['currency', 'method', 'gateway', 'metadata']);
            });
        }

        if (Schema::hasTable('wishlists')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                if (Schema::hasColumn('wishlists', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};

