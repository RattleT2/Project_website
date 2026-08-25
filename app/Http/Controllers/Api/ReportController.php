<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Requests\Report\UpdateReportRequest;
use App\Models\Report;
use App\Models\ReportAnswer;
use App\Models\User;
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
        $user = auth('api')->user();
        $query = Report::with(['mediaType', 'answers.question.scoringRules']);

        if ($user->role === 'pelapor') {
            $query->where('user_id', $user->id);
        }

        $report = $query->findOrFail($id);

        $report->setRelation('answers', $report->answers->map(function (ReportAnswer $answer) {
            if ($answer->answer_type === 'file' && $answer->answer_value) {
                $answer->file_url = Storage::url($answer->answer_value);
            }

            return $answer;
        }));

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

    public function viewAttachment(int $reportId, int $questionId)
    {
        $answer = $this->resolveAttachmentAnswer($reportId, $questionId);

        return response()->file(Storage::disk('public')->path($answer->answer_value), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($answer->answer_value).'"',
        ]);
    }

    public function downloadAttachment(int $reportId, int $questionId)
    {
        $answer = $this->resolveAttachmentAnswer($reportId, $questionId);

        return response()->download(
            Storage::disk('public')->path($answer->answer_value),
            basename($answer->answer_value)
        );
    }

    private function resolveAttachmentAnswer(int $reportId, int $questionId): ReportAnswer
    {
        /** @var User $user */
        $user = auth('api')->user();

        $query = ReportAnswer::with('report')
            ->where('report_id', $reportId)
            ->where('question_id', $questionId)
            ->where('answer_type', 'file')
            ->whereNotNull('answer_value');

        if ($user->role === 'pelapor') {
            $query->whereHas('report', function ($reportQuery) use ($user) {
                $reportQuery->where('user_id', $user->id);
            });
        }

        $answer = $query->firstOrFail();

        if (!$answer instanceof ReportAnswer) {
            abort(404, 'File lampiran tidak ditemukan.');
        }

        // Membersihkan path seandainya masih tersimpan format URL atau /storage/ di database
        $cleanPath = $answer->answer_value;
        if (filter_var($cleanPath, FILTER_VALIDATE_URL)) {
            $cleanPath = parse_url($cleanPath, PHP_URL_PATH);
        }
        $cleanPath = ltrim($cleanPath, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }
        $cleanPath = ltrim($cleanPath, '/');

        if (!Storage::disk('public')->exists($cleanPath)) {
            abort(404, 'File lampiran tidak ditemukan.');
        }

        $answer->answer_value = $cleanPath;

        return $answer;
    }
}
