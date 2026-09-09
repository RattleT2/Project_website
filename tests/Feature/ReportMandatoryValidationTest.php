<?php

namespace Tests\Feature;

use App\Models\EvaluationQuestion;
use App\Models\MediaType;
use App\Models\Report;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportMandatoryValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $pelapor;
    protected User $admin;
    protected MediaType $mediaType;
    protected EvaluationQuestion $question1;
    protected EvaluationQuestion $questionWa;
    protected EvaluationQuestion $question2;
    protected EvaluationQuestion $questionOptional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelapor = User::factory()->create([
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

        $this->question1 = EvaluationQuestion::create([
            'category' => 'identitas',
            'question_text' => 'Nama Media',
            'weight' => 0,
            'is_mandatory' => true,
            'media_type_id' => null,
        ]);

        $this->questionWa = EvaluationQuestion::create([
            'category' => 'identitas',
            'question_text' => 'Nomor WhatsApp / Kontak yang Dapat Dihubungi',
            'weight' => 0,
            'is_mandatory' => true,
            'media_type_id' => null,
        ]);

        $this->question2 = EvaluationQuestion::create([
            'category' => 'legalitas',
            'question_text' => 'Upload akta pendirian perusahaan (PDF, maks 5MB)',
            'weight' => 0,
            'is_mandatory' => true,
            'media_type_id' => null,
        ]);

        $this->questionOptional = EvaluationQuestion::create([
            'category' => 'verifikasi',
            'question_text' => 'Upload bukti dukung verifikasi (PDF, maks 5MB)',
            'weight' => 0,
            'is_mandatory' => false,
            'media_type_id' => null,
        ]);
    }

    public function test_submitting_report_without_mandatory_questions_fails_with_422(): void
    {
        $token = auth('api')->login($this->pelapor);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/reports', [
                'media_type_id' => $this->mediaType->id,
                'submit' => true,
                'answers' => [
                    [
                        'question_id' => $this->question1->id,
                        'answer_value' => 'Media Banjar Online',
                        'answer_type' => 'text',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_draft_created_without_submit_is_not_visible_in_admin(): void
    {
        $pelaporToken = auth('api')->login($this->pelapor);

        $createResponse = $this->withHeader('Authorization', "Bearer {$pelaporToken}")
            ->postJson('/api/reports', [
                'media_type_id' => $this->mediaType->id,
                'submit' => false,
                'answers' => [
                    [
                        'question_id' => $this->question1->id,
                        'answer_value' => 'Media Banjar Online Draft',
                        'answer_type' => 'text',
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $this->assertNull($createResponse->json('report.submitted_at'));

        $pelaporListResponse = $this->withHeader('Authorization', "Bearer {$pelaporToken}")
            ->getJson('/api/reports');
        $pelaporListResponse->assertStatus(200)
            ->assertJsonCount(1);

        $adminToken = auth('api')->login($this->admin);
        $adminListResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/admin/reports');
        $adminListResponse->assertStatus(200)
            ->assertJsonPath('total', 0);

        $adminDashResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/admin/dashboard');
        $adminDashResponse->assertStatus(200)
            ->assertJsonPath('total_reports', 0);
    }

    public function test_submitting_with_all_mandatory_questions_succeeds_and_appears_in_admin(): void
    {
        $pelaporToken = auth('api')->login($this->pelapor);

        $response = $this->withHeader('Authorization', "Bearer {$pelaporToken}")
            ->postJson('/api/reports', [
                'media_type_id' => $this->mediaType->id,
                'submit' => true,
                'answers' => [
                    [
                        'question_id' => $this->question1->id,
                        'answer_value' => 'Media Banjar Online',
                        'answer_type' => 'text',
                    ],
                    [
                        'question_id' => $this->questionWa->id,
                        'answer_value' => '081234567890',
                        'answer_type' => 'text',
                    ],
                    [
                        'question_id' => $this->question2->id,
                        'answer_value' => 'reports/questions/2/akta.pdf',
                        'answer_type' => 'file',
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('report.submitted_at'));

        $adminToken = auth('api')->login($this->admin);
        $adminListResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/admin/reports');
        $adminListResponse->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.whatsapp_number', '081234567890');
    }

    public function test_standalone_file_upload_returns_file_path(): void
    {
        Storage::fake('public');

        $pelaporToken = auth('api')->login($this->pelapor);
        $file = UploadedFile::fake()->create('akta_perusahaan.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$pelaporToken}")
            ->postJson("/api/reports/upload/{$this->question2->id}", [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'file_path', 'url']);

        $filePath = $response->json('file_path');
        Storage::disk('public')->assertExists($filePath);
    }
}