<?php

namespace App\Http\Requests\Kimlik;

use Illuminate\Foundation\Http\FormRequest;

class SosyalKayitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'social_session' => 'required|string',
            'uygulama_versiyonu' => 'nullable|string|max:32',
            'ad' => 'required|string|max:255',
            'kullanici_adi' => 'required|string|max:255',
            'cinsiyet' => 'required|in:erkek,kadin,belirtmek_istemiyorum',
            'dogum_yili' => 'required|integer|min:1940|max:2010',
            'ulke' => 'nullable|string|max:100',
            'il' => 'nullable|string|max:100',
            'dosya' => 'nullable|image|max:5120',
            'device_fingerprint' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:20',
        ];
    }
}
