<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Normaliza e valida filtros da listagem de pontuações.
 *
 * Parâmetros CANÔNICOS aceitos:
 * - valor: string (pode vir com separador BR)
 * - valor_min, valor_max: numeric
 * - dt_inicio, dt_fim: date (Y-m-d)
 * - premio_id, loja_id, cliente_id, profissional_id: int
 * - order_by: string (repo sanitiza e aplica whitelist: id, dt_referencia, valor, dt_cadastro, dt_edicao)
 * - order_dir: in:asc,desc
 * - per_page: 1..100
 *
 * Aliases compatíveis (mapeados para canônicos):
 * - dt_referencia      -> dt_inicio
 * - dt_referencia_fim  -> dt_fim
 * - id_concurso        -> premio_id
 * - id_loja            -> loja_id
 * - id_cliente         -> cliente_id
 * - id_profissional    -> profissional_id
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

            // Não restringimos order_by aqui; o repositório resolve coluna/dir com segurança.
            'order_by'         => ['sometimes', 'string'],
            'order_dir'        => ['sometimes', 'in:asc,desc'],

            'per_page'         => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page'             => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Mapeia aliases para canônicos e define defaults seguros.
     *
     * - Define order_by = dt_cadastro (fallback no repo também é dt_cadastro).
     * - Define order_dir = desc quando não vier.
     * - Garante per_page inteiro (default 10; limitado via rule).
     * - Normaliza faixa de valor (se min > max, troca).
     */
    protected function prepareForValidation(): void
    {
        // Datas (aliases -> canônicos)
        $dtInicio = $this->input('dt_inicio', $this->input('dt_referencia'));
        $dtFim    = $this->input('dt_fim', $this->input('dt_referencia_fim'));

        // IDs (aliases -> canônicos)
        $premioId       = $this->input('premio_id', $this->input('id_concurso'));
        $lojaId         = $this->input('loja_id', $this->input('id_loja'));
        $clienteId      = $this->input('cliente_id', $this->input('id_cliente'));
        $profissionalId = $this->input('profissional_id', $this->input('id_profissional'));

        // Paginação
        $perPage = (int) ($this->input('per_page', 10));

        // Ordenação (defaults; o repo ainda saneia/whitelista)
        $orderBy  = $this->input('order_by', 'dt_cadastro');
        $orderDir = $this->input('order_dir', 'desc');

        // Faixa de valor: se vier invertida, corrige
        $vMin = $this->input('valor_min');
        $vMax = $this->input('valor_max');
        if (is_numeric($vMin) && is_numeric($vMax) && (float)$vMin > (float)$vMax) {
            [$vMin, $vMax] = [$vMax, $vMin];
        }

        $this->merge([
            // datas
            'dt_inicio' => $dtInicio ?: null,
            'dt_fim'    => $dtFim ?: null,

            // ids
            'premio_id'       => $premioId ?: null,
            'loja_id'         => $lojaId ?: null,
            'cliente_id'      => $clienteId ?: null,
            'profissional_id' => $profissionalId ?: null,

            // paginação
            'per_page' => $perPage,

            // ordenação
            'order_by'  => $orderBy,
            'order_dir' => $orderDir,

            // faixa de valor sanitizada
            'valor_min' => $vMin !== '' ? $vMin : null,
            'valor_max' => $vMax !== '' ? $vMax : null,
        ]);
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'dt_fim.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            'dt_inicio' => 'data inicial',
            'dt_fim'    => 'data final',
            'valor_min' => 'valor mínimo',
            'valor_max' => 'valor máximo',
        ];
    }
}
