<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaType;
use App\Models\Report;
use App\Services\ScoringService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function recapPdf(Request $request): Response
    {
        $query = Report::with(['user', 'mediaType', 'answers.question'])
            ->where('status', 'disetujui')
            ->orderBy('total_score', 'desc');

        if ($request->filled('media_type_id')) {
            $query->where('media_type_id', $request->media_type_id);
        }

        $reports = $query->get();

        $reports->transform(function ($report) {
            $report->category = $this->scoringService->getCategory($report->total_score);
            return $report;
        });

        $pdf = Pdf::loadView('pdf.recap', [
            'reports' => $reports,
        ]);

        return $pdf->download('rekapitulasi-laporan-media.pdf');
    }

    public function recapExcel(Request $request): StreamedResponse
    {
        $query = Report::with(['user', 'mediaType', 'answers.question'])
            ->where('status', 'disetujui')
            ->orderBy('total_score', 'desc');

        $mediaType = null;
        if ($request->filled('media_type_id')) {
            $query->where('media_type_id', $request->media_type_id);
            $mediaType = MediaType::find($request->media_type_id);
        }

        $reports = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekapitulasi Laporan');

        // Judul & Header Info
        $sheet->setCellValue('A1', 'REKAPITULASI LAPORAN EVALUASI MEDIA');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $mediaTypeName = $mediaType ? $mediaType->name : 'Semua Jenis Media';
        $sheet->setCellValue('A2', "Jenis Media: {$mediaTypeName}");
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Tanggal Unduh: ' . now()->translatedFormat('d F Y H:i'));
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A3')->getFont()->setSize(10);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header Tabel (Baris 5)
        $headers = [
            'A5' => 'No',
            'B5' => 'Kode Media',
            'C5' => 'Nama Media',
            'D5' => 'Tanggal Submit',
            'E5' => 'Total Score',
            'F5' => 'Kategori',
        ];

        foreach ($headers as $cell => $headerTitle) {
            $sheet->setCellValue($cell, $headerTitle);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Dark Slate
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A5:F5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // Data Rows
        $rowIndex = 6;
        foreach ($reports as $index => $report) {
            $mediaNameAnswer = $report->answers
                ->first(fn ($a) => $a->question && $a->question->question_text === 'Nama Media');
            $mediaName = $mediaNameAnswer?->answer_value ?? '-';

            $category = $this->scoringService->getCategory($report->total_score);

            $sheet->setCellValue("A{$rowIndex}", $index + 1);
            $sheet->setCellValue("B{$rowIndex}", $report->report_code ?? '-');
            $sheet->setCellValue("C{$rowIndex}", $mediaName);
            $submittedAt = $report->submitted_at ?? $report->created_at;
            $sheet->setCellValue("D{$rowIndex}", $submittedAt ? $submittedAt->format('d/m/Y H:i') : '-');
            $sheet->setCellValue("E{$rowIndex}", $report->total_score);
            $sheet->setCellValue("F{$rowIndex}", $category);

            // Row zebra striping for readability
            if ($index % 2 === 1) {
                $sheet->getStyle("A{$rowIndex}:F{$rowIndex}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8FAFC');
            }

            $rowIndex++;
        }

        $lastRow = max(5, $rowIndex - 1);

        // Styling Borders & Alignment
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ];
        $sheet->getStyle("A5:F{$lastRow}")->applyFromArray($borderStyle);

        if ($lastRow >= 6) {
            $sheet->getStyle("A6:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B6:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C6:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D6:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E6:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F6:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Auto-fit column width
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'rekapitulasi-laporan-media' . ($mediaType ? '-' . Str::slug($mediaType->name) : '') . '-' . date('YmdHis') . '.xlsx';

        return new StreamedResponse(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}
