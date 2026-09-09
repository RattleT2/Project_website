<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AkunADMIN extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'kominfomtpadmin@gmail.com'],
            [
                'name' => 'Admin Kominfo',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'aktif',
            ]
        );
    }
}
