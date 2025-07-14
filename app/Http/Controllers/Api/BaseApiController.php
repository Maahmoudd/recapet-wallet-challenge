<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Base API Controller with standardized response methods and error handling.
 */
abstract class BaseApiController extends Controller
{
    /**
     * Return a successful JSON response.
     *
     * @param mixed|null $data Response data
     * @param string $message Success message
     * @param integer $statusCode HTTP status code
     * @return JsonResponse
     */
    protected function successResponse(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toDateTimeString()
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a paginated JSON response.
     *
     * @param mixed $paginatedData Paginated data
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function paginatedResponse(mixed $paginatedData, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginatedData->items(),
            'pagination' => [
                'current_page' => $paginatedData->currentPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'last_page' => $paginatedData->lastPage(),
                'has_more_pages' => $paginatedData->hasMorePages(),
                'from' => $paginatedData->firstItem(),
                'to' => $paginatedData->lastItem()
            ],
            'timestamp' => now()->toDateTimeString()
        ], 200);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message Error message
     * @param integer $statusCode HTTP status code
     * @param array $errors Detailed errors array
     * @param mixed|null $debugData Debug data (only in debug mode)
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message,
        int $statusCode = 400,
        array $errors = [],
        mixed $debugData = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toDateTimeString()
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if (config('app.debug') && $debugData !== null) {
            $response['debug'] = $debugData;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Handle validation exceptions and return formatted response.
     *
     * @param ValidationException $exception Validation exception
     * @return JsonResponse
     */
    protected function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return $this->errorResponse(
            'Validation failed',
            422,
            $exception->errors()
        );
    }

    /**
     * Extract and validate pagination parameters from request.
     *
     * @param Request $request HTTP request
     * @param integer $defaultPerPage Default items per page
     * @param integer $maxPerPage Maximum items per page
     * @return array Pagination parameters
     */
    protected function getPaginationParams(Request $request, int $defaultPerPage = 25, int $maxPerPage = 100): array
    {
        $perPage = (int) $request->get('per_page', $defaultPerPage);
        $perPage = min($perPage, $maxPerPage);
        $perPage = max($perPage, 1);

        return [
            'per_page' => $perPage,
            'page' => (int) $request->get('page', 1)
        ];
    }

    /**
     * Extract filters from request parameters.
     *
     * @param Request $request HTTP request
     * @param array $allowedFilters Allowed filter keys
     * @return array Filtered parameters
     */
    protected function getFilters(Request $request, array $allowedFilters): array
    {
        $filters = [];

        foreach ($allowedFilters as $filter) {
            if ($request->has($filter) && !empty($request->get($filter))) {
                $filters[$filter] = $request->get($filter);
            }
        }

        return $filters;
    }

    /**
     * Log API activity for audit purposes.
     *
     * @param string $action Action performed
     * @param array $data Additional data
     * @return void
     */
    protected function logApiActivity(string $action, array $data = []): void
    {
        if (config('credit_system.logging.enabled', true)) {
            Log::channel(config('credit_system.logging.channel', 'stack'))
                ->info("API: {$action}", array_merge([
                    'user_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'endpoint' => request()->fullUrl(),
                    'method' => request()->method()
                ], $data));
        }
    }


    /**
     * Create a resource created response.
     *
     * @param mixed $data Created resource data
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function createdResponse($data, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Create a resource updated response.
     *
     * @param mixed $data Updated resource data
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function updatedResponse($data, string $message = 'Resource updated successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    /**
     * Create a resource deleted response.
     *
     * @param string $message Success message
     * @return JsonResponse
     */
    protected function deletedResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->successResponse(null, $message, 200);
    }

    /**
     * Create a not found response.
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Create an unauthorized response.
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized access'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Create a forbidden response.
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Access forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Format resource data for API response.
     *
     * @param mixed $resource Resource to format
     * @param string $type Resource type
     * @return array Formatted resource data
     */
    protected function formatResource(mixed $resource, string $type = 'resource'): array
    {
        if (!$resource) {
            return [];
        }

        $formatted = [
            'type' => $type,
            'id' => $resource->id ?? null,
            'attributes' => $resource->toArray()
        ];

        if (isset($resource->created_at)) {
            $formatted['meta'] = [
                'created_at' => $resource->created_at,
                'updated_at' => $resource->updated_at
            ];
        }

        return $formatted;
    }

    /**
     * Handle bulk operation results.
     *
     * @param array $results Bulk operation results
     * @param string $operation Operation name
     * @return JsonResponse
     */
    protected function bulkOperationResponse(array $results, string $operation): JsonResponse
    {
        $summary = $results['summary'] ?? [];
        $hasErrors = !empty($results['errors']);

        $message = sprintf(
            '%s completed: %d successful, %d failed',
            ucfirst($operation),
            $summary['successful'] ?? 0,
            $summary['failed'] ?? 0
        );

        $statusCode = $hasErrors ? 207 : 200; // 207 Multi-Status for partial success

        return response()->json([
            'success' => !$hasErrors || ($summary['successful'] ?? 0) > 0,
            'message' => $message,
            'data' => $results,
            'timestamp' => now()->toDateTimeString()
        ], $statusCode);
    }
}
