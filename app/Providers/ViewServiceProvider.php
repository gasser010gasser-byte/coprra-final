<?php

/** @psalm-suppress UnusedClass */

declare(strict_types=1);

namespace App\Providers;

use App\View\Composers\AppComposer;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as IlluminateView;

class ViewServiceProvider extends ServiceProvider
{
    private const BREADCRUMB_CONFIG = [
        'products.show' => [
            'parent_name' => 'Products',
            'parent_route' => 'products.index',
            // Routes use slug parameters; handle safely in composer
            'param_name' => 'slug',
        ],
        'categories.show' => [
            'parent_name' => 'Categories',
            'parent_route' => 'categories.index',
            'param_name' => 'slug',
        ],
        'brands.show' => [
            'parent_name' => 'Brands',
            'parent_route' => 'brands.index',
            // Use slug when brand show route is present
            'param_name' => 'slug',
        ],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGlobalComposers();
        $this->registerLayoutComposers();
        $this->registerBreadcrumbComposers();
    }

    private function registerGlobalComposers(): void
    {
        // Limit global data to navigation to avoid variable collisions in page views
        View::composer(['layouts.navigation'], AppComposer::class);
    }

    private function registerLayoutComposers(): void
    {
        View::composer(['layouts.app', 'layouts.admin'], static function (IlluminateView $view): void {
            $view->with('user', auth()->user());
        });
    }

    private function registerBreadcrumbComposers(): void
    {
        View::composer(['products.*', 'categories.*', 'brands.*'], function (IlluminateView $view): void {
            $view->with('breadcrumbs', $this->getBreadcrumbs());
        });
    }

    /**
     * Get breadcrumbs for the current page.
     *
     * @return array<int, array<string, string|null>>
     */
    private function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
        ];

        $route = request()->route();

        if (! $route instanceof Route) {
            return $breadcrumbs;
        }

        $routeName = $route->getName();

        if (isset(self::BREADCRUMB_CONFIG[$routeName])) {
            $this->addConfiguredBreadcrumbs($breadcrumbs, $route, self::BREADCRUMB_CONFIG[$routeName]);
        }

        return $breadcrumbs;
    }

    /**
     * Add breadcrumbs based on the route configuration.
     *
     * @param  array<int, array<string, string|null>> $breadcrumbs
     * @param array<string, string> $config
     */
    private function addConfiguredBreadcrumbs(array &$breadcrumbs, Route $route, array $config): void
    {
        $breadcrumbs[] = ['name' => $config['parent_name'], 'url' => route($config['parent_route'])];

        $param = $route->parameter($config['param_name']);

        // Support both object-bound params and simple slug/string params
        $name = null;
        if (\is_object($param) && isset($param->name)) {
            /** @var object $param */
            $name = $param->name;
        } elseif (\is_string($param) && $param !== '') {
            $name = $param;
        }

        if (null !== $name) {
            $breadcrumbs[] = ['name' => $name, 'url' => null];
        }
    }
}
