<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\GeolocationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DetectUserLocale
{
    public function __construct(
        private readonly GeolocationService $geolocationService
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        // Skip for API routes, admin routes, and already detected
        if ($request->is('api/*') || $request->is('admin/*') || $this->geolocationService->hasDetectedLocale()) {
            return $next($request);
        }

        try {
            // Get user's IP address
            $ipAddress = $request->ip();

            // Detect locale from IP
            $detectedLocale = $this->geolocationService->detectLocaleFromIP($ipAddress);

            // Apply detected locale
            $this->geolocationService->applyLocale($detectedLocale, $request->user());

            Log::info('Auto-detected user locale', [
                'ip' => $ipAddress,
                'locale' => $detectedLocale,
            ]);
        } catch (\Throwable $e) {
            // Don't break the app if geolocation fails
            Log::warning('Locale auto-detection failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
