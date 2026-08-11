<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'media_type_id',
        'category',
        'question_text',
        'weight',
        'is_mandatory',
    ];

    // Opsional: Untuk mengubah tipe data secara otomatis
    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    /**
     * Relasi: Satu Pertanyaan Evaluasi bisa memiliki banyak Jawaban (dari berbagai laporan).
     */
    public function answers()
    {
        return $this->hasMany(ReportAnswer::class, 'question_id');
    }

    public function scoringRules()
    {
        return $this->hasMany(ScoringRule::class, 'question_id');
    }

    public function mediaType()
    {
        return $this->belongsTo(MediaType::class, 'media_type_id');
    }
}