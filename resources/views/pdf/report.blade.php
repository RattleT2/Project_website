<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Media #{{ $report->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 5px; }
        h2 { font-size: 14px; text-align: center; color: #555; margin-bottom: 20px; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; border-collapse: collapse; }
        .info td { padding: 4px 8px; }
        .info td:first-child { width: 130px; font-weight: bold; }
        .score-box { background: #f0f0f0; padding: 10px; margin-bottom: 20px; text-align: center; border-radius: 5px; }
        .score-box h3 { margin: 0; font-size: 24px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #333; color: white; padding: 8px; text-align: left; }
        .table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .table tr:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>
    <h1>LAPORAN EVALUASI MEDIA</h1>
    <h2>Pemerintah Kabupaten Banjar</h2>

    <div class="info">
        <table>
            <tr><td>ID Laporan</td><td>: #{{ $report->id }}</td></tr>
            <tr><td>Pelapor</td><td>: {{ $report->user->name }}</td></tr>
            <tr><td>Email</td><td>: {{ $report->user->email }}</td></tr>
            <tr><td>Jenis Media</td><td>: {{ $report->mediaType->name }}</td></tr>
            <tr><td>Status</td><td>: {{ $report->status }}</td></tr>
            <tr><td>Tanggal Submit</td><td>: {{ $report->submitted_at?->format('d F Y H:i') ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="score-box">
        <p>Total Skor</p>
        <h3>{{ $report->total_score }}</h3>
        <p><strong>{{ $category }}</strong></p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Pertanyaan</th>
                <th>Jawaban</th>
                <th>Tipe</th>
                <th>Skor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->answers as $index => $answer)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $answer->question->question_text }}</td>
                <td>
                    @if($answer->answer_type === 'file')
                        File tersimpan
                    @elseif($answer->answer_type === 'url')
                        <a href="{{ $answer->answer_value }}">{{ $answer->answer_value }}</a>
                    @else
                        {{ $answer->answer_value }}
                    @endif
                </td>
                <td>{{ $answer->answer_type }}</td>
                <td>{{ $answer->score_earned }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
