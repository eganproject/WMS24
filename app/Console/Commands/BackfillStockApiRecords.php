<?php
namespace App\Console\Commands;
use App\Models\Item;use App\Support\StockApiSyncService;use Illuminate\Console\Command;
class BackfillStockApiRecords extends Command {protected $signature='stock-api:backfill';protected $description='Mengisi proyeksi API stok tanpa mengubah saldo';public function handle():int{Item::orderBy('id')->chunkById(200,fn($items)=>$items->each(fn($item)=>StockApiSyncService::syncItem($item->id)));$this->info('Backfill API stok selesai.');return self::SUCCESS;}}
