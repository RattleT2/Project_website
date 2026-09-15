<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD', 'admin123');

        User::updateOrCreate(
            ['email' => 'kominfomtpadmin@gmail.com'],
            [
                'name' => 'Admin Kominfo',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'status' => 'aktif',
            ]
        );
    }
}
