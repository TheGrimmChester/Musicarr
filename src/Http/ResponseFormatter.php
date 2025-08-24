<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResponseFormatter
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Create a success JSON response.
     */
    public function successResponse(array $data = [], ?string $message = null, int $statusCode = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message ?? $this->translator->trans('app.success'),
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Create an error JSON response.
     */
    public function errorResponse(string $message, array $errors = [], int $statusCode = 400): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Create a validation error response.
     */
    public function validationErrorResponse(array $errors, ?string $message = null): JsonResponse
    {
        return $this->errorResponse($message ?? $this->translator->trans('app.validation_failed'), $errors, 422);
    }

    /**
     * Create a not found response.
     */
    public function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, [], 404);
    }

    /**
     * Create a forbidden response.
     */
    public function forbiddenResponse(string $message = 'Access denied'): JsonResponse
    {
        return $this->errorResponse($message, [], 403);
    }

    /**
     * Create a server error response.
     */
    public function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($message, [], 500);
    }

    /**
     * Create a paginated response.
     */
    public function paginatedResponse(array $data, int $page, int $limit, int $total): JsonResponse
    {
        return $this->successResponse([
            'items' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
                'hasNext' => ($page * $limit) < $total,
                'hasPrevious' => $page > 1,
            ],
        ]);
    }

    /**
     * Create a list response with metadata.
     */
    public function listResponse(array $items, array $metadata = []): JsonResponse
    {
        return $this->successResponse([
            'items' => $items,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a single item response.
     */
    public function itemResponse(array $item, string $message = 'Item retrieved successfully'): JsonResponse
    {
        return $this->successResponse($item, $message);
    }

    /**
     * Create a created response.
     */
    public function createdResponse(array $data = [], string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Create an updated response.
     */
    public function updatedResponse(array $data = [], string $message = 'Resource updated successfully'): JsonResponse
    {
        return $this->successResponse($data, $message);
    }

    /**
     * Create a deleted response.
     */
    public function deletedResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->successResponse([], $message);
    }

    /**
     * Create a bulk operation response.
     */
    public function bulkOperationResponse(int $successCount, int $errorCount, array $errors = [], string $message = 'Bulk operation completed'): JsonResponse
    {
        return $this->successResponse([
            'successCount' => $successCount,
            'errorCount' => $errorCount,
            'errors' => $errors,
        ], $message);
    }

    /**
     * Create a progress response for long-running operations.
     */
    public function progressResponse(int $progress, string $status, array $data = []): JsonResponse
    {
        return $this->successResponse(array_merge($data, [
            'progress' => $progress,
            'status' => $status,
        ]));
    }
}
