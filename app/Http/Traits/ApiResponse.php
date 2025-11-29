<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Success response.
     *
     * @param mixed|null $data
     */
    protected function success($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Error response.
     *
     * @param mixed|null $errors
     */
    protected function error(string $message = 'Error', $errors = null, int $status = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $this->getErrorCode($status),
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Created response.
     *
     * @param mixed|null $data
     */
    protected function created($data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * No content response.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Not found response.
     */
    protected function notFound(string $message = 'Resource not found', ?array $additionalData = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $this->getErrorCode(404),
        ];

        if ($additionalData) {
            $response = array_merge($response, $additionalData);
        }

        return response()->json($response, 404);
    }

    /**
     * Unauthorized response.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, null, 401);
    }

    /**
     * Forbidden response.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, null, 403);
    }

    /**
     * Validation error response.
     *
     * @param mixed $errors
     */
    protected function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $this->getErrorCode(422),
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, 422);
    }

    /**
     * Server error response.
     *
     * @param mixed|null $errors
     */
    protected function serverError(string $message = 'Internal server error', $errors = null): JsonResponse
    {
        return $this->error($message, $errors, 500);
    }

    /**
     * Paginated response.
     */
    protected function paginated(LengthAwarePaginator $paginator, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'first_page_url' => $paginator->url(1),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'links' => $paginator->linkCollection()->toArray(),
                'next_page_url' => $paginator->nextPageUrl(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Get error code based on HTTP status.
     */
    private function getErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'AUTH_REQUIRED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'INTERNAL_SERVER_ERROR',
            default => 'ERROR',
        };
    }
}
