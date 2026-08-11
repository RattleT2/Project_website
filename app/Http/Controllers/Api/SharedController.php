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
        return response()->json(
            EvaluationQuestion::with('scoringRules')
                ->whereNull('media_type_id')
                ->get()
        );
    }

    public function questionsByMediaType(int $mediaTypeId): JsonResponse
    {
        $questions = EvaluationQuestion::with('scoringRules')
            ->where(function ($query) use ($mediaTypeId) {
                $query->whereNull('media_type_id')
                    ->orWhere('media_type_id', $mediaTypeId);
            })
            ->get();

        return response()->json($questions);
    }
}
