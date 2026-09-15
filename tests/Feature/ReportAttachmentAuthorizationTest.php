<?php

namespace Tests\Feature;

use App\Models\EvaluationQuestion;
use App\Models\MediaType;
use App\Models\Report;
use App\Models\ReportAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportAttachmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaporA;
    private User $pelaporB;
    private User $admin;
    private MediaType $mediaType;
    private EvaluationQuestion $question;
    private Report $reportA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaporA = User::factory()->create([
            'role' => 'pelapor',
            'status' => 'aktif',
        ]);

        $this->pelaporB = User::factory()->create([
            'role' => 'pelapor',
            'status' => 'aktif',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $this->mediaType = MediaType::create([
            'name' => 'Online',
            'code' => 'ONL',
        ]);

        $this->question = EvaluationQuestion::create([
            'media_type_id' => $this->mediaType->id,
            'category' => 'kelembagaan',
            'question_text' => 'Upload Akta Perusahaan',
            'weight' => 10,
            'is_mandatory' => true,
        ]);

        $this->reportA = Report::create([
            'user_id' => $this->pelaporA->id,
            'media_type_id' => $this->mediaType->id,
            'report_code' => 'ONL-001',
            'status' => 'pending',
            'total_score' => 0,
        ]);
    }

    public function test_pelapor_can_view_and_download_their_own_attachment(): void
    {
        $filePath = UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf')
            ->store('reports/questions/' . $this->question->id, 'public');

        ReportAnswer::create([
            'report_id' => $this->reportA->id,
            'question_id' => $this->question->id,
            'answer_value' => $filePath,
            'answer_type' => 'file',
            'score_earned' => 10,
        ]);

        $token = auth('api')->login($this->pelaporA);

        // View attachment
        $viewRes = $this->withHeader('Authorization', "Bearer $token")
            ->get("/api/reports/{$this->reportA->id}/attachments/{$this->question->id}/view");

        $viewRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // Download attachment
        $downloadRes = $this->withHeader('Authorization', "Bearer $token")
            ->get("/api/reports/{$this->reportA->id}/attachments/{$this->question->id}/download");

        $downloadRes->assertStatus(200)
            ->assertHeader('Content-Disposition');
    }

    public function test_pelapor_cannot_view_or_download_another_pelapors_attachment(): void
    {
        $filePath = UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf')
            ->store('reports/questions/' . $this->question->id, 'public');

        ReportAnswer::create([
            'report_id' => $this->reportA->id,
            'question_id' => $this->question->id,
            'answer_value' => $filePath,
            'answer_type' => 'file',
            'score_earned' => 10,
        ]);

        $tokenB = auth('api')->login($this->pelaporB);

        // Pelapor B attempts to view Report A's attachment
        $viewRes = $this->withHeader('Authorization', "Bearer $tokenB")
            ->get("/api/reports/{$this->reportA->id}/attachments/{$this->question->id}/view");

        $viewRes->assertStatus(404);

        // Pelapor B attempts to download Report A's attachment
        $downloadRes = $this->withHeader('Authorization', "Bearer $tokenB")
            ->get("/api/reports/{$this->reportA->id}/attachments/{$this->question->id}/download");

        $downloadRes->assertStatus(404);
    }

    public function test_admin_can_view_and_download_any_pelapors_attachment(): void
    {
        $filePath = UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf')
            ->store('reports/questions/' . $this->question->id, 'public');

        ReportAnswer::create([
            'report_id' => $this->reportA->id,
            'question_id' => $this->question->id,
            'answer_value' => $filePath,
            'answer_type' => 'file',
            'score_earned' => 10,
        ]);

        $adminToken = auth('api')->login($this->admin);

        // Via general reports attachment route
        $viewRes = $this->withHeader('Authorization', "Bearer $adminToken")
            ->get("/api/reports/{$this->reportA->id}/attachments/{$this->question->id}/view");

        $viewRes->assertStatus(200);

        // Via admin prefix route
        $adminViewRes = $this->withHeader('Authorization', "Bearer $adminToken")
            ->get("/api/admin/reports/{$this->reportA->id}/attachments/{$this->question->id}/view");

        $adminViewRes->assertStatus(200);
    }

    public function test_attachment_view_returns_404_if_physical_file_missing(): void
    {
        // Answer references a non-existent physical file
        ReportAnswer::create([
            'report_id' => $this->reportA->id,
            'question_id' => $this->question->id,
            'answer_value' => 'reports/questions/99/missing-file.pdf',
            'answer_type' => 'file',
            'score_earned' => 10,
        ]);

        $token = auth('api')->login($this->pelaporA);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get("/api/reports/{$this->reportA->id}/attachments/{$this->question->id}/view");

        $response->assertStatus(404);
    }

    public function test_admin_cannot_create_reports(): void
    {
        $adminToken = auth('api')->login($this->admin);

        $response = $this->withHeader('Authorization', "Bearer $adminToken")
            ->postJson('/api/reports', [
                'media_type_id' => $this->mediaType->id,
                'answers' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_pelapor_cannot_delete_standalone_file_belonging_to_another_user(): void
    {
        $file = UploadedFile::fake()->create('other.pdf', 50, 'application/pdf');
        $filePath = $file->store('reports/questions/' . $this->question->id, 'public');

        // File is attached to Pelapor A's report
        ReportAnswer::create([
            'report_id' => $this->reportA->id,
            'question_id' => $this->question->id,
            'answer_value' => $filePath,
            'answer_type' => 'file',
            'score_earned' => 10,
        ]);

        $tokenB = auth('api')->login($this->pelaporB);

        // Pelapor B tries to delete Pelapor A's file via standalone delete
        $response = $this->withHeader('Authorization', "Bearer $tokenB")
            ->postJson('/api/reports/delete-upload', [
                'file_path' => $filePath,
            ]);

        $response->assertStatus(403);
        Storage::disk('public')->assertExists($filePath);
    }

    public function test_standalone_delete_rejects_invalid_path(): void
    {
        $tokenA = auth('api')->login($this->pelaporA);

        $response = $this->withHeader('Authorization', "Bearer $tokenA")
            ->postJson('/api/reports/delete-upload', [
                'file_path' => '../../etc/passwd',
            ]);

        $response->assertStatus(422);
    }
}

