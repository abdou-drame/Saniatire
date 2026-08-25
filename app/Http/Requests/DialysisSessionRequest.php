<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DialysisSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date'],
            'pre_weight_kg' => ['required', 'numeric', 'min:0', 'max:300'],
            'post_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'dry_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'blood_flow_rate_ml_min' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'ultrafiltration_volume_ml' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'complications' => ['nullable', 'string'],
        ];
    }
}
