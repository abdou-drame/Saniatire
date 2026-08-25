<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CardioEcgResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'performed_at' => ['required', 'date'],
            'rhythm' => ['required', 'string', 'max:100'],
            'heart_rate' => ['nullable', 'integer', 'min:20', 'max:300'],
            'anomalies' => ['nullable', 'string'],
            'tracing_file_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
