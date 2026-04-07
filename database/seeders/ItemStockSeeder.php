<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemStock;
use App\Support\WarehouseService;
use Illuminate\Database\Seeder;

class ItemStockSeeder extends Seeder
{
    public function run(): void
    {
        Item::select('id')->chunkById(200, function ($items) {
            $warehouseId = WarehouseService::defaultWarehouseId();
            foreach ($items as $item) {
                ItemStock::firstOrCreate(
                    ['item_id' => $item->id, 'warehouse_id' => $warehouseId],
                    ['stock' => 0]
                );
            }
        });
    }
}
