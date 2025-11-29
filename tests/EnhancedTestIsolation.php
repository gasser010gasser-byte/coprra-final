<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mockery;

/**
 * Enhanced Test Isolation Trait.
 *
 * Provides comprehensive isolation between tests to prevent side-effects
 * and cross-contamination. Includes advanced features for:
 * - Superglobal backup/restore
 * - Service container isolation
 * - Cache management
 * - Database state isolation
 * - File system isolation
 * - Memory management
 * - Resource cleanup
 */
trait EnhancedTestIsolation
{
    /**
     * Backup storage for superglobals.
     */
    protected array $envBackup = [];
    protected array $serverBackup = [];
    protected array $sessionBackup = [];
    protected array $cookieBackup = [];
    protected array $filesBackup = [];
    protected array $getBackup = [];
    protected array $postBackup = [];
    protected array $requestBackup = [];

    /**
     * Application state backup.
     */
    protected array $configBackup = [];
    protected array $containerBackup = [];
    protected array $serviceProvidersBackup = [];

    /**
     * Isolation tracking.
     */
    protected array $temporaryPaths = [];
    protected array $createdFiles = [];
    protected array $modifiedFiles = [];
    protected array $originalFileContents = [];
    protected array $databaseConnections = [];
    protected array $cacheStores = [];
    protected array $queueConnections = [];

    /**
     * Resource monitoring.
     */
    protected int $initialMemoryUsage = 0;
    protected int $maxMemoryUsage = 0;
    protected array $openResources = [];
    protected array $activeConnections = [];

    /**
     * Isolation configuration.
     */
    protected bool $strictIsolation = false;
    protected bool $enableMemoryTracking = false;
    protected bool $enableResourceTracking = false;
    protected bool $enableFileSystemIsolation = true;
    protected bool $enableDatabaseIsolation = true;
    protected bool $enableCacheIsolation = true;

    /**
     * Determine if RefreshDatabase (or custom equivalent) is enabled for this test.
     */
    protected function isRefreshDatabaseEnabled(): bool
    {
        try {
            if (method_exists($this, 'usesRefreshDatabase')) {
                return (bool) $this->usesRefreshDatabase();
            }
        } catch (\Throwable $e) {
            // Ignore errors in detection and fall back to trait inspection
        }

        $traits = class_uses_recursive(static::class);

        return \in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, $traits, true)
            || \in_array(\Tests\CustomRefreshDatabase::class, $traits, true);
    }

    /**
     * Heuristic to detect whether a database transaction is currently active.
     */
    protected function isDatabaseTransacting(): bool
    {
        if (! class_exists(DB::class)) {
            return false;
        }

        try {
            $conn = DB::connection();
            if (method_exists($conn, 'transactionLevel')) {
                return $conn->transactionLevel() > 0;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Track file creation.
     */
    public function trackFileCreation(string $filePath): void
    {
        $this->createdFiles[] = $filePath;
    }

    /**
     * Track file modification.
     */
    public function trackFileModification(string $filePath): void
    {
        if (! \in_array($filePath, $this->modifiedFiles, true)) {
            $this->modifiedFiles[] = $filePath;

            if (file_exists($filePath)) {
                $this->originalFileContents[$filePath] = file_get_contents($filePath);
            }
        }
    }

    /**
     * Add temporary path for cleanup.
     */
    public function addTemporaryPath(string $path): void
    {
        $this->temporaryPaths[] = $path;
    }

    /**
     * Get isolation statistics.
     */
    public function getIsolationStatistics(): array
    {
        return [
            'memory_usage' => [
                'initial' => $this->initialMemoryUsage,
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'increase' => memory_get_usage(true) - $this->initialMemoryUsage,
            ],
            'tracked_files' => [
                'created' => \count($this->createdFiles),
                'modified' => \count($this->modifiedFiles),
                'temporary_paths' => \count($this->temporaryPaths),
            ],
            'resources' => [
                'initial_count' => \count($this->openResources),
                'current_count' => \function_exists('get_resources') ? \count(get_resources()) : 0,
            ],
            'isolation_config' => [
                'strict_isolation' => $this->strictIsolation,
                'memory_tracking' => $this->enableMemoryTracking,
                'resource_tracking' => $this->enableResourceTracking,
                'filesystem_isolation' => $this->enableFileSystemIsolation,
                'database_isolation' => $this->enableDatabaseIsolation,
                'cache_isolation' => $this->enableCacheIsolation,
            ],
        ];
    }

    /**
     * Set up enhanced test isolation.
     */
    protected function setUpEnhancedIsolation(): void
    {
        $this->recordInitialState();
        $this->backupSuperglobals();
        $this->backupApplicationState();
        $this->setupIsolatedEnvironment();
        $this->clearAllCaches();
        $this->initializeResourceTracking();
        $this->setupMemoryTracking();
    }

    /**
     * Record initial system state.
     */
    protected function recordInitialState(): void
    {
        $this->initialMemoryUsage = memory_get_usage(true);
        $this->maxMemoryUsage = $this->initialMemoryUsage;

        // Record open file handles
        if (\function_exists('get_resources')) {
            $this->openResources = get_resources();
        }

        // Record database connections without disrupting RefreshDatabase transactions
        if (class_exists(DB::class)) {
            try {
                // If not using RefreshDatabase or an active transaction, ensure we start clean
                if (! $this->isRefreshDatabaseEnabled() && ! $this->isDatabaseTransacting()) {
                    $this->closeAllDatabaseConnections();
                }

                // Record current connections state
                $this->databaseConnections = DB::getConnections();
            } catch (\Exception $e) {
                // Ignore if DB not available
                $this->databaseConnections = [];
            }
        }
    }

    /**
     * Backup all superglobals.
     */
    protected function backupSuperglobals(): void
    {
        $this->envBackup = $_ENV ?? [];
        $this->serverBackup = $_SERVER ?? [];
        $this->sessionBackup = $_SESSION ?? [];
        $this->cookieBackup = $_COOKIE ?? [];
        $this->filesBackup = $_FILES ?? [];
        $this->getBackup = $_GET ?? [];
        $this->postBackup = $_POST ?? [];
        $this->requestBackup = $_REQUEST ?? [];
    }

    /**
     * Backup application state.
     */
    protected function backupApplicationState(): void
    {
        if (\function_exists('app') && app() instanceof Application) {
            $app = app();

            // Backup configuration
            $this->configBackup = $app['config']->all();

            // Backup container bindings
            $this->containerBackup = $this->getContainerBindings($app);

            // Backup service providers
            $this->serviceProvidersBackup = $app->getLoadedProviders();
        }
    }

    /**
     * Get container bindings for backup.
     */
    protected function getContainerBindings(Container $container): array
    {
        $bindings = [];

        try {
            $reflection = new \ReflectionClass($container);
            $bindingsProperty = $reflection->getProperty('bindings');
            $bindingsProperty->setAccessible(true);
            $bindings = $bindingsProperty->getValue($container);
        } catch (\Exception $e) {
            // Fallback: just record known important bindings
            $bindings = [];
        }

        return $bindings;
    }

    /**
     * Setup isolated environment.
     */
    protected function setupIsolatedEnvironment(): void
    {
        // Create isolated temporary directory
        $tempDir = sys_get_temp_dir() . '/test_isolation_' . uniqid();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
            $this->temporaryPaths[] = $tempDir;
        }

        // Set isolated environment variables
        $_ENV['TEST_ISOLATION_TEMP_DIR'] = $tempDir;
        $_ENV['TEST_ISOLATION_ACTIVE'] = 'true';

        // Configure isolated storage
        if ($this->enableFileSystemIsolation) {
            $this->setupIsolatedStorage($tempDir);
        }

        // Configure isolated database
        if ($this->enableDatabaseIsolation) {
            $this->setupIsolatedDatabase();
        }
    }

    /**
     * Setup isolated storage.
     */
    protected function setupIsolatedStorage(string $tempDir): void
    {
        $storagePath = $tempDir . '/storage';
        mkdir($storagePath, 0755, true);

        // Configure Laravel storage paths
        if (\function_exists('app') && app()->bound('config')) {
            try {
                app('config')->set([
                    'filesystems.disks.local.root' => $storagePath,
                    'filesystems.disks.public.root' => $storagePath . '/public',
                    'view.compiled' => $storagePath . '/views',
                    'cache.stores.file.path' => $storagePath . '/cache',
                    'session.files' => $storagePath . '/sessions',
                    'logging.channels.single.path' => $storagePath . '/logs/laravel.log',
                ]);
            } catch (\Throwable $e) {
                // Ignore configuration errors during isolation setup
            }
        }
    }

    /**
     * Setup isolated database.
     */
    protected function setupIsolatedDatabase(): void
    {
        // When using Laravel's RefreshDatabase, or if a transaction is active, do not override
        if ($this->isRefreshDatabaseEnabled() || $this->isDatabaseTransacting()) {
            return;
        }

        if (\function_exists('app') && app()->bound('config')) {
            // First, close any existing connections to prevent leaks
            $this->closeAllDatabaseConnections();

            // Use in-memory SQLite for complete isolation, without changing default
            try {
                $default = config('database.default', 'sqlite');

                // Ensure the default connection is sqlite and points to memory
                if ($default !== 'sqlite') {
                    // Do not force change default; just ensure sqlite memory is available
                    app('config')->set('database.connections.sqlite', [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                    ]);
                } else {
                    // Keep default as sqlite and enforce memory settings
                    app('config')->set('database.connections.sqlite', [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                    ]);
                }
            } catch (\Throwable $e) {
                // Ignore configuration errors during isolation setup
            }

            // Record this connection in our baseline to avoid false positives
            if (class_exists(DB::class)) {
                try {
                    // Force a connection to be created now so we can track it
                    DB::connection(config('database.default', 'sqlite'))->getPdo();

                    // Update our baseline to include this connection
                    $this->databaseConnections = DB::getConnections();
                } catch (\Exception $e) {
                    // Ignore if connection fails
                }
            }
        }
    }

    /**
     * Clear all caches comprehensively.
     * MODIFIED: Disabled file-based cache clearing to prevent parallel testing conflicts.
     */
    protected function clearAllCaches(): void
    {
        $this->clearApplicationCache();
        // $this->clearConfigCache(); // DISABLED: Can conflict with parallel testing
        $this->clearViewCache();
        // $this->clearRouteCache(); // DISABLED: File manipulation conflicts with parallel testing
        $this->clearEventCache();
        $this->clearOpcache();
        $this->clearRedisCache();
        // $this->clearFileCache(); // DISABLED: File manipulation conflicts with parallel testing
    }

    /**
     * Clear application cache.
     */
    protected function clearApplicationCache(): void
    {
        if (class_exists(Cache::class)) {
            try {
                Cache::flush();

                // Clear all cache stores
                $stores = (\function_exists('app') && app()->bound('config')) ? config('cache.stores', []) : [];
                if (is_array($stores) && !empty($stores)) {
                    foreach ($stores as $store => $config) {
                        try {
                            Cache::store($store)->flush();
                        } catch (\Throwable $e) {
                            // Ignore individual store flush errors
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore cache errors in isolation
            }
        }
    }

    /**
     * Clear configuration cache.
     */
    protected function clearConfigCache(): void
    {
        if (\function_exists('app') && app()->bound('config')) {
            // Avoid nuking configuration when RefreshDatabase is in use
            if ($this->isRefreshDatabaseEnabled()) {
                return;
            }
            // Intentionally skip destructive clearing; container reset handles re-resolution.
        }
    }

    /**
     * Clear view cache.
     */
    protected function clearViewCache(): void
    {
        if (class_exists(View::class)) {
            try {
                View::flushFinderCache();

                // Clear compiled views
                $viewPath = storage_path('framework/views');
                if (is_dir($viewPath)) {
                    $this->clearDirectory($viewPath);
                }
            } catch (\Exception $e) {
                // Ignore view cache errors
            }
        }
    }

    /**
     * Clear route cache.
     * DISABLED: Commented out to prevent conflicts with parallel testing.
     * Laravel's default test environment handles cache management.
     */
    protected function clearRouteCache(): void
    {
        // COMMENTED OUT: File manipulation conflicts with parallel testing
        // $routeCachePath = app()->bootstrapPath('cache/routes.php');
        // if (file_exists($routeCachePath)) {
        //     unlink($routeCachePath);
        // }
    }

    /**
     * Clear event cache.
     */
    protected function clearEventCache(): void
    {
        // Event faking is handled in TestCase.php
        // No additional event clearing needed for test isolation
    }

    /**
     * Clear OPcache.
     */
    protected function clearOpcache(): void
    {
        if (\function_exists('opcache_reset')) {
            try {
                opcache_reset();
            } catch (\Exception $e) {
                // Ignore opcache errors
            }
        }
    }

    /**
     * Clear Redis cache.
     */
    protected function clearRedisCache(): void
    {
        // Skip Redis operations in testing environment
        if (app()->environment('testing')) {
            return;
        }

        try {
            if (app()->bound('redis')) {
                Redis::flushall();
            }
        } catch (\Exception $e) {
            // Ignore Redis errors
        }
    }

    /**
     * Clear file cache.
     * DISABLED: Commented out to prevent conflicts with parallel testing.
     * Laravel's default test environment handles cache management.
     */
    protected function clearFileCache(): void
    {
        // COMMENTED OUT: File manipulation conflicts with parallel testing
        // $cachePaths = [
        //     storage_path('framework/cache'),
        //     storage_path('framework/sessions'),
        //     storage_path('framework/views'),
        //     app()->bootstrapPath('cache'),
        // ];
        //
        // foreach ($cachePaths as $path) {
        //     if (is_dir($path)) {
        //         $this->clearDirectory($path);
        //     }
        // }
    }

    /**
     * Initialize resource tracking.
     */
    protected function initializeResourceTracking(): void
    {
        if (! $this->enableResourceTracking) {
            return;
        }

        // Track file operations
        if ($this->enableFileSystemIsolation) {
            $this->setupFileTracking();
        }

        // Track database connections
        if ($this->enableDatabaseIsolation) {
            $this->setupDatabaseTracking();
        }

        // Track cache operations
        if ($this->enableCacheIsolation) {
            $this->setupCacheTracking();
        }
    }

    /**
     * Setup file tracking.
     */
    protected function setupFileTracking(): void
    {
        // This would require stream wrapper or similar advanced technique
        // For now, we'll track manually created files
    }

    /**
     * Setup database tracking.
     */
    protected function setupDatabaseTracking(): void
    {
        if (class_exists(DB::class)) {
            try {
                // Enable query logging for tracking
                DB::enableQueryLog();
            } catch (\Exception $e) {
                // Ignore if DB not available
            }
        }
    }

    /**
     * Setup cache tracking.
     */
    protected function setupCacheTracking(): void
    {
        // Track cache stores that are created during test
        if (\function_exists('app') && app()->bound('config')) {
            try {
                $this->cacheStores = array_keys(config('cache.stores', []));
            } catch (\Throwable $e) {
                $this->cacheStores = [];
            }
        }
    }

    /**
     * Setup memory tracking.
     */
    protected function setupMemoryTracking(): void
    {
        if (! $this->enableMemoryTracking) {
            return;
        }

        // Register shutdown function to track peak memory
        register_shutdown_function(function () {
            $this->maxMemoryUsage = max($this->maxMemoryUsage, memory_get_peak_usage(true));
        });
    }

    /**
     * Reset service container.
     */
    protected function resetServiceContainer(): void
    {
        // Avoid resetting container when RefreshDatabase is managing transactions
        if (($this->isRefreshDatabaseEnabled() || $this->isDatabaseTransacting())) {
            return;
        }

        if (\function_exists('app') && app() instanceof Application) {
            $app = app();

            // Clear resolved instances
            $app->forgetInstances();

            // Reset singletons
            $reflection = new \ReflectionClass($app);
            if ($reflection->hasProperty('instances')) {
                $instancesProperty = $reflection->getProperty('instances');
                $instancesProperty->setAccessible(true);
                $instancesProperty->setValue($app, []);
            }

            // Rebind core services
            $this->rebindCoreServices($app);
        }
    }

    /**
     * Rebind core services.
     */
    protected function rebindCoreServices(Application $app): void
    {
        // Rebind essential services that tests might need
        $coreServices = [
            'config',
            'db',
            'cache',
            'session',
            'auth',
            'view',
            'files',
            'log',
        ];

        $skip = [];
        if ($this->isRefreshDatabaseEnabled() || $this->isDatabaseTransacting()) {
            // Do not disturb DB or config while RefreshDatabase manages transactions
            $skip = ['db', 'config'];
        }

        foreach ($coreServices as $service) {
            if (\in_array($service, $skip, true)) {
                continue;
            }
            if ($app->bound($service)) {
                $app->forgetInstance($service);
            }
        }
    }

    /**
     * Restore superglobals.
     */
    protected function restoreSuperglobals(): void
    {
        $_ENV = $this->envBackup;
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_REQUEST = $this->requestBackup;
    }

    /**
     * Restore application state.
     */
    protected function restoreApplicationState(): void
    {
        if (\function_exists('app') && app() instanceof Application) {
            $app = app();

            // Restore configuration
            if (! empty($this->configBackup)) {
                foreach ($this->configBackup as $key => $value) {
                    $app['config']->set($key, $value);
                }
            }

            // Restore container bindings would be complex
            // For now, we rely on application recreation
        }
    }

    /**
     * Clean up temporary paths.
     */
    protected function cleanupTemporaryPaths(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryPaths = [];
    }

    /**
     * Clean up created files.
     */
    protected function cleanupCreatedFiles(): void
    {
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $this->createdFiles = [];
    }

    /**
     * Restore modified files.
     */
    protected function restoreModifiedFiles(): void
    {
        foreach ($this->modifiedFiles as $file) {
            if (isset($this->originalFileContents[$file])) {
                file_put_contents($file, $this->originalFileContents[$file]);
            }
        }

        $this->modifiedFiles = [];
        $this->originalFileContents = [];
    }

    /**
     * Clear directory contents.
     * DISABLED: Commented out to prevent conflicts with parallel testing.
     * Only used for bootstrap/cache which conflicts with parallel processes.
     */
    protected function clearDirectory(string $directory): void
    {
        // COMMENTED OUT: Skip bootstrap/cache directory to prevent parallel testing conflicts
        if (str_contains($directory, 'bootstrap/cache')) {
            return;
        }

        if (! is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');

            try {
                $path = $fileinfo->getRealPath();
                if ($path !== false) {
                    $todo($path);
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * Remove directory recursively.
     */
    protected function removeDirectoryRecursively(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $this->clearDirectory($directory);

        try {
            rmdir($directory);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    /**
     * Tear down enhanced isolation.
     */
    protected function tearDownEnhancedIsolation(): void
    {
        $this->cleanupResources();
        $this->restoreSuperglobals();
        $this->restoreApplicationState();
        $this->cleanupTemporaryPaths();
        $this->cleanupCreatedFiles();
        $this->restoreModifiedFiles();
        $this->resetServiceContainer();
        $this->clearAllCaches();
        $this->logResourceUsage();
        $this->validateCleanup();

        // Close Mockery
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }
    }

    /**
     * Cleanup resources.
     */
    protected function cleanupResources(): void
    {
        // Close database connections unless using RefreshDatabase or an active transaction
        if (! ($this->isRefreshDatabaseEnabled() || $this->isDatabaseTransacting())) {
            $this->closeAllDatabaseConnections();
        }

        // Clear queue connections
        if (class_exists(Queue::class)) {
            try {
                Queue::getContainer()->forgetInstances();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Storage cleanup is handled by Laravel's testing framework
        // No additional storage clearing needed for test isolation
    }

    /**
     * Close all database connections comprehensively.
     */
    protected function closeAllDatabaseConnections(): void
    {
        if (! class_exists(DB::class)) {
            return;
        }

        try {
            // Do not interfere with RefreshDatabase using in-memory sqlite or active transactions
            if ($this->isRefreshDatabaseEnabled() || $this->isDatabaseTransacting()) {
                return;
            }

            // Get all current connections and close them individually
            $connections = DB::getConnections();
            foreach ($connections as $name => $connection) {
                try {
                    // Close the connection explicitly
                    DB::disconnect($name);
                } catch (\Exception $e) {
                    // Continue with other connections if one fails
                }
            }

            // Also disconnect the default connection explicitly
            try {
                DB::disconnect();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }

            // Force purge all connections from the manager
            try {
                $manager = DB::getManager();
                if (method_exists($manager, 'purge')) {
                    $manager->purge();
                }
            } catch (\Exception $e) {
                // Ignore if purge method doesn't exist or fails
            }

            // Specifically handle testing and sqlite connections
            try {
                // Force disconnect specific problematic connections multiple times
                $problematicConnections = ['testing', 'sqlite', 'sqlite_testing'];
                foreach ($problematicConnections as $connectionName) {
                    // Try multiple times to ensure disconnection
                    for ($i = 0; $i < 3; ++$i) {
                        try {
                            DB::disconnect($connectionName);
                        } catch (\Exception $e) {
                            // Continue with other attempts
                        }
                    }
                }

                // Force garbage collection to clean up PDO connections
                if (\function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    /**
     * Log resource usage.
     */
    protected function logResourceUsage(): void
    {
        if (! $this->enableMemoryTracking) {
            return;
        }

        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryIncrease = $currentMemory - $this->initialMemoryUsage;

        if ($memoryIncrease > 50 * 1024 * 1024) { // 50MB threshold
            error_log(\sprintf(
                'Test isolation warning: High memory usage detected. Initial: %s, Current: %s, Peak: %s, Increase: %s',
                $this->formatBytes($this->initialMemoryUsage),
                $this->formatBytes($currentMemory),
                $this->formatBytes($peakMemory),
                $this->formatBytes($memoryIncrease)
            ));
        }
    }

    /**
     * Validate cleanup.
     */
    protected function validateCleanup(): void
    {
        if (! $this->strictIsolation) {
            return;
        }

        // Check for resource leaks
        $this->validateMemoryUsage();
        $this->validateFileHandles();
        $this->validateDatabaseConnections();
    }

    /**
     * Validate memory usage.
     */
    protected function validateMemoryUsage(): void
    {
        $currentMemory = memory_get_usage(true);
        $memoryIncrease = $currentMemory - $this->initialMemoryUsage;

        // Allow for some memory increase (10MB threshold)
        if ($memoryIncrease > 10 * 1024 * 1024) {
            trigger_error(\sprintf(
                'Memory leak detected: %s increase from initial usage',
                $this->formatBytes($memoryIncrease)
            ), \E_USER_WARNING);
        }
    }

    /**
     * Validate file handles.
     */
    protected function validateFileHandles(): void
    {
        if (! \function_exists('get_resources')) {
            return;
        }

        $currentResources = get_resources();
        $resourceIncrease = \count($currentResources) - \count($this->openResources);

        if ($resourceIncrease > 5) { // Allow for some resource increase
            trigger_error(\sprintf(
                'Resource leak detected: %d new resources not cleaned up',
                $resourceIncrease
            ), \E_USER_WARNING);
        }
    }

    /**
     * Validate database connections.
     */
    protected function validateDatabaseConnections(): void
    {
        if (! class_exists(DB::class)) {
            return;
        }

        try {
            $currentConnections = DB::getConnections();
            $connectionIncrease = \count($currentConnections) - \count($this->databaseConnections);

            if ($connectionIncrease > 0) {
                // Get the names of new connections for better debugging
                $initialConnectionNames = array_keys($this->databaseConnections);
                $currentConnectionNames = array_keys($currentConnections);
                $newConnectionNames = array_diff($currentConnectionNames, $initialConnectionNames);

                // Try to force close the leaked connections before reporting
                foreach ($newConnectionNames as $connectionName) {
                    try {
                        DB::disconnect($connectionName);
                    } catch (\Exception $e) {
                        // Continue with other connections
                    }
                }

                // Check again after forced cleanup
                $finalConnections = DB::getConnections();
                $finalConnectionNames = array_keys($finalConnections);
                $stillLeakedConnections = array_diff($finalConnectionNames, $initialConnectionNames);

                if (! empty($stillLeakedConnections)) {
                    trigger_error(\sprintf(
                        'Database connection leak detected: %d new connections not closed. Leaked connections: [%s] in %s at line %d',
                        \count($stillLeakedConnections),
                        implode(', ', $stillLeakedConnections),
                        __FILE__,
                        __LINE__
                    ), \E_USER_WARNING);
                }
            }
        } catch (\Exception $e) {
            // Ignore validation errors
        }
    }

    /**
     * Format bytes for human reading.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
