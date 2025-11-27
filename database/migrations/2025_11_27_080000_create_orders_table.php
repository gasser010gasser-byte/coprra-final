<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('order_number')->unique();

            $table->decimal('subtotal', 10, 2);

            $table->decimal('tax_amount', 10, 2)->default(0);

            $table->decimal('shipping_amount', 10, 2)->default(0);

            $table->decimal('discount_amount', 10, 2)->default(0);

            $table->decimal('total_amount', 10, 2);

            $table->string('status')->default('pending'); // e.g., pending, processing, shipped, delivered, cancelled

            $table->string('currency', 3)->default('USD');

            $table->json('shipping_address')->nullable();

            $table->json('billing_address')->nullable();

            $table->text('notes')->nullable();

            $table->dateTime('order_date')->nullable();

            $table->dateTime('shipped_at')->nullable();

            $table->dateTime('delivered_at')->nullable();

            $table->timestamps();

            $table->index('status');

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
