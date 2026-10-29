<?php

namespace Tests\Feature\Outbound;

use App\Http\Middleware\ShortDbLockWait;
use Illuminate\Http\Request;
use Tests\TestCase;

class QcShortLockWaitTest extends TestCase
{
    public function test_lock_timeout_error_becomes_clear_retry_message(): void
    {
        $response = (new ShortDbLockWait())->handle(Request::create('/mobile/qc/scan-sku', 'POST'), fn () => response()->json([
            'message' => 'Gagal memproses scan SKU.',
            'error' => 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction',
        ], 500));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('scan ulang', $response->getData(true)['message']);
    }

    public function test_other_responses_are_untouched(): void
    {
        $middleware = new ShortDbLockWait();
        $ok = $middleware->handle(Request::create('/x', 'POST'), fn () => response()->json(['message' => 'SKU berhasil discan.']));
        $other = $middleware->handle(Request::create('/x', 'POST'), fn () => response()->json(['message' => 'Gagal', 'error' => 'boom'], 500));

        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame(500, $other->getStatusCode());
        $this->assertSame('Gagal', $other->getData(true)['message']);
    }
}
