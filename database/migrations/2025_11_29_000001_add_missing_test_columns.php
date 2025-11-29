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
        // Add missing columns to price_offers table
        if (Schema::hasTable('price_offers')) {
            Schema::table('price_offers', static function (Blueprint $table): void {
                if (!Schema::hasColumn('price_offers', 'description')) {
                    $table->text('description')->nullable()->after('specifications');
                }
                if (!Schema::hasColumn('price_offers', 'status')) {
                    $table->string('status', 50)->default('active')->after('description');
                }
            });
        }

        // Add missing columns to price_histories table
        if (Schema::hasTable('price_histories')) {
            Schema::table('price_histories', static function (Blueprint $table): void {
                if (!Schema::hasColumn('price_histories', 'recorded_at')) {
                    // Check if we should add it or if captured_at/effective_date exists
                    if (!Schema::hasColumn('price_histories', 'recorded_at')) {
                        $table->timestamp('recorded_at')->nullable()->after('price');
                    }
                }
                if (!Schema::hasColumn('price_histories', 'currency')) {
                    $table->string('currency', 3)->default('USD')->after('recorded_at');
                }
                if (!Schema::hasColumn('price_histories', 'old_price')) {
                    $table->decimal('old_price', 10, 2)->nullable()->after('currency');
                }
            });
        }

        // Add missing columns to users table for ban tracking
        if (Schema::hasTable('users')) {
            Schema::table('users', static function (Blueprint $table): void {
                if (!Schema::hasColumn('users', 'banned_by')) {
                    $table->foreignId('banned_by')->nullable()->after('banned_at')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('users', 'unbanned_at')) {
                    $table->timestamp('unbanned_at')->nullable()->after('banned_by');
                }
                if (!Schema::hasColumn('users', 'unbanned_by')) {
                    $table->foreignId('unbanned_by')->nullable()->after('unbanned_at')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('price_offers')) {
            Schema::table('price_offers', static function (Blueprint $table): void {
                $table->dropColumn(['description', 'status']);
            });
        }

        if (Schema::hasTable('price_histories')) {
            Schema::table('price_histories', static function (Blueprint $table): void {
                $columnsToDrop = [];
                if (Schema::hasColumn('price_histories', 'recorded_at')) {
                    $columnsToDrop[] = 'recorded_at';
                }
                if (Schema::hasColumn('price_histories', 'currency')) {
                    $columnsToDrop[] = 'currency';
                }
                if (Schema::hasColumn('price_histories', 'old_price')) {
                    $columnsToDrop[] = 'old_price';
                }
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', static function (Blueprint $table): void {
                $table->dropForeign(['banned_by']);
                $table->dropForeign(['unbanned_by']);
                $table->dropColumn(['banned_by', 'unbanned_at', 'unbanned_by']);
            });
        }
    }
};

