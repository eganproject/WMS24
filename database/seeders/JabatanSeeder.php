<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Jabatan;

class JabatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Jabatan::updateOrCreate(
            ['name' => 'Developer'],
            ['description' => 'Pengembang Sistem']
        );
        Jabatan::updateOrCreate(
            ['name' => 'Manager'],
            ['description' => 'Pengembang Sistem']
        );
        Jabatan::updateOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Pengembang Sistem']
        );
        Jabatan::updateOrCreate(
            ['name' => 'Staff'],
            ['description' => 'Pengembang Sistem']
        );
    }
}