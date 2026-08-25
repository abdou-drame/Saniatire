<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaternityDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['voie_basse', 'cesarienne'])],
            'delivered_at' => ['required', 'date'],
            'complications' => ['nullable', 'string'],
        ];
    }
}
