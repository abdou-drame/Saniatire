<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'type_evenement' => [$sometimesOnUpdate, 'string', 'max:100'],
            'canal' => [$sometimesOnUpdate, 'in:email,sms,whatsapp,push'],
            'sujet' => ['nullable', 'string', 'max:255'],
            'contenu' => [$sometimesOnUpdate, 'string'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
