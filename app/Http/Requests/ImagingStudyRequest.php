<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImagingStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'study_instance_uid' => [$sometimesOnUpdate, 'string', 'max:255', 'unique:imaging_studies,study_instance_uid'],
            'accession_number' => [$sometimesOnUpdate, 'string', 'max:255', 'unique:imaging_studies,accession_number'],
            'modality' => [$sometimesOnUpdate, 'string', 'max:50'],
            'performed_at' => ['nullable', 'date'],
            'external_reference_url' => ['nullable', 'string', 'max:2048'],
            'storage_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
