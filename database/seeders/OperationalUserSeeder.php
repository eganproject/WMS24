<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OperationalUserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultDivisiId = DB::table('divisis')->where('id', 1)->value('id');

        $users = [
            [
                'name' => 'Picker Operasional',
                'email' => 'picker@wms24.test',
                'role_slug' => 'picker',
            ],
            [
                'name' => 'QC Operasional',
                'email' => 'qc@wms24.test',
                'role_slug' => 'qc',
            ],
            [
                'name' => 'Packer Operasional',
                'email' => 'packer@wms24.test',
                'role_slug' => 'packer',
            ],
            [
                'name' => 'Scan Out Operasional',
                'email' => 'scanout@wms24.test',
                'role_slug' => 'admin-scan',
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('123456'),
                    'divisi_id' => $defaultDivisiId,
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $userId = DB::table('users')->where('email', $user['email'])->value('id');
            $roleId = DB::table('roles')->where('slug', $user['role_slug'])->value('id');

            if ($userId && $roleId) {
                DB::table('role_user')->updateOrInsert(
                    ['role_id' => $roleId, 'user_id' => $userId],
                    []
                );
            }
        }
    }
}
