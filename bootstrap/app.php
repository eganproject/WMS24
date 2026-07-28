<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\PurgeAttendanceWebhookLogs;
use App\Console\Commands\RecalculatePoLineFulfillment;
use App\Console\Commands\ReconcileCanceledTransferMutations;
use App\Console\Commands\TelegramSetWebhook;
use App\Console\Commands\BackfillStockApiRecords;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        PurgeAttendanceWebhookLogs::class,
        RecalculatePoLineFulfillment::class,
        ReconcileCanceledTransferMutations::class,
        TelegramSetWebhook::class,
        BackfillStockApiRecords::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'activity.log' => \App\Http\Middleware\LogUserActivity::class,
            'user.active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'menu.permission' => \App\Http\Middleware\AuthorizeMenuPermission::class,
            'restrict.mobile' => \App\Http\Middleware\RestrictMobileAccess::class,
            'stock.api.access' => \App\Http\Middleware\StockApiAccess::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);

        $middleware->appendToGroup('web', 'restrict.mobile');
        $middleware->appendToGroup('web', 'user.active');
        $middleware->appendToGroup('web', 'activity.log');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $_, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sesi habis. Silakan login kembali.'], 419);
            }

            return redirect()->route('login')->withErrors(['session' => 'Sesi habis. Silakan login kembali.']);
        });
    })->create();
