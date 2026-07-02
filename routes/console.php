<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:webhook-logs:purge')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command('inventory:low-stock-snapshot --warehouse=all')
    ->dailyAt('07:00')
    ->withoutOverlapping();
