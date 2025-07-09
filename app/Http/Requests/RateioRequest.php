<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modo' => 'required|in:profissional,loja,consolidado',
            'id_premio' => 'required_if:modo,profissional,loja|integer|nullable',
            'ids_premios' => 'required_if:modo,consolidado|array|nullable',
            'id_profissional' => 'nullable|integer',
            'id_loja' => 'nullable|integer',
        ];
    }
}
