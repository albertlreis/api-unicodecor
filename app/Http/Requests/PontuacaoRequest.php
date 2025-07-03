<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PontuacaoRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->id_perfil === 3;
    }

    public function rules()
    {
        return [
            'valor' => 'required|numeric|min:0.01',
            'dt_referencia' => 'required|date',
            'id_profissional' => 'required|exists:usuario,id',
            'id_cliente' => 'required|exists:usuario,id',
            'orcamento' => 'nullable|string|max:255',
        ];
    }
}
