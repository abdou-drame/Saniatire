<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canaux' => ['required', 'array', 'min:1'],
            'canaux.*' => ['in:email,sms,whatsapp,push'],
        ];
    }
}
