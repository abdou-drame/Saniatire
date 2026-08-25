<?php

namespace App\Http\Requests;

use App\Domain\Dentaire\Support\FdiNumbering;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DentalProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tooth_fdi' => ['nullable', Rule::in(FdiNumbering::validCodes())],
            'act_type' => ['required', 'string', 'max:255'],
            'performed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
