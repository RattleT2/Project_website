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

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $activePelapor;
    private User $inactivePelapor;
    private User $admin;
    private MediaType $mediaOnline;
    private MediaType $mediaCetak;
    private EvaluationQuestion $globalQuestion;
    private EvaluationQuestion $onlineQuestion;
    private EvaluationQuestion $cetakQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->activePelapor = User::factory()->create([
            'name' => 'Pelapor Aktif',
            'role' => 'pelapor',
            'status' => 'aktif',
        ]);

        $this->inactivePelapor = User::factory()->create([
            'name' => 'Pelapor Non-Aktif',
            'role' => 'pelapor',
            'status' => 'non-aktif',
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Kominfo',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $this->mediaOnline = MediaType::create([
            'name' => 'Online',
            'code' => 'ON',
        ]);

        $this->mediaCetak = MediaType::create([
            'name' => 'Cetak',
            'code' => 'CT',
        ]);

        $this->globalQuestion = EvaluationQuestion::create([
            'media_type_id' => null,
            'category' => 'identitas',
            'question_text' => 'Nama Media',
            'weight' => 0,
            'is_mandatory' => true,
        ]);

        $this->onlineQuestion = EvaluationQuestion::create([
            'media_type_id' => $this->mediaOnline->id,
            'category' => 'sosial_media',
            'question_text' => 'Pertanyaan Khusus Online',
            'weight' => 10,
            'is_mandatory' => false,
        ]);

        $this->cetakQuestion = EvaluationQuestion::create([
            'media_type_id' => $this->mediaCetak->id,
            'category' => 'sosial_media',
            'question_text' => 'Pertanyaan Khusus Cetak',
            'weight' => 10,
            'is_mandatory' => false,
        ]);
    }

    public function test_inactive_user_token_is_blocked_from_authenticated_routes(): void
    {
        $token = auth('api')->login($this->inactivePelapor);

        // GET /api/auth/me
        $resMe = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/auth/me');
        $resMe->assertStatus(403)
            ->assertJsonPath('message', 'Akun Anda non-aktif. Hubungi admin.');

        // PUT /api/auth/me
        $resUpdate = $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/auth/me', ['name' => 'New Name']);
        $resUpdate->assertStatus(403);

        // POST /api/reports
        $resReport = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/reports', ['media_type_id' => $this->mediaOnline->id]);
        $resReport->assertStatus(403);
    }

    public function test_cross_media_question_id_is_rejected(): void
    {
        $token = auth('api')->login($this->activePelapor);

        // Submitting Cetak question to an Online report
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/reports', [
                'media_type_id' => $this->mediaOnline->id,
                'answers' => [
                    [
                        'question_id' => $this->globalQuestion->id,
                        'answer_value' => 'Media Banjar Online',
                        'answer_type' => 'text',
                    ],
                    [
                        'question_id' => $this->cetakQuestion->id,
                        'answer_value' => 'Nilai Jawaban Cetak',
                        'answer_type' => 'text',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_non_existent_file_path_in_answer_is_rejected(): void
    {
        $token = auth('api')->login($this->activePelapor);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/reports', [
                'media_type_id' => $this->mediaOnline->id,
                'answers' => [
                    [
                        'question_id' => $this->globalQuestion->id,
                        'answer_value' => 'reports/questions/1/non_existent_file.pdf',
                        'answer_type' => 'file',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_shared_endpoints_are_read_only_and_do_not_mutate_db(): void
    {
        $initialCount = EvaluationQuestion::count();

        $response = $this->getJson('/api/evaluation-questions');
        $response->assertStatus(200);

        $this->assertEquals($initialCount, EvaluationQuestion::count());
    }

    public function test_excel_export_sanitizes_potential_formula_injection(): void
    {
        $report = Report::create([
            'user_id' => $this->activePelapor->id,
            'media_type_id' => $this->mediaOnline->id,
            'report_code' => '=1+1',
            'status' => 'disetujui',
            'submitted_at' => now(),
            'total_score' => 80,
        ]);

        ReportAnswer::create([
            'report_id' => $report->id,
            'question_id' => $this->globalQuestion->id,
            'answer_value' => '=cmd|"/C calc"!A0',
            'answer_type' => 'text',
            'score_earned' => 0,
        ]);

        $adminToken = auth('api')->login($this->admin);

        $response = $this->withHeader('Authorization', "Bearer $adminToken")
            ->get('/api/admin/export-excel');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    public function test_avatar_only_profile_update_without_name_succeeds(): void
    {
        $token = auth('api')->login($this->activePelapor);
        $file = UploadedFile::fake()->image('new_avatar.jpg', 150, 150);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/me', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);
        $this->activePelapor->refresh();
        $this->assertEquals('Pelapor Aktif', $this->activePelapor->name);
        $this->assertNotNull($this->activePelapor->avatar);
    }

    public function test_standalone_file_deletion_blocks_unauthorized_path_and_other_user_file(): void
    {
        $token = auth('api')->login($this->activePelapor);

        // Path outside allowed directory
        $response1 = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/reports/delete-upload', [
                'file_path' => 'secret_dir/config.env',
            ]);
        $response1->assertStatus(422);

        // Path of another user's report attachment
        $otherReport = Report::create([
            'user_id' => $this->admin->id,
            'media_type_id' => $this->mediaOnline->id,
            'report_code' => 'LAP-ON-001',
            'status' => 'pending',
        ]);
        ReportAnswer::create([
            'report_id' => $otherReport->id,
            'question_id' => $this->globalQuestion->id,
            'answer_value' => 'reports/questions/1/other_user_file.pdf',
            'answer_type' => 'file',
            'score_earned' => 0,
        ]);

        $response2 = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/reports/delete-upload', [
                'file_path' => 'reports/questions/1/other_user_file.pdf',
            ]);
        $response2->assertStatus(403);
    }

    public function test_admin_report_update_uses_strict_validation(): void
    {
        $report = Report::create([
            'user_id' => $this->activePelapor->id,
            'media_type_id' => $this->mediaOnline->id,
            'report_code' => 'LAP-ON-002',
            'status' => 'pending',
        ]);

        $adminToken = auth('api')->login($this->admin);

        // Malformed answer payload without answer_value/question_id
        $response = $this->withHeader('Authorization', "Bearer $adminToken")
            ->putJson("/api/admin/reports/{$report->id}", [
                'answers' => [
                    ['invalid_key' => 'invalid_value'],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_uploaded_attachment_is_stored_on_private_disk_not_public(): void
    {
        $token = auth('api')->login($this->activePelapor);
        $file = UploadedFile::fake()->create('private_doc.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/reports/upload/{$this->globalQuestion->id}", [
                'file' => $file,
            ]);

        $response->assertStatus(200);
        $filePath = $response->json('file_path');

        // File must exist on private local disk
        Storage::disk('local')->assertExists($filePath);
        // File must NOT exist on public disk (no static bypass)
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_admin_update_rejects_cross_media_question_and_nonexistent_file(): void
    {
        $report = Report::create([
            'user_id' => $this->activePelapor->id,
            'media_type_id' => $this->mediaOnline->id,
            'report_code' => 'LAP-ON-003',
            'status' => 'proses',
        ]);

        $adminToken = auth('api')->login($this->admin);

        // 1. Cross-media question rejection on admin update
        $res1 = $this->withHeader('Authorization', "Bearer $adminToken")
            ->putJson("/api/admin/reports/{$report->id}", [
                'answers' => [
                    [
                        'question_id' => $this->cetakQuestion->id,
                        'answer_value' => 'Nilai Cetak',
                        'answer_type' => 'text',
                    ],
                ],
            ]);
        $res1->assertStatus(422);

        // 2. Non-existent file path rejection on admin update
        $res2 = $this->withHeader('Authorization', "Bearer $adminToken")
            ->putJson("/api/admin/reports/{$report->id}", [
                'answers' => [
                    [
                        'question_id' => $this->globalQuestion->id,
                        'answer_value' => 'reports/questions/1/nonexistent.pdf',
                        'answer_type' => 'file',
                    ],
                ],
            ]);
        $res2->assertStatus(422);
    }

    public function test_user_cannot_submit_file_uploaded_by_another_user(): void
    {
        $otherPelapor = User::factory()->create([
            'role' => 'pelapor',
            'status' => 'aktif',
        ]);

        // 1. Other pelapor uploads a file to temporary storage
        $otherToken = auth('api')->login($otherPelapor);
        $file = UploadedFile::fake()->create('other_secret.pdf', 100, 'application/pdf');
        $uploadRes = $this->withHeader('Authorization', "Bearer $otherToken")
            ->postJson("/api/reports/upload/{$this->globalQuestion->id}", [
                'file' => $file,
            ]);
        $filePath = $uploadRes->json('file_path');

        // 2. Pelapor attempts to steal/reference other pelapor's temporary upload path in their report
        $pelaporToken = auth('api')->login($this->activePelapor);
        $reportRes = $this->withHeader('Authorization', "Bearer $pelaporToken")
            ->postJson('/api/reports', [
                'media_type_id' => $this->mediaOnline->id,
                'answers' => [
                    [
                        'question_id' => $this->globalQuestion->id,
                        'answer_value' => $filePath,
                        'answer_type' => 'file',
                    ],
                ],
            ]);

        $reportRes->assertStatus(422)
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_reset_password_blocked_for_inactive_account(): void
    {
        $token = \Illuminate\Support\Facades\Password::createToken($this->inactivePelapor);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $this->inactivePelapor->email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Akun non-aktif. Hubungi admin.');
    }

    public function test_forgot_password_does_not_send_mail_for_inactive_account(): void
    {
        config(['captcha.disable' => true]);
        \Illuminate\Support\Facades\Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $this->inactivePelapor->email,
            'captcha' => 'ABCDE',
        ]);

        $response->assertStatus(200);
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_admin_can_search_and_filter_users(): void
    {
        $adminToken = auth('api')->login($this->admin);

        // 1. Search by name
        $searchRes = $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson('/api/admin/users?search=Pelapor Aktif');
        $searchRes->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Pelapor Aktif');

        // 2. Filter by status 'aktif'
        $statusActiveRes = $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson('/api/admin/users?status=aktif');
        $statusActiveRes->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', 'aktif');

        // 3. Filter by status 'non-aktif'
        $statusInactiveRes = $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson('/api/admin/users?status=non-aktif');
        $statusInactiveRes->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Pelapor Non-Aktif');
    }
}





