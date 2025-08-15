<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de Construtora.
 */
class StoreConstrutoraRequest extends FormRequest
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
            'razao_social' => ['required', 'string', 'max:150'],
            'cnpj'         => ['nullable', 'string', 'max:50'],
            'imagem'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'status'       => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'razao_social' => 'razão social',
            'cnpj'         => 'CNPJ',
            'imagem'       => 'imagem',
        ];
    }
}
