<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ScoringService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    protected ScoringService $scoringService;

    public function __construct(ScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    public function singlePdf(int $id): Response
    {
        $report = Report::with([
            'user',
            'mediaType',
            'answers.question.scoringRules',
        ])->findOrFail($id);

        $category = $this->scoringService->getCategory($report->total_score);

        $pdf = Pdf::loadView('pdf.report', [
            'report' => $report,
            'category' => $category,
        ]);

        return $pdf->download("laporan-{$report->id}.pdf");
    }

    public function recapPdf(): Response
    {
        $reports = Report::with(['user', 'mediaType', 'answers.question'])
            ->where('status', 'disetujui')
            ->orderBy('total_score', 'desc')
            ->get();

        $reports->transform(function ($report) {
            $report->category = $this->scoringService->getCategory($report->total_score);
            return $report;
        });

        $pdf = Pdf::loadView('pdf.recap', [
            'reports' => $reports,
        ]);

        return $pdf->download('rekapitulasi-laporan-media.pdf');
    }
}
