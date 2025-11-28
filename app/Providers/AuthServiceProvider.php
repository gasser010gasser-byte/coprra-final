<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * AuthServiceProvider handles authentication and authorization services.
 *
 * In Laravel 12, policies are auto-discovered from app/Policies directory.
 * No need to manually register them in $policies array.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Policies are auto-discovered in Laravel 12
        // No need to call $this->registerPolicies()

        // Allow only admins to view the Laravel Pulse dashboard
        Gate::define('viewPulse', function (?User $user): bool {
            return $user !== null && method_exists($user, 'hasRole') && $user->hasRole('admin');
        });
    }
}
