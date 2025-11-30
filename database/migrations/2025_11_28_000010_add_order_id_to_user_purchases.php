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
        Schema::table('user_purchases', static function (Blueprint $table): void {
            if (! Schema::hasColumn('user_purchases', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('product_id')->constrained()->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_purchases', static function (Blueprint $table): void {
            if (Schema::hasColumn('user_purchases', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }
        });
    }
};
