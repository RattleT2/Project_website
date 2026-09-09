<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvaluationQuestion;
use App\Models\MediaType;
use Illuminate\Http\JsonResponse;

class SharedController extends Controller
{
    public function mediaTypes(): JsonResponse
    {
        return response()->json(MediaType::all());
    }

    public function evaluationQuestions(): JsonResponse
    {
        $this->ensureWhatsappQuestionExists();

        return response()->json(
            EvaluationQuestion::with('scoringRules')
                ->whereNull('media_type_id')
                ->orderBy('id')
                ->get()
        );
    }

    public function questionsByMediaType(int $mediaTypeId): JsonResponse
    {
        $this->ensureWhatsappQuestionExists();

        $questions = EvaluationQuestion::with('scoringRules')
            ->where(function ($query) use ($mediaTypeId) {
                $query->whereNull('media_type_id')
                    ->orWhere('media_type_id', $mediaTypeId);
            })
            ->orderBy('id')
            ->get();

        return response()->json($questions);
    }

    private function ensureWhatsappQuestionExists(): void
    {
        $exists = EvaluationQuestion::where('category', 'identitas')
            ->where(function ($q) {
                $q->where('question_text', 'like', '%whatsapp%')
                  ->orWhere('question_text', 'like', '%kontak%')
                  ->orWhere('question_text', 'like', '%telepon%');
            })
            ->exists();

        if (!$exists) {
            EvaluationQuestion::firstOrCreate(
                [
                    'category' => 'identitas',
                    'question_text' => 'Nomor WhatsApp / Kontak yang Dapat Dihubungi',
                ],
                [
                    'media_type_id' => null,
                    'weight' => 0,
                    'is_mandatory' => true,
                ]
            );
        }
    }
}
