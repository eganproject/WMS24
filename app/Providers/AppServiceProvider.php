<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Item;
use App\Support\StockApiSyncService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('stock-api',fn(Request $r)=>Limit::perMinute(config('stock_api.rate_limit_per_minute'))->by(hash('sha256',(string)$r->bearerToken().'|'.$r->ip())));
        Item::saved(fn(Item $item)=>StockApiSyncService::syncItem($item->id));
        Item::deleting(fn(Item $item)=>StockApiSyncService::markDeleted($item));
    }
}
