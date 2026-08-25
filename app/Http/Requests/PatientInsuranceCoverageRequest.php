<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientInsuranceCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'patient_id' => [$sometimesOnUpdate, 'integer', 'exists:patients,id'],
            'insurance_convention_id' => [$sometimesOnUpdate, 'integer', 'exists:insurance_conventions,id'],
            'numero_adherent' => [$sometimesOnUpdate, 'string', 'max:255'],
            'beneficiaire_type' => [$sometimesOnUpdate, 'in:assure_principal,ayant_droit'],
            'date_debut' => [$sometimesOnUpdate, 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
