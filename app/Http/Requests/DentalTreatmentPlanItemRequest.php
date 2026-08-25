<?php

namespace App\Http\Requests;

use App\Domain\Dentaire\Support\FdiNumbering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DentalTreatmentPlanItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'tooth_fdi' => ['nullable', Rule::in(FdiNumbering::validCodes())],
            'act_type' => [$sometimesOnUpdate, 'string', 'max:255'],
            'planned_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['prevu', 'realise', 'annule'])],
        ];
    }
}
