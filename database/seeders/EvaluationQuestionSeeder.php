<?php

namespace Database\Seeders;

use App\Models\EvaluationQuestion;
use App\Models\ScoringRule;
use Illuminate\Database\Seeder;

class EvaluationQuestionSeeder extends Seeder
{
    public function run(): void
    {
        EvaluationQuestion::query()->delete();
        ScoringRule::query()->delete();

        $universalQuestions = [
            ['media_type_id' => null, 'category' => 'identitas', 'question_text' => 'Nama Media', 'weight' => 0, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'verifikasi', 'question_text' => 'Media terverifikasi Dewan Pers, baik verifikasi administrasi dan/atau verifikasi faktual', 'weight' => 25, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'verifikasi', 'question_text' => 'Upload bukti dukung verifikasi (PDF, maks 5MB)', 'weight' => 0, 'is_mandatory' => false],
            ['media_type_id' => null, 'category' => 'kompetensi', 'question_text' => 'Pimpinan redaksi media memiliki sertifikat Uji Kompetensi Wartawan yang masih berlaku', 'weight' => 8, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'kompetensi', 'question_text' => 'Upload sertifikat UKW pimpinan redaksi (PDF, maks 5MB)', 'weight' => 0, 'is_mandatory' => false],
            ['media_type_id' => null, 'category' => 'kompetensi', 'question_text' => 'Wartawan atau biro yang ditugaskan di Kabupaten Banjar', 'weight' => 7, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'kompetensi', 'question_text' => 'Upload bukti dukung sertifikat UKW wartawan (PDF, maks 5MB)', 'weight' => 0, 'is_mandatory' => false],
            ['media_type_id' => null, 'category' => 'usia', 'question_text' => 'Usia media sejak pertama kali terdaftar dan aktif digunakan', 'weight' => 10, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'legalitas', 'question_text' => 'Upload akta pendirian perusahaan (PDF, maks 5MB)', 'weight' => 0, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'aktivitas', 'question_text' => 'Media aktif mempublikasikan berita secara rutin yang mencakup isu umum', 'weight' => 8, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'aktivitas', 'question_text' => 'Link halaman berita isu umum', 'weight' => 0, 'is_mandatory' => false],
            ['media_type_id' => null, 'category' => 'aktivitas', 'question_text' => 'Media aktif mempublikasikan berita yang berkaitan dengan wilayah, kegiatan, atau isu di Kabupaten Banjar', 'weight' => 7, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'aktivitas', 'question_text' => 'Link halaman berita isu khusus wilayah Kabupaten Banjar', 'weight' => 0, 'is_mandatory' => false],
            ['media_type_id' => null, 'category' => 'sosial_media', 'question_text' => 'Jumlah pengikut pada akun resmi media di platform sosial media', 'weight' => 10, 'is_mandatory' => true],
            ['media_type_id' => null, 'category' => 'sosial_media', 'question_text' => 'Link sosial media', 'weight' => 0, 'is_mandatory' => false],
        ];

        foreach ($universalQuestions as $q) {
            EvaluationQuestion::create($q);
        }

        $mediaSpecificQuestions = [
            1 => 'Tersedia menu, rubrik, atau kategori khusus pada website/media yang secara konsisten memuat berita terkait Kabupaten Banjar',
            2 => 'Tersedia menu, rubrik, atau kategori khusus pada website/media yang secara konsisten memuat berita terkait Kabupaten Banjar',
            3 => 'Tersedia menu, rubrik, atau kategori khusus pada website/media yang secara konsisten memuat berita terkait Kabupaten Banjar',
            4 => 'Tersedia tayangan berita Kabupaten Banjar secara rutin',
            5 => 'Tersedia siaran berita Kabupaten Banjar secara rutin',
        ];

        foreach ($mediaSpecificQuestions as $mediaTypeId => $text) {
            EvaluationQuestion::create([
                'media_type_id' => $mediaTypeId,
                'category' => 'sosial_media',
                'question_text' => $text,
                'weight' => 7,
                'is_mandatory' => true,
            ]);
        }

        EvaluationQuestion::create([
            'media_type_id' => null,
            'category' => 'sosial_media',
            'question_text' => 'Link halaman Kabupaten Banjar atau Martapura',
            'weight' => 0,
            'is_mandatory' => false,
        ]);
    }
}
