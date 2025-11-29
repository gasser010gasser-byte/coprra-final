<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SecurityAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Edge case tests for SecurityAnalysisService covering critical security failure scenarios.
 *
 * @internal
 *
 * @coversNothing
 */
final class SecurityAnalysisServiceEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private SecurityAnalysisService $securityService;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure database settings using app config instead of facade
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.driver', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');
        $this->app['config']->set('database.connections.sqlite.prefix', '');
        $this->app['config']->set('database.connections.sqlite.foreign_key_constraints', true);

        // Allow RefreshDatabase to access config during setup
        Config::shouldReceive('offsetGet')
            ->with(\Mockery::any())
            ->andReturnUsing(function ($key) {
                return match ($key) {
                    'database.default' => 'sqlite',
                    'database.connections.sqlite.driver' => 'sqlite',
                    'database.connections.sqlite.database' => ':memory:',
                    'database.connections.sqlite.prefix' => '',
                    'database.connections.sqlite.foreign_key_constraints' => true,
                    default => $this->app['config']->get($key),
                };
            })
            ->byDefault();

        Config::shouldReceive('set')->andReturnSelf()->byDefault();
        Config::shouldReceive('get')
            ->with(\Mockery::any(), \Mockery::any())
            ->andReturnUsing(function ($key, $default = null) {
                return $this->app['config']->get($key, $default);
            })
            ->byDefault();

        $this->securityService = new SecurityAnalysisService();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function testCheckEnvironmentFileWithPermissionDenied(): void
    {
        // Mock File facade to simulate permission denied
        File::shouldReceive('exists')
            ->with(base_path('.env.example'))
            ->andThrow(new \Exception('Permission denied'))
        ;

        $result = $this->securityService->checkEnvironmentFile();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('permission', strtolower($result['message']));
    }

    public function testCheckEnvironmentFileWithCorruptedFile(): void
    {
        // Mock File facade to simulate corrupted file
        File::shouldReceive('exists')
            ->with(base_path('.env.example'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(base_path('.env.example'))
            ->andThrow(new \Exception('File is corrupted or unreadable'))
        ;

        $result = $this->securityService->checkEnvironmentFile();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('corrupted', strtolower($result['message']));
    }

    public function testCheckSecurityMiddlewareWithMissingKernelFile(): void
    {
        // Mock File facade to simulate missing kernel file
        File::shouldReceive('exists')
            ->with(app_path('Http/Kernel.php'))
            ->andReturn(false)
        ;

        $result = $this->securityService->checkSecurityMiddleware();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('kernel file not found', strtolower($result['message']));
    }

    public function testCheckSecurityMiddlewareWithUnreadableKernelFile(): void
    {
        // Mock File facade to simulate unreadable kernel file
        File::shouldReceive('exists')
            ->with(app_path('Http/Kernel.php'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(app_path('Http/Kernel.php'))
            ->andThrow(new \Exception('Permission denied reading kernel file'))
        ;

        $result = $this->securityService->checkSecurityMiddleware();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('permission denied', strtolower($result['message']));
    }

    public function testCheckSecurityMiddlewareWithMalformedKernelFile(): void
    {
        // Mock File facade to return malformed PHP content
        File::shouldReceive('exists')
            ->with(app_path('Http/Kernel.php'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(app_path('Http/Kernel.php'))
            ->andReturn('<?php invalid php syntax {{{')
        ;

        $result = $this->securityService->checkSecurityMiddleware();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('malformed', strtolower($result['message']));
    }

    public function testCheckDependenciesWithMissingComposerLock(): void
    {
        // Mock File facade to simulate missing composer.lock
        File::shouldReceive('exists')
            ->with(base_path('composer.lock'))
            ->andReturn(false)
        ;

        $result = $this->securityService->checkDependencies();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('composer.lock not found', strtolower($result['message']));
    }

    public function testCheckDependenciesWithCorruptedComposerLock(): void
    {
        // Mock File facade to return corrupted JSON
        File::shouldReceive('exists')
            ->with(base_path('composer.lock'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(base_path('composer.lock'))
            ->andReturn('{"invalid": json syntax}')
        ;

        $result = $this->securityService->checkDependencies();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('corrupted', strtolower($result['message']));
    }

    public function testCheckDependenciesWithEmptyComposerLock(): void
    {
        // Mock File facade to return empty file
        File::shouldReceive('exists')
            ->with(base_path('composer.lock'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(base_path('composer.lock'))
            ->andReturn('')
        ;

        $result = $this->securityService->checkDependencies();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('empty', strtolower($result['message']));
    }

    public function testCheckDependenciesWithMissingPackagesArray(): void
    {
        // Mock File facade to return JSON without packages array
        File::shouldReceive('exists')
            ->with(base_path('composer.lock'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(base_path('composer.lock'))
            ->andReturn('{"_readme": ["This file locks the dependencies"]}')
        ;

        $result = $this->securityService->checkDependencies();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('packages array not found', strtolower($result['message']));
    }

    public function testCheckHttpsConfigurationWithNullAppUrl(): void
    {
        // Mock config('app.url') to return null
        // config() helper uses Config::get() internally
        Config::shouldReceive('get')
            ->with('app.url', null)
            ->andReturn(null)
            ->once();

        $result = $this->securityService->checkHttpsConfiguration();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('https not configured', strtolower($result['message']));
    }

    public function testCheckHttpsConfigurationWithMalformedUrl(): void
    {
        // Override default Config::get() for this specific test
        // Mock config('app.url') to return 'not-a-valid-url'
        // config() helper uses Config::get() internally
        Config::shouldReceive('get')
            ->with('app.url', null)
            ->andReturn('not-a-valid-url')
            ->once();

        $result = $this->securityService->checkHttpsConfiguration();

        self::assertFalse($result['passed']);
        // The actual message is "app.url contains malformed URL"
        self::assertStringContainsString('malformed', strtolower($result['message']));
    }

    public function testCheckHttpsConfigurationWithLocalhostException(): void
    {
        // Override default Config::get() for this specific test
        // Mock config('app.url') to return 'http://localhost:8000'
        // config() helper uses Config::get() internally
        Config::shouldReceive('get')
            ->with('app.url', null)
            ->andReturn('http://localhost:8000')
            ->once();

        $result = $this->securityService->checkHttpsConfiguration();

        // Should pass for localhost even with HTTP
        self::assertTrue($result['passed']);
        // The message is "HTTP allowed for localhost (development environment)"
        self::assertStringContainsString('localhost', strtolower($result['message']));
    }

    public function testCheckDebugModeWithMissingConfig(): void
    {
        Config::shouldReceive('get')
            ->with('app.debug', \Mockery::any())
            ->andReturn(null)
            ->once();

        $result = $this->securityService->checkDebugMode();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('debug config not found', strtolower($result['message']));
    }

    public function testCheckDebugModeWithInvalidConfigType(): void
    {
        // Override default Config::get() for this specific test
        // Mock config('app.debug') to return 'invalid_boolean_value'
        // config() helper uses Config::get() internally
        Config::shouldReceive('get')
            ->with('app.debug', null)
            ->andReturn('invalid_boolean_value')
            ->once();

        $result = $this->securityService->checkDebugMode();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('invalid debug value', strtolower($result['message']));
    }

    public function testAnalyzeWithFileSystemFailure(): void
    {
        // Allow RefreshDatabase to access config during setup
        Config::shouldReceive('offsetGet')
            ->with(\Mockery::any())
            ->andReturnUsing(function ($key) {
                return match ($key) {
                    'database.default' => 'sqlite',
                    'database.connections.sqlite.driver' => 'sqlite',
                    'database.connections.sqlite.database' => ':memory:',
                    default => null,
                };
            })
            ->byDefault();

        Config::shouldReceive('set')->andReturnSelf()->byDefault();
        Config::shouldReceive('get')->andReturn(null)->byDefault();

        // Mock multiple file operations to fail
        File::shouldReceive('exists')
            ->andThrow(new \Exception('File system failure'))
            ->byDefault();

        $result = $this->securityService->analyze();

        self::assertIsArray($result);
        self::assertArrayHasKey('overall_score', $result);
        self::assertArrayHasKey('checks', $result);

        // Should have error messages for failed checks that use File facade
        // checkDebugMode and checkHttpsConfiguration don't use File, so they may have different errors
        $fileSystemChecks = array_filter($result['checks'], static function ($check) {
            return str_contains(strtolower($check['message']), 'file system') ||
                   str_contains(strtolower($check['message']), 'filesystem');
        });

        // At least checkDependencies, checkEnvironmentFile, and checkSecurityMiddleware should fail with file system errors
        self::assertGreaterThanOrEqual(3, count($fileSystemChecks));
    }

    public function testAnalyzeWithConfigurationFailure(): void
    {
        // Allow RefreshDatabase to access config during setup
        Config::shouldReceive('offsetGet')
            ->with(\Mockery::any())
            ->andReturnUsing(function ($key) {
                // Only throw exception for specific keys used by the service
                if (in_array($key, ['app.debug', 'app.url'], true)) {
                    throw new \Exception('Configuration system failure');
                }
                // Return defaults for database connection config
                return match ($key) {
                    'database.default' => 'sqlite',
                    'database.connections.sqlite.driver' => 'sqlite',
                    'database.connections.sqlite.database' => ':memory:',
                    default => null,
                };
            })
        ;

        Config::shouldReceive('set')->andReturnSelf();
        Config::shouldReceive('get')
            ->andThrow(new \Exception('Configuration system failure'))
        ;

        $result = $this->securityService->analyze();

        self::assertIsArray($result);
        self::assertArrayHasKey('overall_score', $result);
        self::assertArrayHasKey('checks', $result);

        // Should have error messages for failed config checks
        $configChecks = array_filter($result['checks'], static function ($check) {
            return false !== strpos(strtolower($check['message']), 'configuration');
        });

        self::assertNotEmpty($configChecks);
    }

    public function testCheckDependenciesWithExtremelyLargeComposerLock(): void
    {
        // Mock File facade to return extremely large JSON (simulating memory issues)
        File::shouldReceive('exists')
            ->with(base_path('composer.lock'))
            ->andReturn(true)
        ;

        $largeJson = json_encode([
            'packages' => array_fill(0, 10000, [
                'name' => 'vendor/package',
                'version' => '1.0.0',
                'time' => '2020-01-01T00:00:00+00:00',
            ]),
        ]);

        File::shouldReceive('get')
            ->with(base_path('composer.lock'))
            ->andReturn($largeJson)
        ;

        $result = $this->securityService->checkDependencies();

        // Should handle large files gracefully
        self::assertIsArray($result);
        self::assertArrayHasKey('passed', $result);
        self::assertArrayHasKey('message', $result);
    }

    public function testCheckSecurityMiddlewareWithCircularDependency(): void
    {
        // Mock File facade to return kernel file with circular middleware references
        File::shouldReceive('exists')
            ->with(app_path('Http/Kernel.php'))
            ->andReturn(true)
        ;

        $kernelContent = '<?php
        class Kernel {
            protected $middleware = [
                \App\Http\Middleware\SecurityHeadersMiddleware::class,
                \App\Http\Middleware\SecurityHeadersMiddleware::class, // Duplicate
            ];
        }';

        File::shouldReceive('get')
            ->with(app_path('Http/Kernel.php'))
            ->andReturn($kernelContent)
        ;

        $result = $this->securityService->checkSecurityMiddleware();

        // Should detect and handle duplicate middleware registrations
        self::assertTrue($result['passed']); // Still passes but might warn about duplicates
    }

    public function testCheckEnvironmentFileWithSymlinkAttack(): void
    {
        // Mock File facade to simulate symlink attack scenario
        File::shouldReceive('exists')
            ->with(base_path('.env.example'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(base_path('.env.example'))
            ->andThrow(new \Exception('Symlink attack detected'))
        ;

        $result = $this->securityService->checkEnvironmentFile();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('symlink', strtolower($result['message']));
    }

    public function testCheckDependenciesWithNetworkTimeout(): void
    {
        // Simulate scenario where dependency check requires network access and times out
        File::shouldReceive('exists')
            ->with(base_path('composer.lock'))
            ->andReturn(true)
        ;

        File::shouldReceive('get')
            ->with(base_path('composer.lock'))
            ->andThrow(new \Exception('Network timeout while checking dependencies'))
        ;

        $result = $this->securityService->checkDependencies();

        self::assertFalse($result['passed']);
        self::assertStringContainsString('network timeout', strtolower($result['message']));
    }

    public function testAnalyzeWithPartialFailures(): void
    {
        // Allow RefreshDatabase to access config during setup
        Config::shouldReceive('offsetGet')
            ->with(\Mockery::any())
            ->andReturnUsing(function ($key) {
                return match ($key) {
                    'database.default' => 'sqlite',
                    'database.connections.sqlite.driver' => 'sqlite',
                    'database.connections.sqlite.database' => ':memory:',
                    default => null,
                };
            })
            ->byDefault();

        // Mock some checks to pass and others to fail
        Config::shouldReceive('set')->andReturnSelf()->byDefault();

        // Default Config::get() to return null, but override specific keys
        Config::shouldReceive('get')
            ->with('app.debug', null)
            ->andReturn(false) // This should pass
            ->byDefault();

        Config::shouldReceive('get')
            ->with('app.url', null)
            ->andReturn('https://example.com') // This should pass
            ->byDefault();

        Config::shouldReceive('get')
            ->andReturn(null)
            ->byDefault();

        File::shouldReceive('exists')
            ->with(base_path('.env.example'))
            ->andThrow(new \Exception('Permission denied')) // This should fail
            ->byDefault();

        File::shouldReceive('exists')
            ->with(base_path('composer.lock'))
            ->andReturn(false) // This should fail
            ->byDefault();

        File::shouldReceive('exists')
            ->with(app_path('Http/Kernel.php'))
            ->andReturn(false) // This should fail
            ->byDefault();

        $result = $this->securityService->analyze();

        self::assertIsArray($result);
        self::assertArrayHasKey('overall_score', $result);
        self::assertArrayHasKey('checks', $result);

        // Should have mixed results
        $passedChecks = array_filter($result['checks'], static fn ($check) => $check['passed']);
        $failedChecks = array_filter($result['checks'], static fn ($check) => ! $check['passed']);

        self::assertNotEmpty($passedChecks);
        self::assertNotEmpty($failedChecks);

        // Overall score should be between 0 and 100
        self::assertGreaterThanOrEqual(0, $result['overall_score']);
        self::assertLessThanOrEqual(100, $result['overall_score']);
    }
}
