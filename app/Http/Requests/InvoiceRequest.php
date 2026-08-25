<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'billable_item_ids' => ['required', 'array', 'min:1'],
            'billable_item_ids.*' => ['integer', 'exists:billable_items,id'],
        ];
    }
}
