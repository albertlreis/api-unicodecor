<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmpreendimentoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idConstrutoras' => ['required', 'integer', 'exists:construtoras,idConstrutoras'],
            'nome'           => ['required', 'string', 'max:100'],
            'site'           => ['nullable', 'string', 'max:300', 'url'],
            'imagem'         => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'status'         => ['nullable', 'in:0,1'],
            'planta_titulo'    => ['nullable', 'string', 'max:190'],
            'planta_descricao' => ['nullable', 'string', 'max:2000'],
            'planta_dwg'       => ['nullable', 'file', 'mimes:dwg', 'max:20480'], // 20MB
        ];
    }

    /**
     * Regras condicionais: se enviar arquivo DWG, título é obrigatório.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $hasDwg = $this->file('planta_dwg') !== null;
            $titulo = (string) $this->input('planta_titulo', '');
            if ($hasDwg && trim($titulo) === '') {
                $v->errors()->add('planta_titulo', 'Informe o título da planta quando enviar o arquivo DWG.');
            }
        });
    }
}
