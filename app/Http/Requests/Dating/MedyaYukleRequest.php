<?php

namespace App\Http\Requests\Dating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedyaYukleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mesaj_tipi' => ['required', 'in:foto,ses'],
            'dosya' => [
                'required',
                'file',
                Rule::when(
                    $this->input('mesaj_tipi') === 'foto',
                    ['mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
                    ['mimetypes:audio/m4a,audio/x-m4a,audio/aac,audio/mpeg,audio/wav,audio/x-wav', 'max:20480']
                ),
            ],
        ];
    }
}

