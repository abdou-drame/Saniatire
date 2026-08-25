<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:100', 'unique:lab_samples,barcode'],
            'sample_type' => ['required', 'string', 'max:100'],
            'collected_at' => ['nullable', 'date'],
        ];
    }
}
