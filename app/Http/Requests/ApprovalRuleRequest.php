<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'level' => [$sometimesOnUpdate, 'integer', 'min:1'],
            'min_amount' => [$sometimesOnUpdate, 'numeric', 'min:0'],
            'role_name' => [$sometimesOnUpdate, 'string', 'exists:roles,name'],
        ];
    }
}
