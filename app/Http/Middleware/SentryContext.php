<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SentryContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('sentry') && auth()->check()) {
            \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                $user = auth()->user();
                $scope->setUser([
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->name ?? $user->email,
                ]);
            });
        }

        return $next($request);
    }
}
