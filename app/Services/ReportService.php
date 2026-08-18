<?php

namespace App\Services;

use App\Models\MediaType;
use App\Models\Report;
use App\Models\ReportAnswer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReportService
{
    protected ScoringService $scoringService;

    public function __construct(ScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    public function createReport(int $userId, array $data): Report
    {
        $mediaType = MediaType::findOrFail($data['media_type_id']);

        $report = Report::create([
            'user_id' => $userId,
            'media_type_id' => $data['media_type_id'],
            'report_code' => $this->generateReportCode($mediaType),
            'link_url' => $data['link_url'] ?? null,
            'status' => 'pending',
            'total_score' => 0,
        ]);

        $this->saveAnswers($report, $data['answers'] ?? []);

        if (isset($data['submit']) && $data['submit']) {
            $this->submitReport($report);
        }

        return $report->load('answers.question');
    }

    public function updateReport(Report $report, array $data): Report
    {
        if ($report->status !== 'pending') {
            throw new \Exception('Laporan tidak dapat diedit karena sudah diproses.');
        }

        if (isset($data['media_type_id']) && $data['media_type_id'] != $report->media_type_id) {
            $mediaType = MediaType::findOrFail($data['media_type_id']);
            $report->update([
                'media_type_id' => $data['media_type_id'],
                'report_code' => $this->generateReportCode($mediaType),
            ]);
        } elseif (isset($data['media_type_id'])) {
            $report->update(['media_type_id' => $data['media_type_id']]);
        }

        if (isset($data['link_url'])) {
            $report->update(['link_url' => $data['link_url']]);
        }

        if (isset($data['answers'])) {
            $this->saveAnswers($report, $data['answers']);
        }

        if (isset($data['submit']) && $data['submit']) {
            $this->submitReport($report);
        }

        return $report->fresh()->load('answers.question');
    }

    public function submitReport(Report $report): Report
    {
        $report->update([
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return $this->scoringService->calculateScore($report);
    }

    public function uploadFile(UploadedFile $file, int $questionId): string
    {
        return $file->store("reports/questions/{$questionId}", 'public');
    }

    public function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function generateReportCode(MediaType $mediaType): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $count = Report::where('media_type_id', $mediaType->id)
                ->whereNotNull('report_code')
                ->count();

            $code = $mediaType->code . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

            if (!Report::where('report_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \Exception('Gagal membuat kode laporan.');
    }

    private function saveAnswers(Report $report, array $answers): void
    {
        foreach ($answers as $answer) {
            $existingAnswer = ReportAnswer::where('report_id', $report->id)
                ->where('question_id', $answer['question_id'])
                ->first();

            if ($existingAnswer) {
                if ($existingAnswer->answer_type === 'file' && $existingAnswer->answer_value) {
                    $this->deleteFile($existingAnswer->answer_value);
                }

                $existingAnswer->update([
                    'answer_value' => $answer['answer_value'],
                    'answer_type' => $answer['answer_type'] ?? 'text',
                ]);
            } else {
                ReportAnswer::create([
                    'report_id' => $report->id,
                    'question_id' => $answer['question_id'],
                    'answer_value' => $answer['answer_value'],
                    'answer_type' => $answer['answer_type'] ?? 'text',
                    'score_earned' => 0,
                ]);
            }
        }
    }
}
