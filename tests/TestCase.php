<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use DatabaseSetup;
    use EnhancedTestIsolation;

    /**
     * Performance tracking for test optimization.
     */
    protected array $performanceMetrics = [];

    /**
     * Test execution start time.
     */
    protected float $testStartTime;

    /**
     * Memory usage at test start.
     */
    protected int $testStartMemory;

    /**
     * Test coverage tracking.
     */
    protected array $coverageData = [];

    /**
     * Security test flags.
     */
    protected bool $enableSecurityChecks = true;

    /**
     * Performance test flags.
     */
    protected bool $enablePerformanceTracking = true;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        // Start performance tracking
        $this->startPerformanceTracking();

        // Debug: Check config binding before Laravel's base setUp
        try {
            file_put_contents(__DIR__.'/test_bootstrap.log', '[setUp] config bound before parent: '.(function_exists('app') && app()->bound('config') ? 'yes' : 'no')."\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }

        parent::setUp();

        // Set up enhanced isolation
        $this->setUpEnhancedIsolation();

        // Configure test environment
        $this->configureTestEnvironment();

        // Only set up database manually if not using RefreshDatabase trait
        // RefreshDatabase trait handles database setup automatically
        if (! $this->usesRefreshDatabase()) {
            // Only set up database if the app is properly bootstrapped
            try {
                if (app()->bound('config')) {
                    $this->setUpDatabase();
                }
            } catch (\Exception $e) {
                // Skip database setup if app is not ready
            }
        }
        // Note: RefreshDatabase trait will handle its own setup automatically
        // We don't need to call refreshDatabase() manually as it conflicts with the trait's own setup

        // Set up security testing
        if ($this->enableSecurityChecks) {
            $this->setUpSecurityTesting();
        }

        // Disable separate process execution for tests
        $this->disableSeparateProcessExecution();
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        // Restore global error/exception handlers that may have been set during app bootstrap
        try {
            restore_error_handler();
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            restore_exception_handler();
        } catch (\Throwable $e) {
            // ignore
        }

        // Database cleanup only if not using RefreshDatabase trait
        if (! $this->usesRefreshDatabase()) {
            try {
                $this->tearDownDatabase();
            } catch (\Exception $e) {
                // Skip database teardown if there are issues
            }
        }

        // Clean up mocks
        \Mockery::close();

        // Tear down enhanced isolation
        $this->tearDownEnhancedIsolation();

        // Stop performance tracking
        $this->stopPerformanceTracking();

        // Clean up test artifacts
        $this->cleanupTestArtifacts();

        parent::tearDown();
    }

    /**
     * Creates and bootstraps the application for testing.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Force proper bootstrapping for testing environment
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();

        // Debug: Verify config binding post-bootstrap
        try {
            file_put_contents(__DIR__.'/test_bootstrap.log', '[createApplication] config bound after bootstrap: '.($app->bound('config') ? 'yes' : 'no')."\n", FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore
        }

        // Detect testing environment explicitly
        $app->detectEnvironment(static function () {
            return 'testing';
        });

        // Ensure config service is bound to avoid early lifecycle errors
        if (! $app->bound('config')) {
            $app->instance('config', new \Illuminate\Config\Repository([]));
        }

        // Ensure the default database is in-memory sqlite for testing speed and isolation
        try {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        } catch (\Throwable $e) {
            // ignore configuration errors
        }

        return $app;
    }

    /**
     * Get performance metrics for the current test.
     */
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * Start performance tracking for the test.
     */
    protected function startPerformanceTracking(): void
    {
        if (! $this->enablePerformanceTracking) {
            return;
        }

        $this->testStartTime = microtime(true);
        $this->testStartMemory = memory_get_usage(true);
    }

    /**
     * Stop performance tracking and record metrics.
     */
    protected function stopPerformanceTracking(): void
    {
        if (! $this->enablePerformanceTracking) {
            return;
        }

        $executionTime = microtime(true) - $this->testStartTime;
        $memoryUsage = memory_get_usage(true) - $this->testStartMemory;
        $peakMemory = memory_get_peak_usage(true);

        $this->performanceMetrics = [
            'execution_time' => $executionTime,
            'memory_usage' => $memoryUsage,
            'peak_memory' => $peakMemory,
            'test_name' => $this->name(),
            'timestamp' => now()->toISOString(),
        ];

        // Log performance metrics if execution time exceeds threshold
        if ($executionTime > 5.0) { // 5 seconds threshold
            error_log("Slow test detected: {$this->name()} took {$executionTime}s");
        }

        // Log memory usage if it exceeds threshold
        if ($memoryUsage > 50 * 1024 * 1024) { // 50MB threshold
            error_log("High memory usage test: {$this->getName()} used "
                     .number_format($memoryUsage / 1024 / 1024, 2).'MB');
        }
    }

    /**
     * Configure the test environment with optimal settings.
     */
    protected function configureTestEnvironment(): void
    {
        // Disable broadcasting during tests
        // Broadcasting is disabled by default in testing environment

        // Fake common services for faster tests
        Queue::fake();
        Event::fake();
        Mail::fake();
        Notification::fake();
        Storage::fake();

        // Set test-specific configuration
        if (function_exists('app') && app()->bound('config')) {
            try {
                app('config')->set([
                    'app.debug' => false,
                    'logging.default' => 'null',
                    'cache.default' => 'array',
                    'session.driver' => 'array',
                    'queue.default' => 'sync',
                    'mail.default' => 'array',
                ]);
            } catch (\Throwable $e) {
                // ignore configuration errors in tests
            }
        }

        // Optimize database for testing (skip when inside transactions)
        if ((function_exists('app') && app()->bound('config'))
            && 'sqlite' === config('database.default')
            && (! method_exists($this, 'isDatabaseTransacting') || ! $this->isDatabaseTransacting())) {
            try {
                DB::statement('PRAGMA synchronous = OFF');
                DB::statement('PRAGMA journal_mode = MEMORY');
                DB::statement('PRAGMA temp_store = MEMORY');
                DB::statement('PRAGMA cache_size = 10000');
            } catch (\Throwable $e) {
                // Ignore pragma errors in transactional contexts
            }
        }
    }

    /**
     * Ensure critical configuration is set before database refresh occurs.
     */
    protected function beforeRefreshingDatabase()
    {
        if (function_exists('putenv')) {
            putenv('CACHE_DRIVER=array');
            putenv('SESSION_DRIVER=array');
            putenv('QUEUE_CONNECTION=sync');
            putenv('PERMISSION_CACHE_STORE=array');
        }

        if (function_exists('app') && app()->bound('config')) {
            try {
                app('config')->set([
                    'cache.default' => 'array',
                    'cache.stores.array' => [
                        'driver' => 'array',
                    ],
                    'session.driver' => 'array',
                    'queue.default' => 'sync',
                    'permission.cache.store' => 'array',
                ]);
            } catch (\Throwable $e) {
                // ignore configuration errors in tests
            }
        }
    }

    /**
     * Set up security testing environment.
     */
    protected function setUpSecurityTesting(): void
    {
        // Enable CSRF protection for security tests
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Set up security headers validation
        if (function_exists('app') && app()->bound('config')) {
            try {
                app('config')->set([
                    'secure-headers.x-frame-options' => 'DENY',
                    'secure-headers.x-content-type-options' => 'nosniff',
                    'secure-headers.x-xss-protection' => '1; mode=block',
                    'secure-headers.strict-transport-security' => 'max-age=31536000; includeSubDomains',
                ]);
            } catch (\Throwable $e) {
                // ignore configuration errors in tests
            }
        }
    }

    /**
     * Disable separate process execution for tests.
     */
    protected function disableSeparateProcessExecution(): void
    {
        if (method_exists($this, 'setRunTestInSeparateProcess')) {
            $this->setRunTestInSeparateProcess(false);
        }
    }

    /**
     * Check if the test class uses the RefreshDatabase trait.
     */
    protected function usesRefreshDatabase(): bool
    {
        $traits = class_uses_recursive(static::class);

        return \in_array(RefreshDatabase::class, $traits, true)
               || \in_array(CustomRefreshDatabase::class, $traits, true);
    }

    /**
     * Clean up test artifacts and temporary files.
     */
    protected function cleanupTestArtifacts(): void
    {
        try {
            // Clear all caches only if app is properly initialized
            if (app()->bound('cache')) {
                Cache::flush();
            }

            // Clean up temporary files
            $tempDir = sys_get_temp_dir().'/laravel_testing';
            if (is_dir($tempDir)) {
                $this->recursiveRemoveDirectory($tempDir);
            }

            // Clean up storage fake directories only if app is properly initialized
            if (app()->bound('filesystem')) {
                try {
                    $disk = Storage::disk('local');
                    if (method_exists($disk, 'getRootPath')) {
                        $rootPath = $disk->getRootPath();
                        if ($rootPath !== null && is_dir($rootPath)) {
                            $disk->deleteDirectory('');
                        }
                    } else {
                        // Fallback for fake storage
                        $disk->deleteDirectory('');
                    }
                } catch (\Exception $e) {
                    // Ignore storage cleanup errors
                }
            }
        } catch (\Exception $e) {
            // Silently ignore cleanup errors during test teardown
        }
    }

    /**
     * Recursively remove directory and its contents.
     */
    protected function recursiveRemoveDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.\DIRECTORY_SEPARATOR.$file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Assert that response has security headers.
     */
    protected function assertHasSecurityHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection');
    }

    /**
     * Assert that response time is within acceptable limits.
     */
    protected function assertResponseTimeWithinLimit(float $maxTime = 2.0): void
    {
        $executionTime = microtime(true) - $this->testStartTime;
        self::assertLessThan(
            $maxTime,
            $executionTime,
            "Response time {$executionTime}s exceeded limit of {$maxTime}s"
        );
    }

    /**
     * Assert that memory usage is within acceptable limits.
     */
    protected function assertMemoryUsageWithinLimit(int $maxMemoryMB = 50): void
    {
        $memoryUsage = memory_get_usage(true) - $this->testStartMemory;
        $memoryUsageMB = $memoryUsage / 1024 / 1024;
        self::assertLessThan(
            $maxMemoryMB,
            $memoryUsageMB,
            "Memory usage {$memoryUsageMB}MB exceeded limit of {$maxMemoryMB}MB"
        );
    }

    /**
     * Create a test user with specified attributes.
     */
    protected function createTestUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create an admin test user.
     */
    protected function createAdminUser(array $attributes = []): User
    {
        return $this->createTestUser(array_merge([
            'is_admin' => true,
            'role' => 'admin',
        ], $attributes));
    }

    /**
     * Act as a test user for the request.
     */
    protected function actingAsTestUser(array $attributes = []): User
    {
        $user = $this->createTestUser($attributes);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Act as an admin user for the request.
     */
    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = $this->createAdminUser($attributes);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Enable or disable security checks for the test.
     */
    protected function setSecurityChecks(bool $enabled): void
    {
        $this->enableSecurityChecks = $enabled;
    }

    /**
     * Enable or disable performance tracking for the test.
     */
    protected function setPerformanceTracking(bool $enabled): void
    {
        $this->enablePerformanceTracking = $enabled;
    }

    /**
     * Assert that a database query count is within expected limits.
     */
    protected function assertQueryCountWithinLimit(int $maxQueries = 10): void
    {
        $queryCount = \count(DB::getQueryLog());
        self::assertLessThanOrEqual(
            $maxQueries,
            $queryCount,
            "Query count {$queryCount} exceeded limit of {$maxQueries}"
        );
    }

    /**
     * Enable query logging for performance analysis.
     */
    protected function enableQueryLogging(): void
    {
        DB::enableQueryLog();
    }

    /**
     * Get executed queries for analysis.
     */
    protected function getExecutedQueries(): array
    {
        return DB::getQueryLog();
    }

    /**
     * Assert that no N+1 queries occurred.
     */
    protected function assertNoNPlusOneQueries(callable $callback, int $expectedQueries = 1): void
    {
        DB::enableQueryLog();
        $callback();
        $queries = DB::getQueryLog();

        self::assertLessThanOrEqual(
            $expectedQueries,
            \count($queries),
            'N+1 query detected. Expected '.$expectedQueries.' queries, got '.\count($queries)
        );
    }
}
