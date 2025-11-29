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
        if (Schema::hasTable('users')) {
            Schema::table('users', static function (Blueprint $table): void {
                if (!Schema::hasColumn('users', 'banned_by')) {
                    $table->foreignId('banned_by')->nullable()->after('banned_at')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('users', 'unbanned_at')) {
                    $table->timestamp('unbanned_at')->nullable()->after('ban_expires_at');
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
        if (Schema::hasTable('users')) {
            Schema::table('users', static function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'banned_by')) {
                    $table->dropForeign(['banned_by']);
                    $table->dropColumn('banned_by');
                }
                if (Schema::hasColumn('users', 'unbanned_at')) {
                    $table->dropColumn('unbanned_at');
                }
                if (Schema::hasColumn('users', 'unbanned_by')) {
                    $table->dropForeign(['unbanned_by']);
                    $table->dropColumn('unbanned_by');
                }
            });
        }
    }
};

