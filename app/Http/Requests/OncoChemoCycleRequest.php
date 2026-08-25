<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OncoChemoCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cycle_number' => ['required', 'integer', 'min:1', 'max:200'],
            'cycle_date' => ['required', 'date'],
            'medications' => ['nullable', 'string'],
            'side_effects' => ['nullable', 'string'],
        ];
    }
}
