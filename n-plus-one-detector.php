<?php

declare(strict_types=1);

/**
 * N+1 Query Detector
 *
 * This script automatically scans all GET routes in the application,
 * makes internal requests to each route, and reports routes that
 * exceed a query threshold, indicating potential N+1 problems.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

// Bootstrap the application
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Configuration
$queryThreshold = 10; // Routes exceeding this count are flagged
$excludedPatterns = [
    '_debugbar',
    '_ignition',
    'telescope',
    'pulse',
    'api/',
    'sanctum/',
    'storage/',
    'assets/',
    'css/',
    'js/',
    'images/',
    'fonts/',
];

// Initialize results
$results = [
    'scanned' => [],
    'problematic' => [],
    'errors' => [],
    'total_routes' => 0,
    'scanned_routes' => 0,
];

echo "========================================\n";
echo "   N+1 Query Detector\n";
echo "========================================\n\n";
echo "Configuration:\n";
echo "  • Query Threshold: {$queryThreshold} queries\n";
echo "  • Excluded Patterns: " . implode(', ', $excludedPatterns) . "\n\n";

// Get all registered routes
$routes = $app->make('router')->getRoutes();
$results['total_routes'] = $routes->count();

echo "Total routes found: {$results['total_routes']}\n";
echo "Filtering routes...\n\n";

// Filter routes
$filteredRoutes = [];

foreach ($routes as $route) {
    $methods = $route->methods();
    $uri = $route->uri();

    // Only process GET routes
    if (!in_array('GET', $methods)) {
        continue;
    }

    // Skip excluded patterns
    $shouldExclude = false;
    foreach ($excludedPatterns as $pattern) {
        if (str_contains($uri, $pattern)) {
            $shouldExclude = true;
            break;
        }
    }

    if ($shouldExclude) {
        continue;
    }

    // Get route parameters
    $parameters = $route->parameterNames();
    
    // Skip routes with required parameters (we'll handle them separately if needed)
    // For now, include all routes and handle errors gracefully
    
    $filteredRoutes[] = [
        'uri' => $uri,
        'name' => $route->getName() ?? 'unnamed',
        'action' => $route->getActionName(),
        'parameters' => $parameters,
        'route' => $route,
    ];
}

$results['scanned_routes'] = count($filteredRoutes);
echo "Routes to scan: {$results['scanned_routes']}\n\n";
echo "Starting scan...\n\n";

// Scan each route
$progress = 0;
foreach ($filteredRoutes as $routeInfo) {
    $progress++;
    $uri = $routeInfo['uri'];
    $routeName = $routeInfo['name'];

    echo "[{$progress}/{$results['scanned_routes']}] Scanning: {$uri}";

    try {
        // Skip routes with required parameters for now
        if (count($routeInfo['parameters']) > 0) {
            // Try to create a request with dummy parameters
            $uriWithParams = $uri;
            foreach ($routeInfo['parameters'] as $param) {
                // Replace parameter placeholders with dummy values
                $uriWithParams = str_replace('{'.$param.'}', '1', $uriWithParams);
                $uriWithParams = str_replace('{'.$param.'?}', '1', $uriWithParams);
            }
            
            // If URI still has parameters, skip it
            if (preg_match('/\{[^}]+\}/', $uriWithParams)) {
                echo " ⚠️  SKIPPED (has required parameters)\n";
                continue;
            }
            
            $uri = $uriWithParams;
        }
        
        // Reset query log and Debugbar
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        // Create a request
        $request = Illuminate\Http\Request::create($uri, 'GET');

        // Handle the request
        $response = $kernel->handle($request);

        // Get query count from Debugbar if available
        $queryCount = 0;
        $queryLog = \Illuminate\Support\Facades\DB::getQueryLog();
        $queryCountFromLog = count($queryLog);

        if (app()->bound('debugbar')) {
            try {
                $debugbar = app('debugbar');
                if ($debugbar && $debugbar->isEnabled() && $debugbar->hasCollector('queries')) {
                    $queriesCollector = $debugbar->getCollector('queries');
                    $queryCount = $queriesCollector->count();
                } else {
                    // Use query log if Debugbar not enabled or no collector
                    $queryCount = $queryCountFromLog;
                }
            } catch (\Exception $e) {
                // Fallback to DB query log if Debugbar fails
                $queryCount = $queryCountFromLog;
            }
        } else {
            // Fallback to DB query log
            $queryCount = $queryCountFromLog;
        }
        
        // If still 0, try to get from the response if Debugbar data is embedded
        if ($queryCount === 0 && $response->getContent()) {
            $content = $response->getContent();
            // Try to extract from Debugbar JSON if present
            if (preg_match('/"queries":\{"nb_statements":(\d+)/', $content, $matches)) {
                $queryCount = (int)$matches[1];
            }
        }

        // Record result
        $result = [
            'uri' => $uri,
            'name' => $routeName,
            'query_count' => $queryCount,
            'status' => $response->getStatusCode(),
        ];

        $results['scanned'][] = $result;

        // Check if problematic
        if ($queryCount > $queryThreshold) {
            $results['problematic'][] = $result;
            echo " ⚠️  {$queryCount} queries (THRESHOLD EXCEEDED)\n";
        } else {
            echo " ✓ {$queryCount} queries\n";
        }

        // Terminate the request
        $kernel->terminate($request, $response);

    } catch (\Exception $e) {
        $error = [
            'uri' => $uri,
            'name' => $routeName,
            'error' => $e->getMessage(),
        ];
        $results['errors'][] = $error;
        echo " ✗ ERROR: " . $e->getMessage() . "\n";
    } catch (\Error $e) {
        $error = [
            'uri' => $uri,
            'name' => $routeName,
            'error' => $e->getMessage(),
        ];
        $results['errors'][] = $error;
        echo " ✗ ERROR: " . $e->getMessage() . "\n";
    }
}

// Generate report
echo "\n";
echo "========================================\n";
echo "   N+1 Query Detection Report\n";
echo "========================================\n\n";

echo "Summary:\n";
echo "  • Total Routes: {$results['total_routes']}\n";
echo "  • Routes Scanned: {$results['scanned_routes']}\n";
echo "  • Problematic Routes: " . count($results['problematic']) . "\n";
echo "  • Errors: " . count($results['errors']) . "\n\n";

if (count($results['problematic']) > 0) {
    echo "⚠️  PROBLEMATIC ROUTES (Exceeding {$queryThreshold} queries):\n\n";
    echo str_pad("Route Path", 50) . " | " . str_pad("Query Count", 15) . " | Status\n";
    echo str_repeat("-", 50) . "-+-" . str_repeat("-", 15) . "-+-" . str_repeat("-", 20) . "\n";

    // Sort by query count descending
    usort($results['problematic'], function ($a, $b) {
        return $b['query_count'] <=> $a['query_count'];
    });

    foreach ($results['problematic'] as $route) {
        $uri = str_pad(substr($route['uri'], 0, 48), 50);
        $count = str_pad((string)$route['query_count'], 15);
        $status = "⚠️  N+1 Problem Likely";
        echo "{$uri} | {$count} | {$status}\n";
    }
    echo "\n";
} else {
    echo "✅ No routes exceeded the query threshold!\n\n";
}

if (count($results['errors']) > 0) {
    echo "❌ ERRORS ENCOUNTERED:\n\n";
    foreach ($results['errors'] as $error) {
        echo "  • {$error['uri']}: {$error['error']}\n";
    }
    echo "\n";
}

// Save detailed report to file
$reportFile = 'n-plus-one-detection-report.json';
file_put_contents($reportFile, json_encode($results, JSON_PRETTY_PRINT));
echo "📄 Detailed report saved to: {$reportFile}\n\n";

echo "========================================\n";
echo "   Scan Complete\n";
echo "========================================\n";

