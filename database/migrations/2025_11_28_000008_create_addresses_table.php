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
        if (! Schema::hasTable('addresses')) {
            Schema::create('addresses', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('street');
                $table->string('line1')->nullable();
                $table->string('city');
                $table->string('state')->nullable();
                $table->string('zip_code', 20);
                $table->string('country', 2);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        } else {
            // Add missing columns if table exists
            Schema::table('addresses', static function (Blueprint $table): void {
                if (! Schema::hasColumn('addresses', 'line1')) {
                    $table->string('line1')->nullable()->after('street');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
