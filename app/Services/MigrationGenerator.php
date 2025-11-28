<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Service responsible for generating Laravel migration files.
 */
final class MigrationGenerator
{
    public function __construct(
        private readonly ColumnDefinitionParser $columnParser
    ) {
    }

    /**
     * Generate a migration file for the given operations.
     *
     * @param array<int, array{table:string,index:string,columns:array<int,string>}>                          $indexOps
     * @param array<int, array{table:string,column:string,definition:string,charset:?string,collate:?string}> $modifyOps
     * @param array<string, array<string, string>>                                                            $originalTypesMap
     */
    public function generateMigration(
        array $indexOps,
        array $modifyOps,
        array $originalTypesMap,
        string $outputDir
    ): string {
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp.'_fix_database_issues.php';
        $path = $outputDir.'/'.$filename;

        $content = $this->buildMigrationContent($indexOps, $modifyOps, $originalTypesMap);

        File::put($path, $content);

        return $path;
    }

    /**
     * Build the complete migration file content.
     *
     * @param array<int, array{table:string,index:string,columns:array<int,string>}>                          $indexOps
     * @param array<int, array{table:string,column:string,definition:string,charset:?string,collate:?string}> $modifyOps
     * @param array<string, array<string, string>>                                                            $originalTypesMap
     */
    private function buildMigrationContent(
        array $indexOps,
        array $modifyOps,
        array $originalTypesMap
    ): string {
        $upContent = $this->generateUpMethod($indexOps, $modifyOps);
        $downContent = $this->generateDownMethod($indexOps, $modifyOps, $originalTypesMap);

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
{$upContent}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downContent}
    }
};
PHP;
    }

    /**
     * Generate the up() method content.
     *
     * @param array<int, array{table:string,index:string,columns:array<int,string>}>                          $indexOps
     * @param array<int, array{table:string,column:string,definition:string,charset:?string,collate:?string}> $modifyOps
     */
    private function generateUpMethod(array $indexOps, array $modifyOps): string
    {
        $content = '';

        if (! empty($indexOps)) {
            $content .= $this->renderIndexMigration($indexOps, 'up');
        }

        if (! empty($modifyOps)) {
            $content .= $this->renderModifyMigration($modifyOps, [], 'up');
        }

        return $content;
    }

    /**
     * Generate the down() method content.
     *
     * @param array<int, array{table:string,index:string,columns:array<int,string>}>                          $indexOps
     * @param array<int, array{table:string,column:string,definition:string,charset:?string,collate:?string}> $modifyOps
     * @param array<string, array<string, string>>                                                            $originalTypesMap
     */
    private function generateDownMethod(
        array $indexOps,
        array $modifyOps,
        array $originalTypesMap
    ): string {
        $content = '';

        if (! empty($modifyOps)) {
            $content .= $this->renderModifyMigration($modifyOps, $originalTypesMap, 'down');
        }

        if (! empty($indexOps)) {
            $content .= $this->renderIndexMigration($indexOps, 'down');
        }

        return $content;
    }

    /**
     * Render index operations for migration.
     *
     * @param array<int, array{table:string,index:string,columns:array<int,string>}> $indexOps
     */
    private function renderIndexMigration(array $indexOps, string $direction): string
    {
        $content = '';
        $groupedByTable = $this->groupOperationsByTable($indexOps);

        foreach ($groupedByTable as $table => $ops) {
            $content .= "        Schema::table('{$table}', function (Blueprint \$table) {\n";

            foreach ($ops as $op) {
                $colsStr = "'".implode("', '", $op['columns'])."'";

                if ('up' === $direction) {
                    $content .= "            \$table->index([{$colsStr}], '{$op['index']}');\n";
                } else {
                    $content .= "            \$table->dropIndex('{$op['index']}');\n";
                }
            }

            $content .= "        });\n\n";
        }

        return $content;
    }

    /**
     * Render column modification operations for migration.
     *
     * @param array<int, array{table:string,column:string,definition:string,charset:?string,collate:?string}> $modifyOps
     * @param array<string, array<string, string>>                                                            $originalTypesMap
     */
    private function renderModifyMigration(
        array $modifyOps,
        array $originalTypesMap,
        string $direction
    ): string {
        $content = '';
        $groupedByTable = $this->groupOperationsByTable($modifyOps);

        foreach ($groupedByTable as $table => $ops) {
            $schemaOps = [];
            $rawOps = [];

            foreach ($ops as $op) {
                $column = $op['column'];

                if ('up' === $direction) {
                    $definition = $op['definition'];
                } else {
                    $definition = $originalTypesMap[$table][$column] ?? $op['definition'];
                }

                $parsed = $this->columnParser->parseColumnDefinition($definition);

                if (null !== $parsed['schema']) {
                    $schemaOps[] = str_replace('__COL__', $column, $parsed['schema']);
                } else {
                    $rawOps[] = "ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}";
                }
            }

            if (! empty($schemaOps)) {
                $content .= "        Schema::table('{$table}', function (Blueprint \$table) {\n";
                foreach ($schemaOps as $schemaOp) {
                    $content .= "            {$schemaOp}\n";
                }
                $content .= "        });\n\n";
            }

            if (! empty($rawOps)) {
                foreach ($rawOps as $rawOp) {
                    $content .= "        DB::statement(\"{$rawOp}\");\n";
                }
                $content .= "\n";
            }
        }

        return $content;
    }

    /**
     * Group operations by table name.
     *
     * @param array<int, array{table:string}> $operations
     *
     * @return array<string, array<int, array>>
     */
    private function groupOperationsByTable(array $operations): array
    {
        $grouped = [];
        foreach ($operations as $op) {
            $grouped[$op['table']][] = $op;
        }

        return $grouped;
    }
}
