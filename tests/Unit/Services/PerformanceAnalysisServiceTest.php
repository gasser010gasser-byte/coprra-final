<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PerformanceAnalysisService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PerformanceAnalysisServiceTest extends TestCase
{
    private PerformanceAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PerformanceAnalysisService();
    }

    public function testAnalyzeReturnsCorrectStructure(): void
    {
        $result = $this->service->analyze();

        self::assertIsArray($result);
        self::assertArrayHasKey('score', $result);
        self::assertArrayHasKey('issues', $result);
        self::assertArrayHasKey('category', $result);
        self::assertArrayHasKey('max_score', $result);

        self::assertIsInt($result['score']);
        self::assertIsArray($result['issues']);
        self::assertSame('Performance', $result['category']);
        self::assertSame(100, $result['max_score']);
    }

    public function testAnalyzeWithCacheDisabled(): void
    {
        Config::set('cache.default', 'array');

        $result = $this->service->analyze();

        self::assertContains('Cache is not properly configured (using array driver)', $result['issues']);
        self::assertLessThan(100, $result['score']);
    }

    public function testAnalyzeWithCacheEnabled(): void
    {
        Config::set('cache.default', 'redis');

        $result = $this->service->analyze();

        self::assertNotContains('Cache is not properly configured (using array driver)', $result['issues']);
    }

    public function testAnalyzeWithFileCacheDriver(): void
    {
        Config::set('cache.default', 'file');

        $result = $this->service->analyze();

        // File cache should not trigger the array driver warning
        self::assertNotContains('Cache is not properly configured (using array driver)', $result['issues']);
    }

    public function testAnalyzeWithDatabaseCacheDriver(): void
    {
        Config::set('cache.default', 'database');

        $result = $this->service->analyze();

        // Database cache should not trigger the array driver warning
        self::assertNotContains('Cache is not properly configured (using array driver)', $result['issues']);
    }

    public function testAnalyzeWithQueueSyncDriver(): void
    {
        Config::set('queue.default', 'sync');

        $result = $this->service->analyze();

        self::assertContains('Queue is using sync driver (not suitable for production)', $result['issues']);
        self::assertLessThan(100, $result['score']);
    }

    public function testAnalyzeWithQueueRedisDriver(): void
    {
        Config::set('queue.default', 'redis');

        $result = $this->service->analyze();

        self::assertNotContains('Queue is using sync driver (not suitable for production)', $result['issues']);
    }

    public function testAnalyzeWithQueueDatabaseDriver(): void
    {
        Config::set('queue.default', 'database');

        $result = $this->service->analyze();

        self::assertNotContains('Queue is using sync driver (not suitable for production)', $result['issues']);
    }

    public function testAnalyzeWithMissingPublicMixManifest(): void
    {
        // Check if either mix-manifest.json or Vite manifest exists
        $mixManifestPath = public_path('mix-manifest.json');
        $viteManifestPath = public_path('build/manifest.json');
        $mixManifestExists = File::exists($mixManifestPath);
        $viteManifestExists = File::exists($viteManifestPath);
        $anyManifestExists = $mixManifestExists || $viteManifestExists;

        $result = $this->service->analyze();

        if ($anyManifestExists) {
            // If any manifest exists, the issue should not be present
            self::assertNotContains('Assets not compiled (mix-manifest.json missing)', $result['issues']);
        } else {
            // If no manifest exists, the issue should be present
            self::assertContains('Assets not compiled (mix-manifest.json missing)', $result['issues']);
        }
    }

    public function testAnalyzeScoreIsWithinValidRange(): void
    {
        $result = $this->service->analyze();

        self::assertGreaterThanOrEqual(0, $result['score']);
        self::assertLessThanOrEqual(100, $result['score']);
        self::assertTrue($result['score'] % 20 === 0, 'Score should be a multiple of 20');
    }

    public function testAnalyzeIssuesAreStrings(): void
    {
        $result = $this->service->analyze();

        foreach ($result['issues'] as $issue) {
            self::assertIsString($issue);
            self::assertNotEmpty($issue);
        }
    }

    public function testAnalyzeConsistentResults(): void
    {
        $result1 = $this->service->analyze();
        $result2 = $this->service->analyze();

        // Results should be consistent when run multiple times
        self::assertSame($result1['score'], $result2['score']);
        self::assertSame($result1['issues'], $result2['issues']);
        self::assertSame($result1['category'], $result2['category']);
        self::assertSame($result1['max_score'], $result2['max_score']);
    }

    public function testAnalyzeWithOptimalConfiguration(): void
    {
        // Set optimal configuration
        Config::set('cache.default', 'redis');
        Config::set('queue.default', 'redis');

        $result = $this->service->analyze();

        // Should have high score with optimal config
        self::assertGreaterThanOrEqual(60, $result['score']);
    }

    public function testAnalyzeWithPoorConfiguration(): void
    {
        // Set poor configuration
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');

        $result = $this->service->analyze();

        // Should have lower score with poor config
        self::assertLessThanOrEqual(60, $result['score']);
        self::assertGreaterThanOrEqual(2, \count($result['issues']));
    }

    public function testAnalyzeHandlesDatabaseConnectionIssues(): void
    {
        // This test would require mocking DB facade to throw exceptions
        // For now, we'll test that the method doesn't crash
        $result = $this->service->analyze();

        self::assertIsArray($result);
        self::assertArrayHasKey('score', $result);
        self::assertArrayHasKey('issues', $result);
    }

    public function testAnalyzeWithDifferentCacheDrivers(): void
    {
        $drivers = ['file', 'database', 'redis', 'memcached', 'dynamodb'];

        foreach ($drivers as $driver) {
            Config::set('cache.default', $driver);
            $result = $this->service->analyze();

            if ('array' === $driver) {
                self::assertContains('Cache is not properly configured (using array driver)', $result['issues']);
            } else {
                self::assertNotContains('Cache is not properly configured (using array driver)', $result['issues']);
            }
        }
    }

    public function testAnalyzeWithDifferentQueueDrivers(): void
    {
        $drivers = ['database', 'redis', 'beanstalkd', 'sqs', 'null'];

        foreach ($drivers as $driver) {
            Config::set('queue.default', $driver);
            $result = $this->service->analyze();

            if ('sync' === $driver) {
                self::assertContains('Queue is using sync driver (not suitable for production)', $result['issues']);
            } else {
                self::assertNotContains('Queue is using sync driver (not suitable for production)', $result['issues']);
            }
        }
    }
}
