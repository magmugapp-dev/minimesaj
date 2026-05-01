<?php

namespace App\Http\Requests\Kimlik;

use Illuminate\Foundation\Http\FormRequest;

class KayitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ad' => 'required|string|max:255',
            'soyad' => 'nullable|string|max:255',
            'kullanici_adi' => 'required|string|max:255|unique:users,kullanici_adi',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'cinsiyet' => 'nullable|in:erkek,kadin,belirtmek_istemiyorum',
            'dogum_yili' => 'nullable|integer|min:1940|max:2010',
            'ulke' => 'nullable|string|max:100',
            'il' => 'nullable|string|max:100',
            'istemci_tipi' => 'required|in:dating,extension',
            'device_fingerprint' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:20',
        ];
    }
}
