<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de Prêmio.
 * Regras do projeto:
 * - Título e Período obrigatórios
 * - Banner e Regulamento SEMPRE anexados (sem URL)
 * - Faixas com valor por faixa
 * - Status no cadastro é sempre ativo (controlado no controller)
 */
class PremioStoreRequest extends FormRequest
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

            // Anexos obrigatórios
            'banner_file'      => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'], // 10MB
            'regulamento_file' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],               // 20MB

            // Faixas
            'faixas'                      => ['array'],
            'faixas.*.id'                 => ['sometimes', 'integer', 'min:1'],
            'faixas.*.pontos_min'         => ['required', 'numeric', 'min:0'],
            'faixas.*.pontos_max'         => ['nullable', 'numeric', 'min:0'],
            'faixas.*.vl_viagem'          => ['required', 'numeric', 'min:0'],
            'faixas.*.acompanhante'       => ['required', 'in:0,1'],
            'faixas.*.descricao'          => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'dt_fim.after_or_equal' => 'A data de fim deve ser posterior ou igual à data de início.',
            'banner_file.required'  => 'Envie o banner (imagem).',
            'regulamento_file.required' => 'Anexe o regulamento (PDF).',
        ];
    }
}
