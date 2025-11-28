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
        Schema::create('ai_cost_logs', static function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->index();
            $table->string('operation');
            $table->integer('tokens_used')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['service_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_cost_logs');
    }
};
