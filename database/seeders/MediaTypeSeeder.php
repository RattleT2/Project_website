<?php

namespace Database\Seeders;

use App\Models\MediaType;
use Illuminate\Database\Seeder;

class MediaTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Online', 'Cetak', 'Elektronik', 'Televisi', 'Radio'];

        foreach ($types as $type) {
            MediaType::firstOrCreate(['name' => $type]);
        }
    }
}
