<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,proses,disetujui',
        ];
    }
}
