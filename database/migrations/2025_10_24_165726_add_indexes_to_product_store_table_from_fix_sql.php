<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Note: product_id and store_id already have indexes created by foreign key constraints
        // We only need to add the currency_id index if it doesn't exist

        if (! $this->indexExists('product_store', 'idx_product_store_currency_id')) {
            Schema::table('product_store', static function (Blueprint $table): void {
                $table->index(['currency_id'], 'idx_product_store_currency_id');
            });
        }

        // Foreign key constraints on product_id and store_id automatically create indexes
        // so we don't need to create them manually
    }

    public function down(): void
    {
        // Note: In MySQL, foreign key constraints automatically create indexes that cannot be dropped
        // In SQLite, we can drop indexes even if they're used by foreign keys
        $driver = Schema::getConnection()->getDriverName();

        if ('sqlite' === $driver) {
            // For SQLite, we can safely drop all indexes
            if ($this->indexExists('product_store', 'idx_product_store_currency_id')) {
                Schema::table('product_store', static function (Blueprint $table): void {
                    $table->dropIndex('idx_product_store_currency_id');
                });
            }
        }
        // For MySQL and other databases, only drop indexes not required by foreign keys
        // The currency_id foreign key constraint may also create an index, so we need to be careful
        // We'll skip dropping indexes that might be required by foreign key constraints
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ('sqlite' === $driver) {
            $results = $connection->select("PRAGMA index_list('".$table."')");

            /** @var object $rowObj */
            foreach ($results as $rowObj) {
                $row = (array) $rowObj;
                if (isset($row['name']) && $row['name'] === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ('mysql' === $driver) {
            $results = $connection->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return ! empty($results);
        }

        return false;
    }
};
