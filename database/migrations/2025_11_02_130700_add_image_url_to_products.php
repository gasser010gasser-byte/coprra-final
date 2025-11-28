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
        Schema::table('products', static function (Blueprint $table) {
            $table->string('image_url', 500)->nullable()->after('image');
            $table->string('currency', 10)->default('EGP')->after('price');
            $table->text('specifications')->nullable()->after('description');
            $table->text('features')->nullable()->after('specifications');
            $table->string('url', 500)->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table) {
            $table->dropColumn(['image_url', 'currency', 'specifications', 'features', 'url']);
        });
    }
};
