<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['ciseeng', 'ciapus'] as $name) {
            Supplier::firstOrCreate([
                'name' => $name,
            ]);
        }
    }
}
