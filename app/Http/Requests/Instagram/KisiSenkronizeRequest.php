<?php

namespace App\Http\Requests\Instagram;

use Illuminate\Foundation\Http\FormRequest;

class KisiSenkronizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kisiler' => 'required|array',
            'kisiler.*.instagram_kisi_id' => 'required|string',
            'kisiler.*.kullanici_adi' => 'nullable|string|max:255',
            'kisiler.*.gorunen_ad' => 'nullable|string|max:255',
            'kisiler.*.profil_resmi' => 'nullable|string|max:500',
        ];
    }
}
