<?php

namespace App\Http\Requests\Yonetim;

use App\Models\Sikayet;
use Illuminate\Foundation\Http\FormRequest;

class SikayetYonetimGuncelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'durum' => 'required|in:' . implode(',', [
                Sikayet::DURUM_BEKLIYOR,
                Sikayet::DURUM_INCELENIYOR,
                Sikayet::DURUM_COZULDU,
                Sikayet::DURUM_REDDEDILDI,
            ]),
            'yonetici_notu' => 'nullable|string|max:2000',
        ];
    }
}
