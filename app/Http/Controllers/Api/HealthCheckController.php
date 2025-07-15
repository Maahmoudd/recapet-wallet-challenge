<?php

namespace App\Http\Controllers\Api;

use App\Actions\System\HealthCheckAction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckController extends BaseApiController
{
    public function healthz(HealthCheckAction $action): JsonResponse
    {
        $result = $action->execute();

        $httpStatus = match($result['status']) {
            'healthy', 'degraded' => Response::HTTP_OK,
            'unhealthy' => Response::HTTP_SERVICE_UNAVAILABLE,
            default => Response::HTTP_INTERNAL_SERVER_ERROR
        };

        return response()->json($result, $httpStatus);
    }

    public function readiness(HealthCheckAction $action): JsonResponse
    {
        $result = $action->execute();

        $isReady = $result['status'] === 'healthy' &&
            $result['services']['database']['status'] === 'healthy' &&
            $result['services']['wallet_service']['status'] === 'healthy' &&
            $result['services']['transaction_service']['status'] !== 'unhealthy';

        $httpStatus = $isReady ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json([
            'ready' => $isReady,
            'status' => $result['status'],
            'timestamp' => $result['timestamp'],
            'critical_services' => [
                'database' => $result['services']['database']['status'],
                'wallet_service' => $result['services']['wallet_service']['status'],
                'transaction_service' => $result['services']['transaction_service']['status'],
            ]
        ], $httpStatus);
    }

    public function liveness(): JsonResponse
    {
        return response()->json([
            'alive' => true,
            'timestamp' => now()->toISOString(),
            'message' => 'Application is running'
        ], Response::HTTP_OK);
    }
}
