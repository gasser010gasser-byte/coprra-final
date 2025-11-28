<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('countries', static function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique(); // EG, US, SA, etc.
            $table->string('name'); // Egypt, United States, Saudi Arabia
            $table->string('native_name')->nullable(); // مصر, الولايات المتحدة
            $table->foreignId('language_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
            $table->string('flag_emoji', 10)->nullable(); // 🇪🇬, 🇺🇸
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
