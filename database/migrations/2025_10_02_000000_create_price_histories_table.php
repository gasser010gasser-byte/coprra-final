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
        if (! Schema::hasTable('price_histories')) {
            Schema::create('price_histories', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained()->onDelete('cascade');
                $table->decimal('price', 10, 2);
                $table->decimal('old_price', 10, 2)->nullable();
                $table->string('currency', 3)->default('USD');
                $table->timestamp('recorded_at')->useCurrent();
                // Keep timestamps disabled to match model's $timestamps = false
                // $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
