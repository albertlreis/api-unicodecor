<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource: Linha de rateio por loja (detalhe por profissionais).
 *
 * Espera que $this->resource (stdClass/array) possua:
 * - id_loja           int
 * - nome_loja         string
 * - id_profissional   int
 * - profissional_nome string
 * - colocacao         int
 * - total_geral       numeric
 * - nome_faixa        string|null
 * - vl_viagem         numeric|null
 * - valor_vendido     numeric
 * - percentual        numeric
 * - valor_a_pagar     numeric
 */
class RateioPorLojaResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @phpstan-return array{
     *   id_loja:int,
     *   nome_loja:string,
     *   id_profissional:int,
     *   profissional_nome:string,
     *   colocacao:int,
     *   total_geral:float,
     *   nome_faixa:string|null,
     *   vl_viagem:float,
     *   valor_vendido:float,
     *   percentual:float,
     *   valor_a_pagar:float
     * }
     *
     * @psalm-return array{
     *   id_loja:int,
     *   nome_loja:string,
     *   id_profissional:int,
     *   profissional_nome:string,
     *   colocacao:int,
     *   total_geral:float,
     *   nome_faixa:?string,
     *   vl_viagem:float,
     *   valor_vendido:float,
     *   percentual:float,
     *   valor_a_pagar:float
     * }
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id_loja'           => (int)   $this->id_loja,
            'nome_loja'         => (string)$this->nome_loja,
            'id_profissional'   => (int)   $this->id_profissional,
            'profissional_nome' => (string)$this->profissional_nome,
            'colocacao'         => (int)   $this->colocacao,
            'total_geral'       => (float) $this->total_geral,
            'nome_faixa'        => $this->nome_faixa !== null ? (string)$this->nome_faixa : null,
            'vl_viagem'         => (float) ($this->vl_viagem ?? 0),
            'valor_vendido'     => (int) $this->valor_vendido,
            'percentual'        => (float) $this->percentual,
            'valor_a_pagar'     => (float) $this->valor_a_pagar,
        ];
    }
}
