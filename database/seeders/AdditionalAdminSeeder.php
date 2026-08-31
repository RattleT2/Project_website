<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdditionalAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'email' => 'admin2@kominfo.go.id',
                'name' => 'Admin Media 1',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'aktif',
            ],
            [
                'email' => 'admin3@kominfo.go.id',
                'name' => 'Admin Media 2',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'aktif',
            ],
        ];

        foreach ($admins as $adminData) {
            User::firstOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );
        }
    }
}

