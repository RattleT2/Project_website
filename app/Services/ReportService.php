<?php

namespace App\Services;

use App\Models\EvaluationQuestion;
use App\Models\MediaType;
use App\Models\Report;
use App\Models\ReportAnswer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportService
{
    protected ScoringService $scoringService;

    public function __construct(ScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    public function createReport(int $userId, array $data): Report
    {
        $isSubmit = isset($data['submit']) && (bool) $data['submit'];
        $mediaType = MediaType::findOrFail($data['media_type_id']);

        if ($isSubmit) {
            $this->validateMandatoryQuestions($data['media_type_id'], $data['answers'] ?? []);
        }

        $report = Report::create([
            'user_id' => $userId,
            'media_type_id' => $data['media_type_id'],
            'report_code' => $this->generateReportCode($mediaType),
            'link_url' => $data['link_url'] ?? null,
            'status' => 'pending',
            'submitted_at' => $isSubmit ? now() : null,
            'total_score' => 0,
        ]);

        $this->saveAnswers($report, $data['answers'] ?? []);
        $this->scoringService->calculateScore($report);

        return $report->fresh()->load('answers.question');
    }

    public function updateReport(Report $report, array $data): Report
    {
        if ($report->status !== 'pending') {
            throw new \Exception('Laporan tidak dapat diedit karena sudah diproses.');
        }

        $isSubmit = isset($data['submit']) && (bool) $data['submit'];

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

        if ($isSubmit) {
            $this->submitReport($report);
        } else {
            $this->scoringService->calculateScore($report);
        }

        return $report->fresh()->load('answers.question');
    }

    public function submitReport(Report $report): Report
    {
        $report->load('answers');

        $answersArray = $report->answers->map(function ($a) {
            return [
                'question_id' => $a->question_id,
                'answer_value' => $a->answer_value,
            ];
        })->toArray();

        $this->validateMandatoryQuestions($report->media_type_id, $answersArray);

        $report->update([
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return $this->scoringService->calculateScore($report);
    }

    public function validateMandatoryQuestions(int $mediaTypeId, array $answers): void
    {
        $mandatoryQuestions = EvaluationQuestion::where('is_mandatory', true)
            ->where(function ($q) use ($mediaTypeId) {
                $q->whereNull('media_type_id')
                  ->orWhere('media_type_id', $mediaTypeId);
            })
            ->get();

        $answersCollection = collect($answers);
        $missingQuestions = [];

        foreach ($mandatoryQuestions as $question) {
            $answer = $answersCollection->firstWhere('question_id', $question->id);
            $val = $answer ? trim((string) ($answer['answer_value'] ?? '')) : '';

            if ($val === '') {
                $missingQuestions[] = "Pertanyaan '{$question->question_text}' wajib diisi.";
            }
        }

        if (!empty($missingQuestions)) {
            throw ValidationException::withMessages([
                'answers' => $missingQuestions,
            ]);
        }
    }

    public function uploadFile(UploadedFile $file, int $questionId): string
    {
        return $file->store("reports/questions/{$questionId}", 'public');
    }

    public function deleteFile(?string $path): void
    {
        if ($path) {
            $clean = $this->cleanFilePath($path);
            if ($clean && Storage::disk('public')->exists($clean)) {
                Storage::disk('public')->delete($clean);
            }
        }
    }

    public function cleanFilePath(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $value = parse_url($value, PHP_URL_PATH);
        }

        $value = ltrim((string) $value, '/');
        if (str_starts_with($value, 'storage/')) {
            $value = substr($value, 8);
        }

        return ltrim($value, '/');
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
            $questionId = $answer['question_id'] ?? null;
            if (!$questionId) {
                continue;
            }

            $value = $answer['answer_value'] ?? null;
            $type = $answer['answer_type'] ?? 'text';

            if ($type === 'file') {
                $value = $this->cleanFilePath($value);
            }

            $existingAnswer = ReportAnswer::where('report_id', $report->id)
                ->where('question_id', $questionId)
                ->first();

            // Jika nilai jawaban dikirim null atau kosong ("")
            if ($value === null || trim((string) $value) === '') {
                if ($existingAnswer) {
                    if ($existingAnswer->answer_type === 'file' && $existingAnswer->answer_value) {
                        $this->deleteFile($existingAnswer->answer_value);
                    }
                    $existingAnswer->delete();
                }
                continue;
            }

            if ($existingAnswer) {
                // Hapus file lama jika file barunya memang berbeda
                if ($existingAnswer->answer_type === 'file' && $existingAnswer->answer_value && $existingAnswer->answer_value !== $value) {
                    $this->deleteFile($existingAnswer->answer_value);
                }

                $existingAnswer->update([
                    'answer_value' => $value,
                    'answer_type' => $type,
                ]);
            } else {
                ReportAnswer::create([
                    'report_id' => $report->id,
                    'question_id' => $questionId,
                    'answer_value' => $value,
                    'answer_type' => $type,
                    'score_earned' => 0,
                ]);
            }
        }

        // Jalankan pembersihan otomatis untuk bukti dukung opsional jika jawaban utama bernilai negatif (misal "Tidak")
        $this->cleanupNegativeOptionEvidence($report, $answers);
    }

    private function cleanupNegativeOptionEvidence(Report $report, array $answers): void
    {
        $allQuestions = EvaluationQuestion::all();

        foreach ($answers as $answer) {
            $questionId = $answer['question_id'] ?? null;
            $val = trim((string) ($answer['answer_value'] ?? ''));

            if (!$questionId) {
                continue;
            }

            $question = $allQuestions->firstWhere('id', $questionId);
            if (!$question) {
                continue;
            }

            $qText = strtolower($question->question_text);
            $valLower = strtolower($val);

            $childQuestionIdToDelete = null;

            // 1. Verifikasi Dewan Pers: Ya / Tidak
            if (str_contains($qText, 'dewan pers') && $valLower === 'tidak') {
                $child = $allQuestions->first(fn ($q) => $q->category === 'verifikasi' && str_contains(strtolower($q->question_text), 'upload') && !$q->is_mandatory);
                $childQuestionIdToDelete = $child?->id;
            }

            // 2. UKW Pemred: Ada UKW Utama / Tidak UKW Utama
            elseif (str_contains($qText, 'pimpinan redaksi') && str_contains($valLower, 'tidak')) {
                $child = $allQuestions->first(fn ($q) => $q->category === 'kompetensi' && str_contains(strtolower($q->question_text), 'pimpinan redaksi') && str_contains(strtolower($q->question_text), 'upload') && !$q->is_mandatory);
                $childQuestionIdToDelete = $child?->id;
            }

            // 3. Wartawan / Biro Banjar: Ada + UKW / Ada tanpa UKW / Tidak ada
            elseif (str_contains($qText, 'wartawan atau biro') && (str_contains($valLower, 'tidak') || str_contains($valLower, 'tanpa'))) {
                $child = $allQuestions->first(fn ($q) => $q->category === 'kompetensi' && str_contains(strtolower($q->question_text), 'wartawan') && str_contains(strtolower($q->question_text), 'upload') && !$q->is_mandatory);
                $childQuestionIdToDelete = $child?->id;
            }

            // 4. Berita Isu Umum: Aktif / Tidak
            elseif (str_contains($qText, 'isu umum') && $valLower === 'tidak') {
                $child = $allQuestions->first(fn ($q) => $q->category === 'aktivitas' && str_contains(strtolower($q->question_text), 'isu umum') && str_contains(strtolower($q->question_text), 'link') && !$q->is_mandatory);
                $childQuestionIdToDelete = $child?->id;
            }

            // 5. Berita Isu Khusus Banjar: Aktif / Tidak
            elseif (str_contains($qText, 'kabupaten banjar') && str_contains($qText, 'aktif') && $valLower === 'tidak') {
                $child = $allQuestions->first(fn ($q) => $q->category === 'aktivitas' && str_contains(strtolower($q->question_text), 'khusus') && str_contains(strtolower($q->question_text), 'link') && !$q->is_mandatory);
                $childQuestionIdToDelete = $child?->id;
            }

            // 6. Rubrik / Tayangan / Siaran Khusus Media: Ada / Tidak
            elseif ((str_contains($qText, 'rubrik') || str_contains($qText, 'tayangan') || str_contains($qText, 'siaran')) && $valLower === 'tidak') {
                $child = $allQuestions->first(fn ($q) => str_contains(strtolower($q->question_text), 'martapura') && str_contains(strtolower($q->question_text), 'link') && !$q->is_mandatory);
                $childQuestionIdToDelete = $child?->id;
            }

            if ($childQuestionIdToDelete) {
                $childAnswer = ReportAnswer::where('report_id', $report->id)
                    ->where('question_id', $childQuestionIdToDelete)
                    ->first();

                if ($childAnswer) {
                    if ($childAnswer->answer_type === 'file' && $childAnswer->answer_value) {
                        $this->deleteFile($childAnswer->answer_value);
                    }
                    $childAnswer->delete();
                }
            }
        }
    }
}