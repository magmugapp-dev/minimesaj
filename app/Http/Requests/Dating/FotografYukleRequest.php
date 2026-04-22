<?php

namespace App\Http\Requests\Dating;

use Illuminate\Foundation\Http\FormRequest;

class FotografYukleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dosya' => 'required|image|max:5120',
            'ana_fotograf_mi' => 'sometimes|boolean',
        ];
    }
}
