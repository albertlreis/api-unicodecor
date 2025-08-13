<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Normaliza e valida filtros da listagem de pontuações.
 *
 * Parâmetros CANÔNICOS aceitos:
 * - valor: string (pode vir com separador BR)
 * - valor_min, valor_max: numeric
 * - dt_inicio, dt_fim: YYYY-MM-DD
 * - premio_id, loja_id, cliente_id, profissional_id: int
 * - order_by: in:dt_referencia,valor,id
 * - order_dir: in:asc,desc
 * - per_page: 1..100
 *
 * Aliases compatíveis (serão mapeados para os canônicos):
 * - dt_referencia   -> dt_inicio
 * - dt_referencia_fim -> dt_fim
 * - id_concurso     -> premio_id
 * - id_loja         -> loja_id
 * - id_cliente      -> cliente_id
 * - id_profissional -> profissional_id
 */
class PontuacaoIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'valor'            => ['nullable', 'string', 'max:30'],
            'valor_min'        => ['nullable', 'numeric'],
            'valor_max'        => ['nullable', 'numeric'],

            'dt_inicio'        => ['nullable', 'date_format:Y-m-d'],
            'dt_fim'           => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dt_inicio'],

            'premio_id'        => ['nullable', 'integer', 'min:1'],
            'loja_id'          => ['nullable', 'integer', 'min:1'],
            'cliente_id'       => ['nullable', 'integer', 'min:1'],
            'profissional_id'  => ['nullable', 'integer', 'min:1'],

            'order_by'         => ['nullable', 'in:dt_referencia,valor,id'],
            'order_dir'        => ['nullable', 'in:asc,desc'],

            'per_page'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'             => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Mapeia aliases para os nomes CANÔNICOS.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // datas
            'dt_inicio' => $this->input('dt_inicio', $this->input('dt_referencia')),
            'dt_fim'    => $this->input('dt_fim', $this->input('dt_referencia_fim')),

            // ids
            'premio_id'       => $this->input('premio_id', $this->input('id_concurso')),
            'loja_id'         => $this->input('loja_id', $this->input('id_loja')),
            'cliente_id'      => $this->input('cliente_id', $this->input('id_cliente')),
            'profissional_id' => $this->input('profissional_id', $this->input('id_profissional')),

            // paginação
            'per_page' => (int) $this->input('per_page', 10),
        ]);
    }
}
