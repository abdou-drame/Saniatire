<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PmaCycleMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monitoring_date' => ['required', 'date'],
            'echo_observations' => ['nullable', 'string'],
            'hormone_level' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'puncture_date' => ['nullable', 'date'],
            'transfer_date' => ['nullable', 'date'],
        ];
    }
}
