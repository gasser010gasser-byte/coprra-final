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
        if (Schema::hasTable('stores')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                // For SQLite, we can't easily modify unique constraints
                // So we'll skip the migration for SQLite - the column is already nullable
                // The unique constraint will be handled at application level
                // No action needed for SQLite
            } else {
                // For other databases, use standard approach
                Schema::table('stores', static function (Blueprint $table): void {
                    if (Schema::hasColumn('stores', 'slug')) {
                        try {
                            $table->dropUnique(['slug']);
                        } catch (\Exception $e) {
                            // Index might not exist, continue
                        }
                        $table->string('slug')->nullable()->unique()->change();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stores')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver !== 'sqlite') {
                Schema::table('stores', static function (Blueprint $table): void {
                    if (Schema::hasColumn('stores', 'slug')) {
                        try {
                            $table->dropUnique(['slug']);
                        } catch (\Exception $e) {
                            // Index might not exist, continue
                        }
                        $table->string('slug')->nullable(false)->unique()->change();
                    }
                });
            }
        }
    }
};
