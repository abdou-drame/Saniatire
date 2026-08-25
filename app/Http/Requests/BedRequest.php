<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BedRequest extends FormRequest
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
            'ward_id' => [$sometimesOnUpdate, 'integer', 'exists:wards,id'],
            'room_number' => [$sometimesOnUpdate, 'string', 'max:50'],
            'bed_label' => [$sometimesOnUpdate, 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['libre', 'occupe', 'reserve', 'entretien', 'indisponible'])],
        ];
    }
}
