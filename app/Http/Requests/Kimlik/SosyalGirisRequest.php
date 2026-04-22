<?php

namespace App\Http\Requests\Kimlik;

use Illuminate\Foundation\Http\FormRequest;

class SosyalGirisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|in:apple,google',
            'token' => 'required|string',
            'istemci_tipi' => 'required|in:dating,extension,admin',
            'ad' => 'nullable|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'avatar_url' => 'nullable|url|max:2048',
        ];
    }
}
