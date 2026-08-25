<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HomeCareVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'care_type' => ['required', 'string', 'max:255'],
            'visit_datetime' => ['required', 'date'],
            'report' => ['nullable', 'string'],
        ];
    }
}
