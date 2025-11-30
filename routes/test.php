<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PriceSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

// Simple test route
Route::get('/test-simple', static function () {
    return response()->json(['message' => 'Simple test route works']);
});

// Test route for API tests
Route::get('/test', static function () {
    return response()->json([
        'data' => ['message' => 'API test route works'],
        'status' => 'success',
    ]);
});

// POST route for validation testing
Route::post('/test', static function (Request $request) {
    try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        return response()->json(['message' => 'Validation passed', 'data' => $validated]);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }
});

// Temporary best offer route outside middleware - test method call
Route::get('/best-offer-debug', static function (Request $request) {
    try {
        $controller = app(PriceSearchController::class);

        return $controller->bestOffer($request);
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Method call failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// Direct test of the bestOffer method
Route::get('/direct-best-offer', static function (Request $request) {
    return response()->json([
        'message' => 'Direct route test',
        'params' => $request->all(),
        'method' => 'bestOffer',
        'controller' => 'PriceSearchController',
    ]);
});

// Test API routes for external service testing
Route::middleware(['throttle:public'])->group(static function (): void {
    Route::get('/external-data', static function () {
        try {
            $response = Http::get('https://api.external-service.com/data');

            return response()->json($response->json(), $response->status());
        } catch (Exception $e) {
            return response()->json(['error' => 'External service unavailable'], 503);
        }
    });

    Route::get('/slow-external-data', static function () {
        try {
            $response = Http::timeout(3)->get('https://api.slow-service.com/data');

            return response()->json($response->json(), $response->status());
        } catch (Exception $e) {
            return response()->json(['error' => 'Service timeout'], 408);
        }
    });

    Route::get('/error-external-data', static function () {
        try {
            $response = Http::get('https://api.error-service.com/data');
            if ($response->status() >= 400) {
                return response()->json(['error' => 'External service error'], 502);
            }

            return response()->json($response->json(), $response->status());
        } catch (Exception $e) {
            return response()->json(['error' => 'External service unavailable'], 503);
        }
    });

    Route::get('/authenticated-external-data', static function () {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer test-token',
            ])->get('https://api.authenticated-service.com/data');

            return response()->json($response->json(), $response->status());
        } catch (Exception $e) {
            return response()->json(['error' => 'Authentication failed'], 401);
        }
    });

    Route::get('/rate-limited-external-data', static function () {
        try {
            $response = Http::get('https://api.rate-limited-service.com/data');
            if (429 === $response->status()) {
                return response()->json(['error' => 'Rate limited'], 429);
            }

            return response()->json($response->json(), $response->status());
        } catch (Exception $e) {
            return response()->json(['error' => 'Service unavailable'], 503);
        }
    });

    Route::get('/cached-external-data', static function () {
        return Cache::remember('external-data', 60, static function () {
            try {
                $response = Http::get('https://api.cacheable-service.com/data');

                return $response->json();
            } catch (Exception $e) {
                return ['error' => 'Service unavailable'];
            }
        });
    });

    Route::get('/fallback-external-data', static function () {
        try {
            // Try primary service first
            $response = Http::get('https://api.primary-service.com/data');
            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }
        } catch (Exception $e) {
            // Primary service failed, try fallback
        }

        try {
            // Try fallback service
            $response = Http::get('https://api.fallback-service.com/data');

            return response()->json($response->json(), $response->status());
        } catch (Exception $e) {
            return response()->json(['error' => 'All services unavailable'], 503);
        }
    });
});
