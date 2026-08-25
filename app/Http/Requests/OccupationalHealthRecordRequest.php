<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OccupationalHealthRecordRequest extends FormRequest
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
            'visit_type' => [$sometimesOnUpdate, Rule::in(['embauche', 'periodique', 'reprise', 'demande'])],
            'fitness_status' => [$sometimesOnUpdate, Rule::in(['apte', 'apte_avec_reserves', 'inapte'])],
            // An "apte avec réserves" verdict without a description of what
            // those reserves are is a data-quality issue we reject outright
            // rather than allowing to be silently recorded.
            'restrictions' => [Rule::requiredIf(fn () => $this->input('fitness_status') === 'apte_avec_reserves'), 'nullable', 'string'],
            'risk_exposures' => ['nullable', 'array'],
            'risk_exposures.*' => ['string', 'max:100'],
            'visit_date' => [$sometimesOnUpdate, 'date'],
            'next_visit_due_at' => ['nullable', 'date'],
        ];
    }
}
