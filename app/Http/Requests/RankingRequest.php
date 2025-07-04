<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RankingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_premio' => ['required', 'integer', 'exists:premios,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_premio.required' => 'O parâmetro id_premio é obrigatório.',
            'id_premio.exists' => 'O prêmio informado não existe.',
        ];
    }
}
