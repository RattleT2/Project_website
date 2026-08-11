<?php

namespace Database\Seeders;

use App\Models\EvaluationQuestion;
use App\Models\ScoringRule;
use Illuminate\Database\Seeder;

class ScoringRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            'Terverifikasi Dewan Pers' => [
                ['answer_option' => 'Ya', 'score' => 25],
                ['answer_option' => 'Tidak', 'score' => 0],
            ],
            'Pimpinan redaksi media memiliki' => [
                ['answer_option' => 'Ada UKW Utama', 'score' => 8],
                ['answer_option' => 'Tidak UKW Utama', 'score' => 0],
            ],
            'Wartawan atau biro yang ditugaskan' => [
                ['answer_option' => 'Ada + UKW', 'score' => 7],
                ['answer_option' => 'Ada tanpa UKW', 'score' => 4],
                ['answer_option' => 'Tidak ada', 'score' => 0],
            ],
            'Usia media sejak' => [
                ['answer_option' => '>4 tahun', 'score' => 10],
                ['answer_option' => '2-4 tahun', 'score' => 6],
                ['answer_option' => '<2 tahun', 'score' => 2],
            ],
            'berita secara rutin yang mencakup isu umum' => [
                ['answer_option' => 'Aktif', 'score' => 8],
                ['answer_option' => 'Tidak', 'score' => 0],
            ],
            'berkaitan dengan wilayah, kegiatan, atau isu di Kabupaten Banjar' => [
                ['answer_option' => 'Aktif', 'score' => 7],
                ['answer_option' => 'Tidak', 'score' => 0],
            ],
            'Jumlah pengikut' => [
                ['answer_option' => '>20.000', 'score' => 10],
                ['answer_option' => '5.000 - 20.000', 'score' => 6],
                ['answer_option' => '<5.000', 'score' => 3],
            ],
            'menu, rubrik, atau kategori khusus' => [
                ['answer_option' => 'Ada', 'score' => 7],
                ['answer_option' => 'Tidak', 'score' => 0],
            ],
            'tayangan berita Kabupaten Banjar' => [
                ['answer_option' => 'Ada', 'score' => 7],
                ['answer_option' => 'Tidak', 'score' => 0],
            ],
            'siaran berita Kabupaten Banjar' => [
                ['answer_option' => 'Ada', 'score' => 7],
                ['answer_option' => 'Tidak', 'score' => 0],
            ],
        ];

        foreach ($rules as $questionTextKey => $ruleSet) {
            $questions = EvaluationQuestion::where('question_text', 'like', "%{$questionTextKey}%")->get();

            foreach ($questions as $question) {
                foreach ($ruleSet as $rule) {
                    ScoringRule::firstOrCreate(
                        [
                            'question_id' => $question->id,
                            'answer_option' => $rule['answer_option'],
                        ],
                        ['score' => $rule['score']]
                    );
                }
            }
        }
    }
}
