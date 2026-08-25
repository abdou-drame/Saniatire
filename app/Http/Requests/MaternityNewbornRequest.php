<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaternityNewbornRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sex' => ['required', Rule::in(['m', 'f'])],
            'birth_weight_grams' => ['required', 'integer', 'min:100', 'max:7000'],
            'apgar_1min' => ['required', 'integer', 'min:0', 'max:10'],
            'apgar_5min' => ['required', 'integer', 'min:0', 'max:10'],
            'apgar_10min' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }
}
