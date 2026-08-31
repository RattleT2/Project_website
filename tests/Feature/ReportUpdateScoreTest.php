<?php

namespace Tests\Feature;

use App\Models\EvaluationQuestion;
use App\Models\MediaType;
use App\Models\Report;
use App\Models\ScoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportUpdateScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_report_answers_recalculates_total_score_and_category(): void
    {
        $pelapor = User::factory()->create([
            'role' => 'pelapor',
            'status' => 'aktif',
        ]);

        $mediaType = MediaType::create([
            'name' => 'Online',
            'code' => 'ONL',
        ]);

        $question = EvaluationQuestion::create([
            'category' => 'Keabsahan / Legalitas Media',
            'question_text' => 'Terverifikasi Dewan Pers',
            'weight' => 25,
            'media_type_id' => $mediaType->id,
        ]);

        ScoringRule::create([
            'question_id' => $question->id,
            'answer_option' => 'Ya',
            'score' => 70,
        ]);

        ScoringRule::create([
            'question_id' => $question->id,
            'answer_option' => 'Tidak',
            'score' => 0,
        ]);

        $token = auth('api')->login($pelapor);

        // 1. Create report with initial answer "Tidak" (score 0)
        $createResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/reports', [
                'media_type_id' => $mediaType->id,
                'answers' => [
                    [
                        'question_id' => $question->id,
                        'answer_value' => 'Tidak',
                        'answer_type' => 'text',
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $reportId = $createResponse->json('report.id');
        $this->assertEquals(0, $createResponse->json('report.total_score'));
        $this->assertEquals('Tidak memenuhi kategori', $createResponse->json('report.category'));

        // 2. Update report with answer "Ya" (score 70)
        $updateResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/reports/{$reportId}", [
                'answers' => [
                    [
                        'question_id' => $question->id,
                        'answer_value' => 'Ya',
                        'answer_type' => 'text',
                    ],
                ],
            ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals(70, $updateResponse->json('report.total_score'));
        $this->assertEquals('Kategori 1', $updateResponse->json('report.category'));

        // 3. Verify in database that total_score is updated to 70
        $this->assertDatabaseHas('reports', [
            'id' => $reportId,
            'total_score' => 70,
        ]);
    }
}
