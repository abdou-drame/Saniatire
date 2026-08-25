<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HospitalizationDailyNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note_date' => ['nullable', 'date'],
            'care_administered' => ['nullable', 'string'],
            'medications_given' => ['nullable', 'string'],
            'procedures_performed' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
