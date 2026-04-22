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
            'reklam_platformu' => 'required|string|max:50',
            'reklam_birim_kodu' => 'required|string|max:100',
        ];
    }
}
