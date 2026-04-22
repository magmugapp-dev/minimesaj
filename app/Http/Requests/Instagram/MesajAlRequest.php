<?php

namespace App\Http\Requests\Instagram;

use Illuminate\Foundation\Http\FormRequest;

class MesajAlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mesajlar' => 'required|array',
            'mesajlar.*.instagram_kisi_id' => 'required|string|max:500',
            'mesajlar.*.gonderen_tipi' => 'required|in:biz,karsi_taraf',
            'mesajlar.*.mesaj_metni' => 'required|string|max:5000',
            'mesajlar.*.mesaj_tipi' => 'sometimes|in:metin,ses,foto',
            'mesajlar.*.instagram_mesaj_kodu' => 'nullable|string|max:255',
        ];
    }
}
