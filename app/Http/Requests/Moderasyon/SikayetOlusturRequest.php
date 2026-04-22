<?php

namespace App\Http\Requests\Moderasyon;

use Illuminate\Foundation\Http\FormRequest;

class SikayetOlusturRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hedef_tipi' => 'required|in:user,mesaj',
            'hedef_id' => 'required|integer',
            'kategori' => 'required|string|max:100',
            'aciklama' => 'nullable|string|max:1000',
        ];
    }
}
