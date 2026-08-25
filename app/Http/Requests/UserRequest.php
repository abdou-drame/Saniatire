<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreate = $this->isMethod('POST');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'is_active' => ['boolean'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer', Rule::exists('sites', 'id')],
        ];
    }
}
