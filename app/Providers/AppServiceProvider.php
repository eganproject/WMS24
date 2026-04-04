<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use App\Support\MenuPermissionResolver;
use App\View\Composers\MenuComposer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MenuPermissionResolver::class, function () {
            return new MenuPermissionResolver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.partials._header', MenuComposer::class);

        Blade::if('menuCan', function (string $ability, ?string $routeName = null) {
            return app(MenuPermissionResolver::class)->userCan($ability, $routeName);
        });
    }
}