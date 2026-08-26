<?php

namespace Tests\Feature;

use App\Models\MediaType;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_recap_excel(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $mediaType = MediaType::create([
            'name' => 'Online',
            'code' => 'ONL',
        ]);

        Report::create([
            'user_id' => $admin->id,
            'media_type_id' => $mediaType->id,
            'report_code' => 'ONL-001',
            'status' => 'disetujui',
            'total_score' => 75,
            'submitted_at' => now(),
        ]);

        $token = auth('api')->login($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/admin/export-excel');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_export_recap_excel_filtered_by_media_type(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $onlineMedia = MediaType::create([
            'name' => 'Online',
            'code' => 'ONL',
        ]);

        $cetakMedia = MediaType::create([
            'name' => 'Cetak',
            'code' => 'CTK',
        ]);

        Report::create([
            'user_id' => $admin->id,
            'media_type_id' => $onlineMedia->id,
            'report_code' => 'ONL-001',
            'status' => 'disetujui',
            'total_score' => 80,
            'submitted_at' => now(),
        ]);

        Report::create([
            'user_id' => $admin->id,
            'media_type_id' => $cetakMedia->id,
            'report_code' => 'CTK-001',
            'status' => 'disetujui',
            'total_score' => 60,
            'submitted_at' => now(),
        ]);

        $token = auth('api')->login($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/admin/export-excel?media_type_id={$onlineMedia->id}");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_pelapor_cannot_export_excel(): void
    {
        $pelapor = User::factory()->create([
            'role' => 'pelapor',
            'status' => 'aktif',
        ]);

        $token = auth('api')->login($pelapor);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/admin/export-excel');

        $response->assertStatus(403);
    }
}