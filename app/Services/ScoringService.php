<?php

namespace App\Services;

use App\Models\EvaluationQuestion;
use App\Models\Report;
use App\Models\ReportAnswer;

class ScoringService
{
    public function calculateScore(Report $report): Report
    {
        $totalScore = 0;

        $report->unsetRelation('answers');
        $answers = $report->answers()->get();

        foreach ($answers as $answer) {
            $score = $this->getScoreForAnswer($answer->question_id, $answer->answer_value);
            $answer->update(['score_earned' => $score]);
            $totalScore += $score;
        }

        $report->update(['total_score' => $totalScore]);

        return $report->fresh()->load('answers.question');
    }

    public function getScoreForAnswer(int $questionId, string $answerValue): int
    {
        $question = EvaluationQuestion::with('scoringRules')->find($questionId);

        if (!$question || $question->scoringRules->isEmpty()) {
            return 0;
        }

        $rule = $question->scoringRules->firstWhere('answer_option', $answerValue);

        return $rule ? $rule->score : 0;
    }

    public function getCategory(int $totalScore): string
    {
        return match (true) {
            $totalScore >= 68 => 'Kategori 1',
            $totalScore >= 40 => 'Kategori 2',
            $totalScore >= 20 => 'Kategori 3',
            default => 'Tidak memenuhi kategori',
        };
    }
}
