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
        if (! $user) {
            abort(403, 'Access denied. Admin privilege required.');
        }
        
        // Allow admin or moderator roles
        $isAdmin = (bool) ($user->is_admin ?? false);
        $isModerator = $user->role === 'moderator' || $user->role === 'admin';
        
        if (! $isAdmin && ! $isModerator) {
            abort(403, 'Access denied. Admin privilege required.');
        }

        return $next($request);
    }
}
