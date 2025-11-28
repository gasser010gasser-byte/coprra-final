<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * This migration fixes schema mismatches discovered during deployment:
     * 1. Adds missing 'description' column to brands table
     * 2. Adds missing 'deleted_at' column to stores table (SoftDeletes support)
     */
    public function up(): void
    {
        // Fix brands table - add description column if it doesn't exist
        if (Schema::hasTable('brands') && ! Schema::hasColumn('brands', 'description')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }

        // Fix stores table - add deleted_at column for SoftDeletes if it doesn't exist
        if (Schema::hasTable('stores') && ! Schema::hasColumn('stores', 'deleted_at')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Fix categories table - add deleted_at if missing
        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'deleted_at')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Fix products table - ensure deleted_at exists
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove description from brands if it exists
        if (Schema::hasTable('brands') && Schema::hasColumn('brands', 'description')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        // Remove soft deletes from stores
        if (Schema::hasTable('stores') && Schema::hasColumn('stores', 'deleted_at')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        // Remove soft deletes from categories
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'deleted_at')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        // Remove soft deletes from products
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
