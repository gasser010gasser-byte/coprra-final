<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! (bool) ($user->is_admin ?? false)) {
            abort(403, 'Access denied. Admin privilege required.');
        }

        return $next($request);
    }
}
