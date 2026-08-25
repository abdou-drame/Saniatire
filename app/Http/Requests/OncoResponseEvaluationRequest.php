<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OncoResponseEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'evaluated_at' => ['required', 'date'],
            'response' => ['required', Rule::in(['reponse_complete', 'reponse_partielle', 'stable', 'progression'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
