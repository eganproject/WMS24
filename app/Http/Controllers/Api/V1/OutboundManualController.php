<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OutboundManualApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOutboundManualRequest;
use App\Http\Resources\Api\V1\OutboundManualResource;
use App\Services\OutboundManualApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class OutboundManualController extends Controller
{
    public function store(
        StoreOutboundManualRequest $request,
        OutboundManualApiService $service,
    ): JsonResponse {
        try {
            $result = $service->create($request->validated());
        } catch (OutboundManualApiException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                ],
            ], $exception->httpStatus);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Data outbound manual tidak valid.',
                    'details' => $exception->errors(),
                ],
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Outbound manual gagal disimpan.',
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['replayed']
                ? 'Request ini sudah pernah diproses; data outbound yang sama dikembalikan.'
                : 'Outbound manual berhasil dibuat dan masuk tahap QC.',
            'meta' => [
                'idempotent_replay' => $result['replayed'],
            ],
            'data' => (new OutboundManualResource($result['transaction']))->resolve($request),
        ], $result['replayed'] ? 200 : 201);
    }
}
