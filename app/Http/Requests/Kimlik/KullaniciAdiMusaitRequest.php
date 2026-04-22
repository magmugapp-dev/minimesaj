<?php

namespace App\Http\Requests\Kimlik;

use Illuminate\Foundation\Http\FormRequest;

class KullaniciAdiMusaitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kullanici_adi' => 'required|string|max:255',
        ];
    }
}
