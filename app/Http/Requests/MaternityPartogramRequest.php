<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaternityPartogramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'labor_started_at' => ['required', 'date'],
        ];
    }
}
