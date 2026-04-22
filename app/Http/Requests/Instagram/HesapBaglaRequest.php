<?php

namespace App\Http\Requests\Instagram;

use Illuminate\Foundation\Http\FormRequest;

class HesapBaglaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instagram_kullanici_adi' => 'required|string|max:255',
            'instagram_profil_id' => 'nullable|string|max:255',
        ];
    }
}
