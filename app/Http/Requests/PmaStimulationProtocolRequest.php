<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PmaStimulationProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol_type' => ['required', 'string', 'max:255'],
            'medications' => ['nullable', 'string'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
