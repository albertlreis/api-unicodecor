<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização de Prêmio.
 * - Banner/regulamento são opcionais (troca se enviados)
 * - Mantém as mesmas regras de título/período/faixas do cadastro
 */
class PremioUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo'      => ['required', 'string', 'max:255'],
            'descricao'   => ['nullable', 'string'],
            'regras'      => ['nullable', 'string'],
            'dt_inicio'   => ['required', 'date_format:Y-m-d'],
            'dt_fim'      => ['required', 'date_format:Y-m-d', 'after_or_equal:dt_inicio'],

            // Anexos opcionais na edição
            'banner_file'      => ['sometimes', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'regulamento_file' => ['sometimes', 'file', 'mimetypes:application/pdf', 'max:20480'],

            // Faixas
            'faixas'                      => ['array'],
            'faixas.*.id'                 => ['sometimes', 'integer', 'min:1'],
            'faixas.*.pontos_min'         => ['required', 'integer', 'min:0'],
            'faixas.*.pontos_max'         => ['nullable', 'integer', 'min:0'],
            'faixas.*.vl_viagem'          => ['required', 'numeric', 'min:0'],
            'faixas.*.acompanhante'       => ['required', 'in:0,1'],
            'faixas.*.descricao'          => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'dt_fim.after_or_equal' => 'A data de fim deve ser posterior ou igual à data de início.',
        ];
    }
}
