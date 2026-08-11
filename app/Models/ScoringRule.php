<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoringRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'answer_option',
        'score',
    ];

    public function question()
    {
        return $this->belongsTo(EvaluationQuestion::class, 'question_id');
    }
}
