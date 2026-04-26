<?php

namespace App\Http\Requests\Odeme;

use Illuminate\Foundation\Http\FormRequest;

class ReklamOdulKaydetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reklam_platformu' => 'required|string|in:android,ios',
            'reklam_birim_kodu' => 'required|string|max:160',
            'olay_kodu' => 'required|string|max:100',
            'reklam_tipi' => 'nullable|string|in:rewarded',
        ];
    }
}
