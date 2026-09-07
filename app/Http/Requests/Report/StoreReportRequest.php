<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'media_type_id' => 'required|exists:media_types,id',
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required_with:answers|exists:evaluation_questions,id',
            'answers.*.answer_value' => 'nullable|string',
            'answers.*.answer_type' => 'nullable|in:text,file,url',
            'link_url' => 'nullable|url',
            'submit' => 'nullable|boolean',
        ];
    }
}
