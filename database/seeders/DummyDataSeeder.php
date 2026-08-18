<?php

namespace Database\Seeders;

use App\Models\EvaluationQuestion;
use App\Models\MediaType;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Report::whereNotNull('report_code')->exists()) {
            $this->command->warn('Data dummy laporan sudah ada. Lewati pembuatan laporan.');
            return;
        }

        $users = $this->seedUsers();
        $this->seedReports($users);
    }

    private function seedUsers(): array
    {
        $names = [
            ['Budi Santoso', 'budi@mediabanjar.com'],
            ['Siti Rahma', 'siti@beritabanua.id'],
            ['Ahmad Fauzi', 'ahmad@kalselonline.com'],
            ['Dewi Lestari', 'dewi@radarbanjar.co.id'],
            ['Rudi Hartono', 'rudi@martapura.news'],
            ['Maya Sari', 'maya@tvbanjar.tv'],
            ['Agus Salim', 'agus@radiomartapura.com'],
            ['Fitri Handayani', 'fitri@mediacetak.co.id'],
        ];

        $users = [];

        foreach ($names as [$name, $email]) {
            $users[] = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password123'),
                    'role' => 'pelapor',
                    'status' => 'aktif',
                ]
            );
        }

        $this->command->info(count($users) . ' user pelapor dibuat.');

        return $users;
    }

    private function seedReports(array $users): void
    {
        $service = app(ReportService::class);
        $questions = EvaluationQuestion::with('scoringRules')->get();

        $mediaNames = [
            1 => ['Banjar Post', 'Kalsel Online', 'Berita Banua', 'Martapura News'],
            2 => ['Radar Banjar', 'Banjar Harian', 'Koran Banua'],
            3 => ['Banjar Elektronik', 'E-Banjar News', 'Media Digital Banjar'],
            4 => ['TV Banjar', 'Banjar TV', 'Kalsel TV'],
            5 => ['Radio Martapura FM', 'Radio Banjar', 'Suara Banua FM'],
        ];

        $plans = [
            [0, 1, 'disetujui'],
            [1, 1, 'disetujui'],
            [2, 1, 'proses'],
            [3, 1, 'pending'],
            [4, 2, 'disetujui'],
            [5, 2, 'proses'],
            [6, 2, 'pending'],
            [7, 4, 'disetujui'],
            [0, 4, 'proses'],
            [1, 4, 'pending'],
            [2, 5, 'disetujui'],
            [3, 5, 'proses'],
            [4, 5, 'pending'],
            [5, 3, 'disetujui'],
            [6, 3, 'proses'],
            [7, 3, 'pending'],
        ];

        $daysAgo = 0;

        foreach ($plans as $plan) {
            [$userIndex, $mediaTypeId, $status] = $plan;

            $answers = $this->buildAnswers(
                $questions,
                $mediaTypeId,
                $mediaNames[$mediaTypeId]
            );

            $report = $service->createReport($users[$userIndex]->id, [
                'media_type_id' => $mediaTypeId,
                'answers' => $answers,
                'submit' => true,
            ]);

            $report->update([
                'status' => $status,
                'submitted_at' => now()->subDays($daysAgo)->subHours(random_int(0, 8)),
            ]);

            $daysAgo += random_int(0, 2);
        }

        $this->command->info(count($plans) . ' laporan dummy dibuat.');
    }

    private function buildAnswers($questions, int $mediaTypeId, array $mediaNames): array
    {
        $answers = [];
        $mediaName = $mediaNames[array_rand($mediaNames)];
        $slug = strtolower(Str::slug($mediaName));

        $applicable = $questions->filter(function ($q) use ($mediaTypeId) {
            return $q->media_type_id === null || $q->media_type_id === $mediaTypeId;
        });

        foreach ($applicable as $question) {
            $text = strtolower($question->question_text);

            if (str_contains($text, 'nama media')) {
                $answers[] = [
                    'question_id' => $question->id,
                    'answer_value' => $mediaName,
                    'answer_type' => 'text',
                ];
            } elseif (str_contains($text, 'upload')) {
                $answers[] = [
                    'question_id' => $question->id,
                    'answer_value' => "dummy/dokumen-q{$question->id}.pdf",
                    'answer_type' => 'file',
                ];
            } elseif (str_contains($text, 'link')) {
                $answers[] = [
                    'question_id' => $question->id,
                    'answer_value' => $this->randomUrl($slug),
                    'answer_type' => 'url',
                ];
            } else {
                $option = $question->scoringRules->isNotEmpty()
                    ? $question->scoringRules->random()->answer_option
                    : 'Ya';

                $answers[] = [
                    'question_id' => $question->id,
                    'answer_value' => $option,
                    'answer_type' => 'text',
                ];
            }
        }

        return $answers;
    }

    private function randomUrl(string $slug): string
    {
        $paths = [
            '/berita/umum',
            '/berita/terkini',
            '/kabupaten-banjar',
            '/kategori/martapura',
        ];

        return 'https://' . $slug . '.com' . $paths[array_rand($paths)];
    }
}
