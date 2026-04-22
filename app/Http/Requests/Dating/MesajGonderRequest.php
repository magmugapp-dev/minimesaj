<?php

namespace App\Http\Requests\Dating;

use Illuminate\Foundation\Http\FormRequest;

class MesajGonderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mesaj_tipi' => 'sometimes|in:metin,ses,foto,sistem',
            'mesaj_metni' => 'required_if:mesaj_tipi,metin|nullable|string|max:5000',
            'dosya_yolu' => 'nullable|string',
            'dosya_suresi' => 'nullable|integer',
            'dosya_boyutu' => 'nullable|integer',
            'cevaplanan_mesaj_id' => 'nullable|exists:mesajlar,id',
        ];
    }
}
