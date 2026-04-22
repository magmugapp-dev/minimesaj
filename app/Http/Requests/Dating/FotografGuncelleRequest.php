<?php

namespace App\Http\Requests\Dating;

use Illuminate\Foundation\Http\FormRequest;

class FotografGuncelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ana_fotograf_mi' => 'sometimes|boolean',
            'sira_no' => 'sometimes|integer|min:0|max:99',
        ];
    }
}
