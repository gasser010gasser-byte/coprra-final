<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stores', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('logo_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->json('supported_countries')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->text('affiliate_base_url')->nullable();
            $table->string('affiliate_code')->nullable();
            $table->json('api_config')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
