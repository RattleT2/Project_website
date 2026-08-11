<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'media_type_id' => 'sometimes|exists:media_types,id',
            'answers' => 'sometimes|array',
            'answers.*.question_id' => 'required_with:answers|exists:evaluation_questions,id',
            'answers.*.answer_value' => 'required_with:answers|string',
            'answers.*.answer_type' => 'required_with:answers|in:text,file,url',
            'link_url' => 'nullable|url',
            'submit' => 'nullable|boolean',
        ];
    }
}
