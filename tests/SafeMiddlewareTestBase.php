<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\ViewServiceProvider;

/**
 * Safe base class for middleware tests that avoids full Laravel bootstrap
 * to prevent error/exception handler modifications, while providing DB setup.
 */
class SafeMiddlewareTestBase extends SafeTestBase
{
    use DatabaseSetup;

    /**
     * Set up the test environment with DB setup, without full app bootstrap.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Bootstrap a minimal Laravel application to make facades like Schema/DB available
        $this->app = $this->createApplication();
        // Ensure global container/facade application points to this app instance
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);
        // Register minimal providers required by the middleware (view depends on events/filesystem)
        $this->app->register(EventServiceProvider::class);
        $this->app->register(FilesystemServiceProvider::class);
        $this->app->register(ViewServiceProvider::class);
        // Bind a default HTTP request so services like UrlGenerator receive a valid Request instance
        $defaultRequest = Request::create('/', 'GET');
        $this->app->instance('request', $defaultRequest);
        $this->app->instance(Request::class, $defaultRequest);
        if ($this->app->bound('url')) {
            $this->app['url']->setRequest($defaultRequest);
        }
        // Ensure Schema facade accessor is available in safe tests
        if (! $this->app->bound('db.schema')) {
            $this->app->singleton('db.schema', static function ($app) {
                return $app['db']->connection()->getSchemaBuilder();
            });
        }
        // Bind a minimal 'view' service so the view() helper works without full ViewServiceProvider
        // If provider did not bind view for any reason, add a lightweight fallback
        if (! $this->app->bound('view')) {
            $this->app->singleton('view', static function () {
                return new class () {
                    public function share($key, $value)
                    { // no-op for tests
                    }
                };
            });
        }
        $this->setUpDatabase();
    }

    /**
     * Tear down the test environment with DB cleanup.
     */
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
        parent::tearDown();
    }
}
