<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'question_id',
        'answer_value',
        'answer_type',
        'score_earned',
    ];

    /**
     * Relasi: Setiap Jawaban adalah bagian dari satu Laporan.
     */
    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Relasi: Setiap Jawaban merujuk pada satu Pertanyaan Evaluasi.
     */
    public function question()
    {
        return $this->belongsTo(EvaluationQuestion::class, 'question_id');
    }
}