<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EquipmentMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'biomedical_equipment_id' => [$sometimesOnUpdate, 'integer', 'exists:biomedical_equipment,id'],
            'type' => [$sometimesOnUpdate, 'in:preventive,corrective'],
            'date_prevue' => [$sometimesOnUpdate, 'date'],
            'date_realisee' => ['nullable', 'date'],
            'intervenant_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'intervenant_externe' => ['nullable', 'string', 'max:255'],
            'cout' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'statut' => ['sometimes', 'in:planifiee,realisee,annulee'],
        ];
    }
}
