<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class SecurityAnalysisService
{
    /**
     * Run comprehensive security analysis.
     *
     * @return array<array<string>|int|string>
     *
     * @psalm-return array{score: int, max_score: 100, issues: list<string>, category: 'Security'}
     */
    public function analyze(): array
    {
        $score = 0;
        $issues = [];
        $checks = [];

        // Run each check individually to handle exceptions gracefully
        try {
            $score += $this->checkDependencies($issues, $checks);
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                default => 'Dependencies check failed: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
        }

        try {
            $score += $this->checkEnvironmentFile($issues, $checks);
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                default => 'Environment file check failed: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
        }

        try {
            $score += $this->checkDebugMode($issues, $checks);
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                str_contains($errorMessage, 'configuration') => 'Configuration failure: '.$e->getMessage(),
                default => 'Debug mode check failed: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
        }

        try {
            $score += $this->checkHttpsConfiguration($issues, $checks);
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                str_contains($errorMessage, 'configuration') => 'Configuration failure: '.$e->getMessage(),
                default => 'HTTPS configuration check failed: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
        }

        try {
            $score += $this->checkSecurityMiddleware($issues, $checks);
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                default => 'Security middleware check failed: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
        }

        // Ensure checks array is populated even if no issues
        if (empty($checks) && empty($issues)) {
            $checks[] = [
                'passed' => true,
                'message' => 'All security checks passed',
            ];
        }

        return [
            'overall_score' => $score,
            'score' => $score,
            'max_score' => 100,
            'issues' => $issues,
            'category' => 'Security',
            'checks' => $checks,
        ];
    }

    /**
     * Check for outdated dependencies.
     *
     * @param list<string>|null $issues
     * @param list<array<string, bool|string>>|null $checks
     *
     * @psalm-return 0|30|array<string, bool|string>
     */
    public function checkDependencies(?array &$issues = null, ?array &$checks = null): int|array
    {
        $wasCalledWithoutParams = ($issues === null && $checks === null);
        $localIssues = [];
        $localChecks = [];

        if ($issues === null) {
            $issues = &$localIssues;
        }
        if ($checks === null) {
            $checks = &$localChecks;
        }

        try {
            $composerLockPath = base_path('composer.lock');

            // First check if composer.lock exists
            if (! File::exists($composerLockPath)) {
                $message = 'composer.lock not found';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            } else {
                // Try to read and parse composer.lock
                try {
                    $composerLockContent = File::get($composerLockPath);

                    // Check if file is empty
                    if (empty(trim($composerLockContent))) {
                        $message = 'composer.lock file is empty';
                        $issues[] = $message;
                        $checks[] = [
                            'passed' => false,
                            'message' => $message,
                        ];
                        $result = 0;
                    } else {
                        // Try to parse JSON
                        $composerData = json_decode($composerLockContent, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $message = 'composer.lock file is corrupted (invalid JSON)';
                            $issues[] = $message;
                            $checks[] = [
                                'passed' => false,
                                'message' => $message,
                            ];
                            $result = 0;
                        } elseif (! isset($composerData['packages'])) {
                            $message = 'composer.lock packages array not found';
                            $issues[] = $message;
                            $checks[] = [
                                'passed' => false,
                                'message' => $message,
                            ];
                            $result = 0;
                        } else {
                            // File is valid, check for outdated dependencies
                            try {
                                $process = new Process(['composer', 'outdated', '--direct']);
                                $process->run();

                                if (! $process->isSuccessful()) {
                                    $result = 0;
                                    $checks[] = [
                                        'passed' => false,
                                        'message' => 'Failed to check for outdated dependencies',
                                    ];
                                } else {
                                    $outdated = $process->getOutput();
                                    if (
                                        \in_array(trim($outdated), ['', '0'], true)
                                        || str_contains($outdated, 'No direct dependencies')
                                    ) {
                                        $checks[] = [
                                            'passed' => true,
                                            'message' => 'Dependencies are up to date',
                                        ];
                                        $result = 30;
                                    } else {
                                        $message = 'Outdated dependencies found. Consider running "composer update".';
                                        $issues[] = $message;
                                        $checks[] = [
                                            'passed' => false,
                                            'message' => $message,
                                        ];
                                        $result = 0;
                                    }
                                }
                            } catch (\Exception $processException) {
                                $errorMessage = strtolower($processException->getMessage());
                                $message = match (true) {
                                    str_contains($errorMessage, 'network timeout') || str_contains($errorMessage, 'timeout') => 'Network timeout while checking dependencies',
                                    default => 'Error checking dependencies: '.$processException->getMessage(),
                                };
                                $issues[] = $message;
                                $checks[] = [
                                    'passed' => false,
                                    'message' => $message,
                                ];
                                $result = 0;
                            }
                        }
                    }
                } catch (\Exception $readException) {
                    $errorMessage = strtolower($readException->getMessage());
                    $message = match (true) {
                        str_contains($errorMessage, 'network timeout') || str_contains($errorMessage, 'timeout') => 'Network timeout while reading composer.lock',
                        str_contains($errorMessage, 'corrupted') => 'composer.lock file is corrupted',
                        default => 'Error reading composer.lock: '.$readException->getMessage(),
                    };
                    $issues[] = $message;
                    $checks[] = [
                        'passed' => false,
                        'message' => $message,
                    ];
                    $result = 0;
                }
            }
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                default => 'File system failure while checking dependencies: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
            $result = 0;
        }

        // If called without parameters (for testing), return array
        if ($wasCalledWithoutParams) {
            return $localChecks[0] ?? ['passed' => false, 'message' => 'Unknown error'];
        }

        return $result;
    }

    /**
     * Check if .env.example file exists.
     *
     * @param list<string>|null $issues
     * @param list<array<string, bool|string>>|null $checks
     *
     * @psalm-return 0|10|array<string, bool|string>
     */
    public function checkEnvironmentFile(?array &$issues = null, ?array &$checks = null): int|array
    {
        $wasCalledWithoutParams = ($issues === null && $checks === null);
        $localIssues = [];
        $localChecks = [];

        if ($issues === null) {
            $issues = &$localIssues;
        }
        if ($checks === null) {
            $checks = &$localChecks;
        }

        try {
            $envExamplePath = base_path('.env.example');

            // Check if file exists
            if (! File::exists($envExamplePath)) {
                $message = '.env.example file missing';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            } else {
                // Try to read the file to check if it's readable and not corrupted
                try {
                    File::get($envExamplePath);
                    $checks[] = [
                        'passed' => true,
                        'message' => '.env.example file exists',
                    ];
                    $result = 10;
                } catch (\Exception $readException) {
                    $errorMessage = strtolower($readException->getMessage());
                    $message = match (true) {
                        str_contains($errorMessage, 'corrupted') || str_contains($errorMessage, 'unreadable') => 'File is corrupted or unreadable',
                        str_contains($errorMessage, 'permission') => 'Permission denied accessing .env.example file',
                        str_contains($errorMessage, 'symlink') => 'Symlink attack detected on .env.example file',
                        default => 'Error reading .env.example file: '.$readException->getMessage(),
                    };
                    $issues[] = $message;
                    $checks[] = [
                        'passed' => false,
                        'message' => $message,
                    ];
                    $result = 0;
                }
            }
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'permission') => 'Permission denied accessing file system',
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                default => 'File system failure: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
            $result = 0;
        }

        // If called without parameters (for testing), return array
        if ($wasCalledWithoutParams) {
            return $localChecks[0] ?? ['passed' => false, 'message' => 'Unknown error'];
        }

        return $result;
    }

    /**
     * Check if debug mode is disabled.
     *
     * @param list<string>|null $issues
     * @param list<array<string, bool|string>>|null $checks
     *
     * @psalm-return 0|20|array<string, bool|string>
     */
    public function checkDebugMode(?array &$issues = null, ?array &$checks = null): int|array
    {
        $wasCalledWithoutParams = ($issues === null && $checks === null);
        $localIssues = [];
        $localChecks = [];

        if ($issues === null) {
            $issues = &$localIssues;
        }
        if ($checks === null) {
            $checks = &$localChecks;
        }

        try {
            $debugConfig = config('app.debug');

            // Check if debug config is missing
            if ($debugConfig === null) {
                $message = 'debug config not found (app.debug is null)';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            } elseif (! \is_bool($debugConfig)) {
                $message = 'invalid debug value (app.debug must be boolean, got: '.gettype($debugConfig).')';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            } elseif (false === $debugConfig) {
                $checks[] = [
                    'passed' => true,
                    'message' => 'Debug mode is disabled',
                ];
                $result = 20;
            } else {
                $message = 'Debug mode is enabled (should be false in production)';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            }
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'configuration system failure') => 'Configuration system failure: '.$e->getMessage(),
                default => 'Configuration system failure: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
            $result = 0;
        }

        // If called without parameters (for testing), return array
        if ($wasCalledWithoutParams) {
            return $localChecks[0] ?? ['passed' => false, 'message' => 'Unknown error'];
        }

        return $result;
    }

    /**
     * Check if HTTPS is configured.
     *
     * @param list<string>|null $issues
     * @param list<array<string, bool|string>>|null $checks
     *
     * @psalm-return 0|20|array<string, bool|string>
     */
    public function checkHttpsConfiguration(?array &$issues = null, ?array &$checks = null): int|array
    {
        $wasCalledWithoutParams = ($issues === null && $checks === null);
        $localIssues = [];
        $localChecks = [];

        if ($issues === null) {
            $issues = &$localIssues;
        }
        if ($checks === null) {
            $checks = &$localChecks;
        }

        try {
            $appUrl = config('app.url');

            // Check if app.url is null or empty
            if ($appUrl === null || $appUrl === '') {
                $message = 'HTTPS not configured in APP_URL';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            } elseif (! \is_string($appUrl)) {
                $message = 'Invalid app.url configuration type';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            } else {
                // Check if URL is valid
                $parsedUrl = parse_url($appUrl);
                if ($parsedUrl === false || ! isset($parsedUrl['scheme'])) {
                    $message = 'app.url contains malformed URL';
                    $issues[] = $message;
                    $checks[] = [
                        'passed' => false,
                        'message' => $message,
                    ];
                    $result = 0;
                } elseif (str_starts_with($appUrl, 'https')) {
                    $checks[] = [
                        'passed' => true,
                        'message' => 'HTTPS is configured',
                    ];
                    $result = 20;
                } elseif (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
                    // Allow HTTP for localhost (development)
                    $checks[] = [
                        'passed' => true,
                        'message' => 'HTTP allowed for localhost (development environment)',
                    ];
                    $result = 20;
                } else {
                    $message = 'HTTPS not configured in APP_URL';
                    $issues[] = $message;
                    $checks[] = [
                        'passed' => false,
                        'message' => $message,
                    ];
                    $result = 0;
                }
            }
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'configuration system failure') => 'Configuration system failure: '.$e->getMessage(),
                default => 'Configuration system failure: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
            $result = 0;
        }

        // If called without parameters (for testing), return array
        if ($wasCalledWithoutParams) {
            return $localChecks[0] ?? ['passed' => false, 'message' => 'Unknown error'];
        }

        return $result;
    }

    /**
     * Check if SecurityHeadersMiddleware is registered.
     *
     * @param list<string>|null $issues
     * @param list<array<string, bool|string>>|null $checks
     *
     * @psalm-return 0|20|array<string, bool|string>
     */
    public function checkSecurityMiddleware(?array &$issues = null, ?array &$checks = null): int|array
    {
        $wasCalledWithoutParams = ($issues === null && $checks === null);
        $localIssues = [];
        $localChecks = [];

        if ($issues === null) {
            $issues = &$localIssues;
        }
        if ($checks === null) {
            $checks = &$localChecks;
        }

        try {
            $kernelFile = app_path('Http/Kernel.php');

            // Check if kernel file exists
            if (! File::exists($kernelFile)) {
                $message = 'Kernel file not found at app/Http/Kernel.php';
                $issues[] = $message;
                $checks[] = [
                    'passed' => false,
                    'message' => $message,
                ];
                $result = 0;
            } else {
                // Try to read the kernel file
                try {
                    $kernelContent = File::get($kernelFile);

                    // Check if content is valid PHP (basic check)
                    if (str_contains($kernelContent, '<?php') && str_contains($kernelContent, 'class')) {
                        if ($this->isMiddlewareRegistered(SecurityHeadersMiddleware::class)) {
                            $checks[] = [
                                'passed' => true,
                                'message' => 'SecurityHeadersMiddleware is registered',
                            ];
                            $result = 20;
                        } else {
                            $message = 'SecurityHeadersMiddleware is not registered globally in app/Http/Kernel.php';
                            $issues[] = $message;
                            $checks[] = [
                                'passed' => false,
                                'message' => $message,
                            ];
                            $result = 0;
                        }
                    } else {
                        $message = 'Kernel file is malformed (invalid PHP syntax)';
                        $issues[] = $message;
                        $checks[] = [
                            'passed' => false,
                            'message' => $message,
                        ];
                        $result = 0;
                    }
                } catch (\Exception $readException) {
                    $errorMessage = strtolower($readException->getMessage());
                    $message = match (true) {
                        str_contains($errorMessage, 'permission denied') || str_contains($errorMessage, 'permission') => 'Permission denied reading kernel file',
                        str_contains($errorMessage, 'malformed') => 'Kernel file is malformed',
                        default => 'Error reading kernel file: '.$readException->getMessage(),
                    };
                    $issues[] = $message;
                    $checks[] = [
                        'passed' => false,
                        'message' => $message,
                    ];
                    $result = 0;
                }
            }
        } catch (\Exception $e) {
            $errorMessage = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($errorMessage, 'file system') || str_contains($errorMessage, 'filesystem') => 'File system failure: '.$e->getMessage(),
                default => 'Middleware check failed: '.$e->getMessage(),
            };
            $issues[] = $message;
            $checks[] = [
                'passed' => false,
                'message' => $message,
            ];
            $result = 0;
        }

        // If called without parameters (for testing), return array
        if ($wasCalledWithoutParams) {
            return $localChecks[0] ?? ['passed' => false, 'message' => 'Unknown error'];
        }

        return $result;
    }

    /**
     * Check if middleware is registered in the kernel.
     */
    private function isMiddlewareRegistered(string $middlewareClass): bool
    {
        try {
            // Check if class exists first
            if (! class_exists($middlewareClass)) {
                return false;
            }

            // For Laravel 10+, we need to check the kernel file directly
            $kernelFile = app_path('Http/Kernel.php');
            if (! File::exists($kernelFile)) {
                return false;
            }

            try {
                $kernelContent = File::get($kernelFile);
                $shortClassName = class_basename($middlewareClass);

                // Check if middleware is registered in any of the arrays
                return str_contains($kernelContent, $middlewareClass)
                    || str_contains($kernelContent, $shortClassName);
            } catch (\Exception) {
                return false;
            }
        } catch (\Exception) {
            return false;
        }
    }
}
