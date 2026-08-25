<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "status" is deliberately absent from these rules: the procedure's status
 * only ever moves through SurgicalProcedureController::start/complete/
 * cancel, never through a generic store/update payload.
 */
class SurgicalProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'hospitalization_id' => ['nullable', 'integer', 'exists:hospitalizations,id'],
            'surgeon_id' => ['required', 'integer', 'exists:users,id'],
            'anesthesiologist_id' => ['required', 'integer', 'exists:users,id'],
            'operating_room' => ['required', 'string', 'max:255'],
            'procedure_type' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
        ];
    }
}
