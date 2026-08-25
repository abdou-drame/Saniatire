<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultationDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'icd_code_id' => ['required', 'integer', 'exists:icd_codes,id'],
            'type' => ['required', Rule::in(['principal', 'secondaire'])],
            'status' => ['sometimes', Rule::in(['provisoire', 'confirme'])],
        ];
    }
}
