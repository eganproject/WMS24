<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultCode = config('inventory.default_warehouse_code', 'GUDANG_BESAR');
        $displayCode = config('inventory.display_warehouse_code', 'GUDANG_DISPLAY');
        $now = now();

        DB::table('warehouses')->updateOrInsert(
            ['code' => $defaultCode],
            ['name' => 'Gudang Besar', 'type' => 'main', 'updated_at' => $now, 'created_at' => $now]
        );

        DB::table('warehouses')->updateOrInsert(
            ['code' => $displayCode],
            ['name' => 'Gudang Display', 'type' => 'display', 'updated_at' => $now, 'created_at' => $now]
        );
    }
}
