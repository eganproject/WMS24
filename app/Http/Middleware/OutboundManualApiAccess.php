<?php

namespace App\Http\Middleware;

use App\Models\StockApiAllowedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OutboundManualApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('outbound_manual_api.enabled')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'API_DISABLED',
                    'message' => 'API Outbound Manual tidak aktif.',
                ],
            ], 503);
        }

        $token = (string) config('outbound_manual_api.token');
        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Token API Outbound Manual tidak valid.',
                ],
            ], 401);
        }

        $allowed = StockApiAllowedIp::query()
            ->where('is_active', true)
            ->where('ip_address', $request->ip())
            ->exists();

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IP_NOT_ALLOWED',
                    'message' => 'IP sumber tidak diizinkan.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
