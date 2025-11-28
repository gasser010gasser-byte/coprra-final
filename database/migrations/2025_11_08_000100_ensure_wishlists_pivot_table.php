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
        if (! Schema::hasTable('wishlists')) {
            Schema::create('wishlists', static function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['user_id', 'product_id'], 'wishlists_user_product_unique');
            });

            return;
        }

        if (! Schema::hasColumn('wishlists', 'user_id')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('wishlists', 'product_id')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                $table->foreignId('product_id')->after('user_id')->constrained()->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('wishlists', 'created_at')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasColumn('wishlists', 'updated_at')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (! Schema::hasColumn('wishlists', 'deleted_at')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (! $this->indexExists('wishlists', 'wishlists_user_product_unique')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                $table->unique(['user_id', 'product_id'], 'wishlists_user_product_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('wishlists', 'wishlists_user_product_unique')) {
            Schema::table('wishlists', static function (Blueprint $table): void {
                $table->dropUnique('wishlists_user_product_unique');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        if (! method_exists($connection, 'getDoctrineSchemaManager')) {
            return true;
        }

        try {
            /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $schemaManager */
            $schemaManager = $connection->getDoctrineSchemaManager();
            $indexes = $schemaManager->listTableIndexes($connection->getTablePrefix().$table);

            return array_key_exists($indexName, $indexes);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
