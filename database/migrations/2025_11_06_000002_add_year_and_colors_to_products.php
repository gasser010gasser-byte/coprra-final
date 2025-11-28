<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Add year_of_manufacture and available_colors fields to products table
     * for enhanced product information and comparison filtering.
     */
    public function up(): void
    {
        Schema::table('products', static function (Blueprint $table): void {
            $table->year('year_of_manufacture')->nullable()->after('description');
            $table->json('available_colors')->nullable()->after('year_of_manufacture');

            // Add index for filtering by year
            $table->index('year_of_manufacture');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table): void {
            $table->dropIndex(['year_of_manufacture']);
            $table->dropColumn(['year_of_manufacture', 'available_colors']);
        });
    }
};
