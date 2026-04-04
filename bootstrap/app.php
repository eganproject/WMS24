<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);

        // Redirect authenticated users who hit guest routes (e.g., "/")
        // to the admin dashboard to avoid redirect loops.
        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(
            fn () => route('admin.dashboard')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
