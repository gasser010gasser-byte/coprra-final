<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Fix: Make language_id and currency_id nullable to prevent 500 errors
     * when users change country/currency/language independently.
     */
    public function up(): void
    {
        Schema::table('user_locale_settings', static function (Blueprint $table): void {
            $table->foreignId('language_id')->nullable()->change();
            $table->foreignId('currency_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_locale_settings', static function (Blueprint $table): void {
            $table->foreignId('language_id')->nullable(false)->change();
            $table->foreignId('currency_id')->nullable(false)->change();
        });
    }
};
