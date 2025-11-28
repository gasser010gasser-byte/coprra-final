<?php

declare(strict_types=1);

namespace App\Services\Performance;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;

final readonly class DatabaseOptimizerService
{
    public function __construct(
        private OutputStyle $output,
        private DatabaseManager $database,
        private Kernel $kernel
    ) {
    }

    public function optimizeDatabase(): void
    {
        $this->executeOptimizationTask(
            '🗄️  Optimizing database...',
            function (): void {
                $tableNames = $this->getTableNames();
                $optimized = 0;
                $errors = [];

                foreach ($tableNames as $tableName) {
                    try {
                        $this->database->connection()->statement("OPTIMIZE TABLE `{$tableName}`");
                        ++$optimized;
                    } catch (\Exception $e) {
                        $errors[] = "Could not optimize table `{$tableName}`: {$e->getMessage()}";
                    }
                }

                foreach ($errors as $error) {
                    $this->output->warn("  - {$error}");
                }

                $this->output->writeln("  ✓ Optimized {$optimized} tables");
            },
            'Database optimization completed'
        );
    }

    public function analyzeDatabase(): void
    {
        $this->kernel->call('db:analyze');
    }

    /**
     * Get all table names from the database.
     *
     * @return array<string>
     */
    private function getTableNames(): array
    {
        $tables = $this->database->connection()->select('SHOW TABLES');
        $database = $this->database->connection()->getDatabaseName();
        $tableKey = "Tables_in_{$database}";
        $tableNames = [];
        foreach ($tables as $table) {
            if (\is_object($table) && isset($table->{$tableKey})) {
                $tableNames[] = $table->{$tableKey};
            }
        }

        return $tableNames;
    }

    /**
     * Execute a single optimization task.
     */
    private function executeOptimizationTask(string $title, callable $task, string $successMessage): void
    {
        $this->output->info($title);

        try {
            $task();
            $this->output->writeln('  ✓ '.$successMessage);
        } catch (\Exception $e) {
            $this->output->warn('  ✗ Failed: '.$e->getMessage());
        }

        $this->output->newLine();
    }
}
