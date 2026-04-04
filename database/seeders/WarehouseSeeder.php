<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Warehouse::updateOrCreate(['name' => 'Gudang Seha']);
        Warehouse::updateOrCreate(['name' => 'Gudang Nanggewer']);
        Warehouse::updateOrCreate(['name' => 'Gudang Ciseeng']);
    }
}
