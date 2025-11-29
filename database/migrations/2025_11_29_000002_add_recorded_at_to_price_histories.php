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
                if (!Schema::hasColumn('price_histories', 'recorded_at')) {
                    // Check if captured_at or effective_date exists and migrate data
                    if (Schema::hasColumn('price_histories', 'captured_at')) {
                        $table->timestamp('recorded_at')->nullable()->after('price');
                        DB::statement('UPDATE price_histories SET recorded_at = captured_at WHERE recorded_at IS NULL');
                    } elseif (Schema::hasColumn('price_histories', 'effective_date')) {
                        $table->timestamp('recorded_at')->nullable()->after('price');
                        DB::statement('UPDATE price_histories SET recorded_at = effective_date WHERE recorded_at IS NULL');
                    } else {
                        $table->timestamp('recorded_at')->useCurrent()->after('price');
                    }
                }
                if (!Schema::hasColumn('price_histories', 'currency')) {
                    $table->string('currency', 3)->default('USD')->after('recorded_at');
                }
            });
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
            });
        }
    }
};

