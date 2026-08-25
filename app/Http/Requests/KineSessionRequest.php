<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KineSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date'],
            'exercises_performed' => ['nullable', 'string'],
            'evolution' => ['nullable', 'string'],
            'pain_scale' => ['nullable', 'integer', 'min:0', 'max:10'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
