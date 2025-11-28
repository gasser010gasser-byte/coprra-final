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
        Schema::create('scraper_jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id')->index();
            $table->integer('job_number');
            $table->text('url');
            $table->string('store_adapter')->nullable();
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued')->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'job_number']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_jobs');
    }
};
