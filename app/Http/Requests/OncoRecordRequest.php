<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OncoRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'site_id' => [$sometimesOnUpdate, 'integer', 'exists:sites,id'],
            'patient_id' => [$sometimesOnUpdate, 'integer', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'cancer_type' => [$sometimesOnUpdate, 'string', 'max:255'],
            'stage_t' => ['nullable', Rule::in(['Tis', 'T0', 'T1', 'T2', 'T3', 'T4'])],
            'stage_n' => ['nullable', Rule::in(['N0', 'N1', 'N2', 'N3'])],
            'stage_m' => ['nullable', Rule::in(['M0', 'M1'])],
            'protocol_name' => ['nullable', 'string', 'max:255'],
            'treatment_line' => ['nullable', 'integer', 'min:1', 'max:20'],
            'diagnosed_at' => ['nullable', 'date'],
        ];
    }
}
