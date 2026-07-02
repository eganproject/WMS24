<?php

namespace App\Console\Commands;

use App\Support\LowStockService;
use App\Support\TelegramBotService;
use Illuminate\Console\Command;

class CreateLowStockSnapshot extends Command
{
    protected $signature = 'inventory:low-stock-snapshot
        {--warehouse=all : Warehouse id or all}
        {--notify : Send Telegram summary to allowed chats}';

    protected $description = 'Create a point-in-time snapshot of current low stock rows.';

    public function handle(LowStockService $lowStockService): int
    {
        $warehouse = $this->option('warehouse') ?: 'all';
        $snapshot = $lowStockService->createSnapshot($warehouse, 'scheduled');

        $message = "Snapshot low stock #{$snapshot->id} dibuat.\n"
            ."Waktu: ".$snapshot->snapshot_at?->format('Y-m-d H:i')."\n"
            ."Gudang: ".($snapshot->scope === 'all' ? 'Semua Gudang' : ($snapshot->warehouse_name ?? '-'))."\n"
            ."Total low: {$snapshot->total_low}\n"
            ."Out of stock: {$snapshot->total_out_of_stock}\n"
            ."Total gap: {$snapshot->total_gap}";

        $this->info($message);

        if ($this->option('notify')) {
            app(TelegramBotService::class)->notifyAllowedChats($message);
        }

        return self::SUCCESS;
    }
}
