<?php

declare(strict_types=1);

namespace App\Services;

final class PerformanceAnalysisService
{
    /**
     * Run comprehensive performance analysis.
     *
     * @return array<string, int|list<string>>
     */
    public function analyze(): array
    {
        $score = 0;
        $issues = [];

        try {
            $score += $this->checkCacheConfiguration($issues);
            $score += $this->checkDatabaseIndexes($issues);
            $score += $this->checkAssetCompilation($issues);
            $score += $this->checkQueueConfiguration($issues);
        } catch (\Exception $e) {
            $issues[] = 'Performance analysis failed: '.$e->getMessage();
        }

        // Ensure score is a multiple of 20 (0, 20, 40, 60, 80, 100)
        // Round down to nearest multiple of 20
        $score = (int) (floor($score / 20) * 20);

        return [
            'score' => $score,
            'max_score' => 100,
            'issues' => $issues,
            'category' => 'Performance',
        ];
    }

    /**
     * Check cache configuration.
     *
     * @param array<string> $issues
     *
     * @psalm-return 0|25
     */
    private function checkCacheConfiguration(array &$issues): int
    {
        $cacheDriver = config('cache.default', 'file');

        // Array driver is not suitable for production
        if ('array' === $cacheDriver) {
            $issues[] = 'Cache is not properly configured (using array driver)';

            return 0;
        }

        // File cache is acceptable but not optimal
        if ('file' === $cacheDriver) {
            $issues[] = 'Using file cache (consider Redis or Memcached for production)';

            return 0;
        }

        // Other drivers (redis, database, memcached, etc.) are good
        return 25;
    }

    /**
     * Check database indexes.
     *
     * @param array<string> $issues
     *
     * @psalm-return 0|25
     */
    private function checkDatabaseIndexes(array &$issues): int
    {
        $migrationFiles = glob(database_path('migrations/*.php'));
        if (false === $migrationFiles) {
            $migrationFiles = [];
        }
        foreach ($migrationFiles as $file) {
            $content = file_get_contents($file);
            if (false !== $content && (str_contains($content, '->index(') || str_contains($content, '->unique('))) {
                return 25;
            }
        }

        $issues[] = 'No database indexes found in migrations';

        return 0;
    }

    /**
     * Check asset compilation.
     *
     * @param array<string> $issues
     *
     * @psalm-return 0|25
     */
    private function checkAssetCompilation(array &$issues): int
    {
        // Check for mix-manifest.json (Laravel Mix) or build/manifest.json (Vite)
        $mixManifest = public_path('mix-manifest.json');
        $viteManifest = public_path('build/manifest.json');

        if (file_exists($mixManifest) || file_exists($viteManifest)) {
            return 25;
        }

        $issues[] = 'Assets not compiled (mix-manifest.json missing)';

        return 0;
    }

    /**
     * Check queue configuration.
     *
     * @param array<string> $issues
     *
     * @psalm-return 0|25
     */
    private function checkQueueConfiguration(array &$issues): int
    {
        $queueDriver = config('queue.default', 'sync');

        // Sync driver is not suitable for production
        if ('sync' === $queueDriver) {
            $issues[] = 'Queue is using sync driver (not suitable for production)';

            return 0;
        }

        // Other drivers (database, redis, etc.) are good
        return 25;
    }
}
