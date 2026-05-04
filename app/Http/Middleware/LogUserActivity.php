<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Support\ActivityLogDescription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    private static ?bool $hasTable = null;

    public function handle(Request $request, Closure $next): Response
    {
        $userBefore = $request->user();
        $response = $next($request);

        if (!$this->shouldLog($request)) {
            return $response;
        }

        $user = $request->user() ?? $userBefore;
        if (!$user) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        $method = strtoupper($request->method());
        $payload = $this->sanitizePayload($request);
        $userId = $user->newQuery()->whereKey($user->getKey())->exists()
            ? $user->id
            : null;

        ActivityLog::create([
            'user_id' => $userId,
            'action' => ActivityLogDescription::describe($request, $response, $payload),
            'route_name' => $routeName,
            'method' => $method,
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => ActivityLogDescription::payload($request, $response, $payload),
        ]);

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        $method = strtoupper($request->method());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        if (self::$hasTable === null) {
            self::$hasTable = Schema::hasTable('activity_logs');
        }

        return self::$hasTable;
    }

    private function sanitizePayload(Request $request): array
    {
        $payload = $request->except([
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'new_password_confirmation',
        ]);

        return $this->normalizeValue($payload);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
                'mime' => $value->getClientMimeType(),
            ];
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }
            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return get_class($value);
        }

        return $value;
    }
}
