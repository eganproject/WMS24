<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();

        $administratorUsers = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@gmail.com',
                'password' => 'Password24!2',
            ],
            [
                'name' => 'Doni',
                'email' => 'doni24@gmail.com',
                'password' => 'DoniPassword!2',
            ],
        ];

        foreach ($administratorUsers as $administratorUser) {
            DB::table('users')->updateOrInsert(
                ['email' => $administratorUser['email']],
                [
                    'name' => $administratorUser['name'],
                    'password' => Hash::make($administratorUser['password']),
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $user = DB::table('users')->where('email', $administratorUser['email'])->first();

            if ($user && $adminRole) {
                DB::table('role_user')->updateOrInsert(
                    ['role_id' => $adminRole->id, 'user_id' => $user->id],
                    []
                );
            }
        }

        $users = [
            ['name' => 'Budi Santoso', 'email' => 'budi.santoso@example.com'],
            ['name' => 'Siti Rahmawati', 'email' => 'siti.rahmawati@example.com'],
            ['name' => 'Andi Pratama', 'email' => 'andi.pratama@example.com'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi.lestari@example.com'],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('Password!2'),
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
