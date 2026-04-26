<?php

namespace App\Http\Requests\Dating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfilGuncelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ad' => 'sometimes|string|max:255',
            'soyad' => 'sometimes|nullable|string|max:255',
            'biyografi' => 'sometimes|nullable|string|max:1000',
            'dogum_yili' => 'sometimes|integer|min:1940|max:2010',
            'cinsiyet' => 'sometimes|in:erkek,kadin,belirtmek_istemiyorum',
            'ulke' => 'sometimes|nullable|string|max:100',
            'il' => 'sometimes|nullable|string|max:100',
            'ilce' => 'sometimes|nullable|string|max:100',
            'gorunum_modu' => 'sometimes|in:acik,koyu,sistem',
            'ses_acik_mi' => 'sometimes|boolean',
            'bildirimler_acik_mi' => 'sometimes|boolean',
            'titresim_acik_mi' => 'sometimes|boolean',
            'dil' => [
                'sometimes',
                'nullable',
                'string',
                'max:12',
                Rule::exists('app_languages', 'code')->where('is_active', true),
            ],
        ];
    }
}
