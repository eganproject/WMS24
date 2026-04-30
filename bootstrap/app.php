<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\RecalculatePoLineFulfillment;
use App\Console\Commands\TelegramSetWebhook;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        RecalculatePoLineFulfillment::class,
        TelegramSetWebhook::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'activity.log' => \App\Http\Middleware\LogUserActivity::class,
            'menu.permission' => \App\Http\Middleware\AuthorizeMenuPermission::class,
            'restrict.mobile' => \App\Http\Middleware\RestrictMobileAccess::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);

        $middleware->appendToGroup('web', 'restrict.mobile');
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
