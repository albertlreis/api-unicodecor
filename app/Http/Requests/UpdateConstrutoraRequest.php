<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização de Construtora.
 */
class UpdateConstrutoraRequest extends FormRequest
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
            'razao_social' => ['sometimes', 'required', 'string', 'max:150'],
            'cnpj'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'imagem'       => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'status'       => ['sometimes', 'required', 'integer', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'razao_social' => 'razão social',
            'cnpj'         => 'CNPJ',
            'imagem'       => 'imagem',
        ];
    }
}
