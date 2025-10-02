<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualizar Empreendimento.
 */
class EmpreendimentoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idConstrutoras' => ['sometimes', 'required', 'integer', 'exists:construtoras,idConstrutoras'],
            'nome'           => ['sometimes', 'required', 'string', 'max:100'],
            'site'           => ['sometimes', 'nullable', 'string', 'max:300', 'url'],
            'imagem'         => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'status'         => ['sometimes', 'required', 'in:0,1'],
            'planta_titulo'    => ['nullable','string','max:190'],
            'planta_descricao' => ['nullable','string','max:2000'],
            'planta_dwg'       => ['nullable','file','mimes:dwg','max:20480'],

        ];
    }
}
