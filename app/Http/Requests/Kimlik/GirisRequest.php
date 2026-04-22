<?php

namespace App\Http\Requests\Kimlik;

use Illuminate\Foundation\Http\FormRequest;

class GirisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kullanici_adi' => 'required|string',
            'password' => 'required|string',
            'istemci_tipi' => 'required|in:dating,extension,admin',
        ];
    }
}
