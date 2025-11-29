<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('price_histories')) {
            Schema::table('price_histories', static function (Blueprint $table): void {
                // Add recorded_at if it doesn't exist
                if (!Schema::hasColumn('price_histories', 'recorded_at')) {
                    // Check if we have effective_date or captured_at to migrate from
                    if (Schema::hasColumn('price_histories', 'effective_date')) {
                        $table->timestamp('recorded_at')->nullable()->after('price');
                        DB::statement('UPDATE price_histories SET recorded_at = effective_date WHERE recorded_at IS NULL');
                    } elseif (Schema::hasColumn('price_histories', 'captured_at')) {
                        $table->timestamp('recorded_at')->nullable()->after('price');
                        DB::statement('UPDATE price_histories SET recorded_at = captured_at WHERE recorded_at IS NULL');
                    } else {
                        $table->timestamp('recorded_at')->useCurrent()->after('price');
                    }
                }

                // Handle effective_date column - migrate to recorded_at if needed
                if (Schema::hasColumn('price_histories', 'effective_date') && !Schema::hasColumn('price_histories', 'recorded_at')) {
                    // If we have effective_date but no recorded_at, copy data and then we can ignore effective_date
                    // The column will remain but won't be required for new inserts
                    DB::statement('UPDATE price_histories SET recorded_at = effective_date WHERE recorded_at IS NULL');
                }

                // Add currency if missing
                if (!Schema::hasColumn('price_histories', 'currency')) {
                    $table->string('currency', 3)->default('USD')->after('recorded_at');
                }

                // Add old_price if missing (used by model)
                if (!Schema::hasColumn('price_histories', 'old_price')) {
                    $table->decimal('old_price', 10, 2)->nullable()->after('price');
                }
            });

            // For SQLite, we need to handle the NOT NULL constraint differently
            // Since SQLite has limited ALTER TABLE support, we'll ensure the model handles it
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('price_histories')) {
            Schema::table('price_histories', static function (Blueprint $table): void {
                if (Schema::hasColumn('price_histories', 'recorded_at')) {
                    $table->dropColumn('recorded_at');
                }
                if (Schema::hasColumn('price_histories', 'currency')) {
                    $table->dropColumn('currency');
                }
                if (Schema::hasColumn('price_histories', 'old_price')) {
                    $table->dropColumn('old_price');
                }
            });
        }
    }
};

