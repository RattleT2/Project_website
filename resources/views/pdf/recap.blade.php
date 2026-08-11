<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekapitulasi Laporan Media</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 5px; }
        h2 { font-size: 14px; text-align: center; color: #555; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #333; color: white; padding: 8px; text-align: left; }
        .table td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        .table tr:nth-child(even) { background: #f9f9f9; }
        .summary { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>REKAPITULASI LAPORAN EVALUASI MEDIA</h1>
    <h2>Pemerintah Kabupaten Banjar</h2>

    <p>Tanggal Cetak: {{ now()->format('d F Y') }}</p>
    <p>Total Laporan Disetujui: {{ $reports->count() }}</p>

    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>ID</th>
                <th>Pelapor</th>
                <th>Jenis Media</th>
                <th>Total Skor</th>
                <th>Kategori</th>
                <th>Status</th>
                <th>Tanggal Submit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reports as $index => $report)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>#{{ $report->id }}</td>
                <td>{{ $report->user->name }}</td>
                <td>{{ $report->mediaType->name }}</td>
                <td>{{ $report->total_score }}</td>
                <td>{{ $report->category }}</td>
                <td>{{ $report->status }}</td>
                <td>{{ $report->submitted_at?->format('d/m/Y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <p><strong>Ringkasan Kategori:</strong></p>
        <ul>
            <li>Kategori 1 (68-82): {{ $reports->where('total_score', '>=', 68)->count() }} laporan</li>
            <li>Kategori 2 (40-67): {{ $reports->whereBetween('total_score', [40, 67])->count() }} laporan</li>
            <li>Kategori 3 (20-39): {{ $reports->whereBetween('total_score', [20, 39])->count() }} laporan</li>
            <li>Tidak memenuhi: {{ $reports->where('total_score', '<', 20)->count() }} laporan</li>
        </ul>
    </div>
</body>
</html>
