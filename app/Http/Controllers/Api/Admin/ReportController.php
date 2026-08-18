<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReportStatusRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    protected ScoringService $scoringService;

    public function __construct(ScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['user', 'mediaType', 'answers.question']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('media_type_id')) {
            $query->where('media_type_id', $request->media_type_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('id', 'like', "%{$search}%")
                ->orWhere('report_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('score_min')) {
            $query->where('total_score', '>=', $request->score_min);
        }

        if ($request->filled('score_max')) {
            $query->where('total_score', '<=', $request->score_max);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSort = ['created_at', 'total_score', 'status', 'id', 'report_code'];
        if (in_array($sortBy, $allowedSort)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $reports = $query->paginate($perPage);

        $reports->through(fn (Report $report) => $this->formatListItem($report));

        return response()->json($reports);
    }

    public function show(int $id): JsonResponse
    {
        $report = Report::with(['user', 'mediaType', 'answers.question.scoringRules'])->findOrFail($id);

        $category = $this->scoringService->getCategory($report->total_score);

        return response()->json([
            'report' => $report,
            'category' => $category,
        ]);
    }

    public function updateStatus(UpdateReportStatusRequest $request, int $id): JsonResponse
    {
        $report = Report::with('user')->findOrFail($id);
        $oldStatus = $report->status;

        if ($request->status === 'proses' || $request->status === 'disetujui') {
            $this->scoringService->calculateScore($report);
        }

        $report->update(['status' => $request->status]);

        $this->sendStatusNotification($report, $oldStatus, $request->status);

        return response()->json([
            'message' => 'Status laporan berhasil diperbarui.',
            'report' => $report->load('answers.question'),
            'category' => $this->scoringService->getCategory($report->total_score),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        if (isset($request->answers)) {
            foreach ($request->answers as $answerData) {
                $report->answers()
                    ->where('question_id', $answerData['question_id'])
                    ->update([
                        'answer_value' => $answerData['answer_value'],
                        'answer_type' => $answerData['answer_type'] ?? 'text',
                    ]);
            }

            $this->scoringService->calculateScore($report);
        }

        if ($request->filled('media_type_id')) {
            $report->update(['media_type_id' => $request->media_type_id]);
        }

        return response()->json([
            'message' => 'Laporan berhasil diperbarui.',
            'report' => $report->fresh()->load('answers.question'),
            'category' => $this->scoringService->getCategory($report->total_score),
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $totalUsers = User::where('role', 'pelapor')->count();
        $totalReports = Report::count();
        $pendingReports = Report::where('status', 'pending')->count();
        $processingReports = Report::where('status', 'proses')->count();
        $approvedReports = Report::where('status', 'disetujui')->count();

        $reportsPerMedia = Report::selectRaw('media_type_id, count(*) as total')
            ->with('mediaType')
            ->groupBy('media_type_id')
            ->get();

        $categoryCounts = [
            'kategori_1' => Report::where('total_score', '>=', 68)->count(),
            'kategori_2' => Report::whereBetween('total_score', [40, 67])->count(),
            'kategori_3' => Report::whereBetween('total_score', [20, 39])->count(),
            'tidak_memenuhi' => Report::where('total_score', '<', 20)->count(),
        ];

        return response()->json([
            'total_users' => $totalUsers,
            'total_reports' => $totalReports,
            'pending_reports' => $pendingReports,
            'processing_reports' => $processingReports,
            'approved_reports' => $approvedReports,
            'reports_per_media' => $reportsPerMedia,
            'category_counts' => $categoryCounts,
        ]);
    }

    private function formatListItem(Report $report): array
    {
        $mediaNameAnswer = $report->answers
            ->first(fn ($a) => $a->question && $a->question->question_text === 'Nama Media');

        return [
            'id' => $report->id,
            'report_code' => $report->report_code,
            'media_name' => $mediaNameAnswer?->answer_value,
            'user_name' => $report->user?->name,
            'user_email' => $report->user?->email,
            'media_type' => $report->mediaType?->name,
            'submitted_at' => $report->submitted_at?->format('Y-m-d H:i:s'),
            'total_score' => $report->total_score,
            'category' => $report->category,
            'status' => $report->status,
        ];
    }

    private function sendStatusNotification(Report $report, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $user = $report->user;
        if (!$user || !$user->email) {
            return;
        }

        $statusLabels = [
            'pending' => 'Pending',
            'proses' => 'Sedang Diproses',
            'disetujui' => 'Disetujui',
        ];

        $data = [
            'userName' => $user->name,
            'reportId' => $report->id,
            'oldStatus' => $statusLabels[$oldStatus] ?? $oldStatus,
            'newStatus' => $statusLabels[$newStatus] ?? $newStatus,
            'mediaType' => $report->mediaType?->name ?? '-',
            'totalScore' => $report->total_score,
            'category' => $this->scoringService->getCategory($report->total_score),
        ];

        try {
            Mail::send('emails.report-status', $data, function ($message) use ($user, $report) {
                $message->to($user->email, $user->name)
                    ->subject("Status Laporan #{$report->id} Diperbarui");
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send status notification email: ' . $e->getMessage());
        }
    }
}
