<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [];

    /**
     * @var array<int, string>
     */
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    #[\Override]
    public function register(): void
    {
        $this->reportable(function (\Throwable $e): void {
            // Report to Sentry if available
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            if ($this->isSecurityException($e)) {
                $this->logSecurityException($e);
            }
        });

        $this->renderable(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->handleApiExceptions($e);
            }
        });
    }

    /**
     * Check if the exception is a security-related exception.
     */
    private function isSecurityException(\Throwable $e): bool
    {
        return $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof ValidationException
            || $e instanceof QueryException;
    }

    /**
     * Log security-related exceptions.
     */
    private function logSecurityException(\Throwable $e): void
    {
        logger()->warning('Security-related exception occurred', [
            'exception_type' => $e::class,
            'message' => $e->getMessage(),
            'ip' => request()->ip() ?? 'unknown',
            'user_agent' => request()->userAgent() ?? 'unknown',
            'url' => request()->url() ?? 'unknown',
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * Handle API exceptions.
     */
    private function handleApiExceptions(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422),
            $e instanceof NotFoundHttpException => response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'error_code' => 'NOT_FOUND',
            ], 404),
            $e instanceof AuthenticationException => response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error_code' => 'AUTH_REQUIRED',
                'timestamp' => now()->toIso8601String(),
                'request_id' => request()->header('X-Request-ID') ?? uniqid('req_', true),
                'security' => [
                    'attempt_logged' => true,
                    'ip_address' => request()->ip() ?? 'unknown',
                    'user_agent_logged' => !empty(request()->userAgent()),
                ],
            ], 401),
            $e instanceof QueryException => response()->json([
                'success' => false,
                'message' => 'A server-side database error occurred.',
                'error_code' => 'DATABASE_ERROR',
            ], 500),
            $e instanceof AuthorizationException => response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'error_code' => 'FORBIDDEN',
            ], 403),
            $e instanceof HttpException => $this->handleHttpException($e),
            default => response()->json([
                'success' => false,
                'message' => 'An unexpected server error occurred.',
                'error_code' => 'INTERNAL_SERVER_ERROR',
            ], 500),
        };
    }

    /**
     * Handle HTTP exceptions (like abort(401), abort(403), etc.).
     */
    private function handleHttpException(HttpException $e): JsonResponse
    {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage() ?: $this->getDefaultMessageForStatusCode($statusCode);
        $errorCode = $this->getErrorCodeForStatusCode($statusCode);

        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ];

        // Add additional fields for 401 errors
        if ($statusCode === 401) {
            $response['timestamp'] = now()->toIso8601String();
            $response['request_id'] = request()->header('X-Request-ID') ?? uniqid('req_', true);
            $response['security'] = [
                'attempt_logged' => true,
                'ip_address' => request()->ip() ?? 'unknown',
                'user_agent_logged' => !empty(request()->userAgent()),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Get default message for HTTP status code.
     */
    private function getDefaultMessageForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            500 => 'Internal Server Error',
            default => 'An error occurred',
        };
    }

    /**
     * Get error code for HTTP status code.
     */
    private function getErrorCodeForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'AUTH_REQUIRED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            422 => 'VALIDATION_ERROR',
            500 => 'INTERNAL_SERVER_ERROR',
            default => 'HTTP_ERROR',
        };
    }
}
