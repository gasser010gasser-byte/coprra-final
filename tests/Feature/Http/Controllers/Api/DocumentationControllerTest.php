<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @internal
 *
 * @coversNothing
 */
final class DocumentationControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function testCanGetApiStatus()
    {
        $response = $this->getJson('/api/');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'version',
                'timestamp',
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'COPRRA API is running',
                'version' => '1.0.0',
            ])
        ;
    }

    public function testCanGetHealthStatus()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
                'environment',
                'database',
                'cache',
                'storage',
            ])
            ->assertJson([
                'status' => 'healthy',
                'database' => 'connected',
                'cache' => 'working',
                'storage' => 'writable',
            ])
        ;
    }

    public function testReturnsUnhealthyStatusWhenDatabaseFails()
    {
        // Mock database connection failure
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \Exception('Database connection failed'))
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'database' => 'disconnected',
            ])
        ;
    }

    public function testReturnsUnhealthyStatusWhenCacheFails()
    {
        // Mock cache failure
        Cache::shouldReceive('put')
            ->andThrow(new \Exception('Cache failed'))
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'cache' => 'not_working',
            ])
        ;
    }

    public function testReturnsUnhealthyStatusWhenStorageIsNotWritable()
    {
        // Mock storage as not writable
        $this->app->instance('filesystem', static function () {
            return new class () {
                public function isWritable($path)
                {
                    return false;
                }
            };
        });

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'storage' => 'not_writable',
            ])
        ;
    }

    public function testIncludesTimestampInStatusResponse()
    {
        $response = $this->getJson('/api/');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timestamp',
            ])
        ;

        // Verify timestamp is valid ISO format
        $timestamp = $response->json('timestamp');
        self::assertIsString($timestamp);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $timestamp);
    }

    public function testIncludesTimestampInHealthResponse()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'timestamp',
            ])
        ;

        // Verify timestamp is valid ISO format
        $timestamp = $response->json('timestamp');
        self::assertIsString($timestamp);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $timestamp);
    }

    public function testIncludesVersionInStatusResponse()
    {
        $response = $this->getJson('/api/');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'version',
            ])
        ;

        $version = $response->json('version');
        self::assertIsString($version);
        self::assertSame('1.0.0', $version);
    }

    public function testIncludesVersionInHealthResponse()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'version',
            ])
        ;

        $version = $response->json('version');
        self::assertIsString($version);
        self::assertNotEmpty($version);
    }

    public function testIncludesEnvironmentInHealthResponse()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'environment',
            ])
        ;

        $environment = $response->json('environment');
        self::assertIsString($environment);
        self::assertContains($environment, ['local', 'testing', 'staging', 'production']);
    }

    public function testTestsDatabaseConnectionInHealthCheck()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $database = $response->json('database');
        self::assertContains($database, ['connected', 'disconnected']);
    }

    public function testTestsCacheFunctionalityInHealthCheck()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $cache = $response->json('cache');
        self::assertContains($cache, ['working', 'not_working']);
    }

    public function testTestsStorageWritabilityInHealthCheck()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $storage = $response->json('storage');
        self::assertContains($storage, ['writable', 'not_writable']);
    }

    public function testHandlesMultipleSystemFailuresInHealthCheck()
    {
        // Mock both database and cache failures
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \Exception('Database connection failed'))
        ;

        Cache::shouldReceive('put')
            ->andThrow(new \Exception('Cache failed'))
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'database' => 'disconnected',
                'cache' => 'not_working',
            ])
        ;
    }

    public function testReturns200ForHealthySystems()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
    }

    public function testReturns503ForUnhealthySystems()
    {
        // Mock database failure
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \Exception('Database connection failed'))
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(503);
    }

    public function testHandlesCacheTestSuccessfully()
    {
        // Mock successful cache test
        Cache::shouldReceive('put')
            ->with('health_check', 'ok', 60)
            ->once()
        ;

        Cache::shouldReceive('get')
            ->with('health_check')
            ->andReturn('ok')
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'cache' => 'working',
            ])
        ;
    }

    public function testHandlesCacheTestFailure()
    {
        // Mock cache test failure
        Cache::shouldReceive('put')
            ->with('health_check', 'ok', 60)
            ->once()
        ;

        Cache::shouldReceive('get')
            ->with('health_check')
            ->andReturn(null)
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'cache' => 'not_working',
            ])
        ;
    }

    public function testHandlesDatabaseConnectionException()
    {
        // Mock database connection exception
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \Exception('Connection failed'))
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'database' => 'disconnected',
            ])
        ;
    }

    public function testHandlesCacheException()
    {
        // Mock cache exception
        Cache::shouldReceive('put')
            ->andThrow(new \Exception('Cache failed'))
        ;

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'cache' => 'not_working',
            ])
        ;
    }

    public function testHandlesStorageException()
    {
        // Mock storage exception
        $this->app->instance('filesystem', static function () {
            throw new \Exception('Storage failed');
        });

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
                'storage' => 'not_writable',
            ])
        ;
    }

    public function testReturnsConsistentStatusMessage()
    {
        $response = $this->getJson('/api/');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'COPRRA API is running',
            ])
        ;
    }

    public function testReturnsConsistentHealthMessageForHealthySystems()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ])
        ;
    }

    public function testReturnsConsistentHealthMessageForUnhealthySystems()
    {
        // Mock database failure
        DB::shouldReceive('connection->getPdo')
            ->andThrow(new \Exception('Database connection failed'));

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'unhealthy',
            ]);
    }
}
