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

class ReportOptionalAttachmentCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected User $pelapor;
    protected MediaType $mediaType;
    protected EvaluationQuestion $qDewanPers;
    protected EvaluationQuestion $qUploadDewanPers;
    protected EvaluationQuestion $qAktaMandatory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelapor = User::factory()->create([
            'role' => 'pelapor',
            'status' => 'aktif',
        ]);

        $this->mediaType = MediaType::create([
            'name' => 'Online',
            'code' => 'ONL',
        ]);

        $this->qDewanPers = EvaluationQuestion::create([
            'category' => 'verifikasi',
            'question_text' => 'Media terverifikasi Dewan Pers, baik verifikasi administrasi dan/atau verifikasi faktual',
            'weight' => 25,
            'is_mandatory' => true,
            'media_type_id' => null,
        ]);

        $this->qUploadDewanPers = EvaluationQuestion::create([
            'category' => 'verifikasi',
            'question_text' => 'Upload bukti dukung verifikasi (PDF, maks 5MB)',
            'weight' => 0,
            'is_mandatory' => false,
            'media_type_id' => null,
        ]);

        $this->qAktaMandatory = EvaluationQuestion::create([
            'category' => 'legalitas',
            'question_text' => 'Upload akta pendirian perusahaan (PDF, maks 5MB)',
            'weight' => 0,
            'is_mandatory' => true,
            'media_type_id' => null,
        ]);
    }

    public function test_changing_parent_to_tidak_deletes_optional_supporting_file_and_answer(): void
    {
        Storage::fake('public');
        $token = auth('api')->login($this->pelapor);

        $fakeFilePath = 'reports/questions/' . $this->qUploadDewanPers->id . '/test_dewan_pers.pdf';
        Storage::disk('public')->put($fakeFilePath, 'fake content');

        // Create initial draft with "Ya" and uploaded file
        $report = Report::create([
            'user_id' => $this->pelapor->id,
            'media_type_id' => $this->mediaType->id,
            'report_code' => 'ONL-001',
            'status' => 'pending',
        ]);

        ReportAnswer::create([
            'report_id' => $report->id,
            'question_id' => $this->qDewanPers->id,
            'answer_value' => 'Ya',
            'answer_type' => 'text',
        ]);

        ReportAnswer::create([
            'report_id' => $report->id,
            'question_id' => $this->qUploadDewanPers->id,
            'answer_value' => $fakeFilePath,
            'answer_type' => 'file',
        ]);

        Storage::disk('public')->assertExists($fakeFilePath);
        $this->assertDatabaseHas('report_answers', ['question_id' => $this->qUploadDewanPers->id]);

        // Update parent to "Tidak"
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/reports/{$report->id}", [
                'answers' => [
                    [
                        'question_id' => $this->qDewanPers->id,
                        'answer_value' => 'Tidak',
                        'answer_type' => 'text',
                    ],
                ],
            ]);

        $response->assertStatus(200);

        // File must be deleted from storage
        Storage::disk('public')->assertMissing($fakeFilePath);

        // Child answer record must be removed
        $this->assertDatabaseMissing('report_answers', [
            'report_id' => $report->id,
            'question_id' => $this->qUploadDewanPers->id,
        ]);
    }

    public function test_explicit_delete_attachment_endpoint(): void
    {
        Storage::fake('public');
        $token = auth('api')->login($this->pelapor);

        $fakeFilePath = 'reports/questions/' . $this->qUploadDewanPers->id . '/test.pdf';
        Storage::disk('public')->put($fakeFilePath, 'content');

        $report = Report::create([
            'user_id' => $this->pelapor->id,
            'media_type_id' => $this->mediaType->id,
            'report_code' => 'ONL-002',
            'status' => 'pending',
        ]);

        ReportAnswer::create([
            'report_id' => $report->id,
            'question_id' => $this->qUploadDewanPers->id,
            'answer_value' => $fakeFilePath,
            'answer_type' => 'file',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/reports/{$report->id}/answers/{$this->qUploadDewanPers->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Lampiran berhasil dihapus.');

        Storage::disk('public')->assertMissing($fakeFilePath);
        $this->assertDatabaseMissing('report_answers', [
            'report_id' => $report->id,
            'question_id' => $this->qUploadDewanPers->id,
        ]);
    }

    public function test_mandatory_question_attachment_cannot_be_deleted_via_delete_endpoint(): void
    {
        $token = auth('api')->login($this->pelapor);

        $report = Report::create([
            'user_id' => $this->pelapor->id,
            'media_type_id' => $this->mediaType->id,
            'report_code' => 'ONL-003',
            'status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/reports/{$report->id}/answers/{$this->qAktaMandatory->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Berkas pada pertanyaan wajib tidak dapat dihapus.');
    }

    public function test_standalone_delete_upload_endpoint(): void
    {
        Storage::fake('public');
        $token = auth('api')->login($this->pelapor);

        $fakeFilePath = 'reports/questions/99/temp.pdf';
        Storage::disk('public')->put($fakeFilePath, 'temp content');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/reports/delete-upload', [
                'file_path' => $fakeFilePath,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'File berhasil dihapus.');

        Storage::disk('public')->assertMissing($fakeFilePath);
    }
}

