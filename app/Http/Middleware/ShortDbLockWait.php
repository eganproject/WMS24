<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * For short scan requests (QC): do not let one request wait 50 seconds on a row
 * lock held by another operator. Fail fast with a clear message so the scanner
 * screen never looks frozen.
 */
class ShortDbLockWait
{
    public function handle(Request $request, Closure $next, string $seconds = '8'): Response
    {
        $restore = $this->shorten(max(1, (int) $seconds));

        try {
            $response = $next($request);
        } finally {
            $restore();
        }

        return $this->friendlyLockError($response);
    }

    private function shorten(int $seconds): callable
    {
        try {
            if (DB::connection()->getDriverName() !== 'mysql') {
                return static fn () => null;
            }
            $previous = (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS t')->t;
            DB::statement('SET SESSION innodb_lock_wait_timeout = '.$seconds);
        } catch (\Throwable) {
            return static fn () => null;
        }

        return static function () use ($previous) {
            try {
                DB::statement('SET SESSION innodb_lock_wait_timeout = '.max(1, $previous));
            } catch (\Throwable) {
                // ignore
            }
        };
    }

    private function friendlyLockError(Response $response): Response
    {
        if (!$response instanceof JsonResponse || $response->getStatusCode() < 500) {
            return $response;
        }

        $data = $response->getData(true);
        $error = (string) ($data['error'] ?? '');
        if (!str_contains($error, 'Lock wait timeout') && !str_contains($error, 'Deadlock found')) {
            return $response;
        }

        $data['message'] = 'Data sedang dipakai proses lain (QC atau scan out lain). Tidak ada yang tersimpan, silakan scan ulang.';
        $response->setData($data);
        $response->setStatusCode(409);

        return $response;
    }
}
