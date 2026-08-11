<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Requests\Report\UpdateReportRequest;
use App\Models\Report;
use App\Models\ReportAnswer;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(): JsonResponse
    {
        $reports = Report::with(['mediaType', 'answers.question'])
            ->where('user_id', auth('api')->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reports);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = $this->reportService->createReport(
            auth('api')->id(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Laporan berhasil dibuat.',
            'report' => $report,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $report = Report::with(['mediaType', 'answers.question.scoringRules'])
            ->where('user_id', auth('api')->id())
            ->findOrFail($id);

        return response()->json($report);
    }

    public function update(UpdateReportRequest $request, int $id): JsonResponse
    {
        $report = Report::where('user_id', auth('api')->id())->findOrFail($id);

        $report = $this->reportService->updateReport($report, $request->validated());

        return response()->json([
            'message' => 'Laporan berhasil diperbarui.',
            'report' => $report,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $report = Report::where('user_id', auth('api')->id())->findOrFail($id);

        if ($report->status !== 'pending') {
            return response()->json(['message' => 'Laporan tidak dapat dihapus karena sudah diproses.'], 400);
        }

        foreach ($report->answers as $answer) {
            if ($answer->answer_type === 'file' && $answer->answer_value) {
                Storage::disk('public')->delete($answer->answer_value);
            }
        }

        $report->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus.']);
    }

    public function submit(int $id): JsonResponse
    {
        $report = Report::where('user_id', auth('api')->id())->findOrFail($id);

        $report = $this->reportService->submitReport($report);

        return response()->json([
            'message' => 'Laporan berhasil disubmit.',
            'report' => $report->load('answers.question'),
        ]);
    }

    public function uploadFile(Request $request, int $reportId, int $questionId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5120',
        ]);

        $report = Report::where('user_id', auth('api')->id())
            ->where('status', 'pending')
            ->findOrFail($reportId);

        $file = $request->file('file');
        $path = $this->reportService->uploadFile($file, $questionId);

        $answer = ReportAnswer::updateOrCreate(
            [
                'report_id' => $report->id,
                'question_id' => $questionId,
            ],
            [
                'answer_value' => $path,
                'answer_type' => 'file',
                'score_earned' => 0,
            ]
        );

        return response()->json([
            'message' => 'File berhasil diupload.',
            'answer' => $answer,
            'url' => Storage::url($path),
        ]);
    }
}
