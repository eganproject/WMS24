<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Jabatan; // Import Jabatan model
use App\Models\Warehouse;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $developerJabatan = Jabatan::where('name', 'Developer')->first();
        $adminJabatan = Jabatan::where('name', 'Admin')->first();

        // Get warehouses
        $gudangSeha = Warehouse::where('name', 'Gudang Seha')->first();
        $gudangNanggewer = Warehouse::where('name', 'Gudang Nanggewer')->first();

        User::updateOrCreate(
            ['email' => 'superadmin@developer.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Password!2'),
                'jabatan_id' => $developerJabatan->id ?? null,
            ]
        );

        if ($gudangSeha) {
            User::updateOrCreate(
                ['email' => 'mawar@admin.com'],
                [
                    'name' => 'mawar',
                    'password' => Hash::make('Password!2'),
                    'jabatan_id' => $adminJabatan->id ?? null,
                    'warehouse_id' => $gudangSeha->id
                ]
            );
        }

        if ($gudangNanggewer) {
            User::updateOrCreate(
                ['email' => 'melati@admin.com'],
                [
                    'name' => 'melati',
                    'password' => Hash::make('Password!2'),
                    'jabatan_id' => $adminJabatan->id ?? null,
                    'warehouse_id' => $gudangNanggewer->id
                ]
            );
        }
    }
}
