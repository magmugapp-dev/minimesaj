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
            'client_upload_id' => ['nullable', 'string', 'max:96'],
            'dosya' => [
                'required',
                'file',
                Rule::when(
                    $this->input('mesaj_tipi') === 'foto',
                    ['mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
                    [
                        'mimetypes:audio/mp4,audio/m4a,audio/x-m4a,audio/aac,audio/aacp,audio/mpeg,audio/mp3,audio/wav,audio/x-wav,video/mp4,application/mp4,application/octet-stream',
                        'max:20480',
                    ]
                ),
            ],
        ];
    }
}
