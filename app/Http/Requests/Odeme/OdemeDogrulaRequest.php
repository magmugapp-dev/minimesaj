<?php

namespace App\Http\Requests\Odeme;

use Illuminate\Foundation\Http\FormRequest;

class OdemeDogrulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => 'required|in:ios,android',
            'fis_verisi' => 'required|string|max:4096',
            'urun_kodu' => 'required|string|max:100',
            'urun_tipi' => 'nullable|in:tek_seferlik,abonelik',
            'tutar' => 'required|numeric|min:0',
            'para_birimi' => 'required|string|size:3',
        ];
    }
}
