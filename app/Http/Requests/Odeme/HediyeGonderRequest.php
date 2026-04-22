<?php

namespace App\Http\Requests\Odeme;

use Illuminate\Foundation\Http\FormRequest;

class HediyeGonderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alici_user_id' => 'required|exists:users,id',
            'hediye_tipi' => 'required|string|max:50',
            'puan_degeri' => 'required|integer|min:1|max:10000',
            'mesaj' => 'nullable|string|max:500',
        ];
    }
}
