<?php

namespace Database\Seeders;

use App\Models\MediaType;
use Illuminate\Database\Seeder;

class MediaTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Online', 'code' => 'ON'],
            ['name' => 'Cetak', 'code' => 'CT'],
            ['name' => 'Elektronik', 'code' => 'EL'],
            ['name' => 'Televisi', 'code' => 'TV'],
            ['name' => 'Radio', 'code' => 'RD'],
        ];

        foreach ($types as $type) {
            $mediaType = MediaType::firstOrCreate(['name' => $type['name']]);
            if (!$mediaType->code) {
                $mediaType->update(['code' => $type['code']]);
            }
        }
    }
}
