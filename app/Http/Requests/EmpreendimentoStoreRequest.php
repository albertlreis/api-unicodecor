<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criar Empreendimento.
 */
class EmpreendimentoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sanctum/Policies podem filtrar depois se necessário.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idConstrutoras' => ['required', 'integer', 'exists:construtoras,idConstrutoras'],
            'nome'           => ['required', 'string', 'max:100'],
            'site'           => ['nullable', 'string', 'max:300', 'url'],
            'imagem'         => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // 5 MB
            'status'         => ['nullable', 'in:0,1'], // criação aceita 0|1; exclusão lógica é via destroy
        ];
    }
}
