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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('price_offers')) {
            Schema::table('price_offers', static function (Blueprint $table): void {
                if (Schema::hasColumn('price_offers', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('price_offers', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};

