<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource: Linha de rateio por profissional (detalhe por lojas).
 *
 * Espera que $this->resource (stdClass/array) possua os campos:
 * - colocacao         int
 * - id_profissional   int
 * - profissional_nome string
 * - total_geral       numeric
 * - acompanhante      bool|int
 * - nome_faixa        string|null
 * - vl_viagem         numeric|null
 * - loja              string
 * - id_loja           int
 * - valor_vendido     numeric
 * - percentual        numeric
 * - valor_a_pagar     numeric
 */
class RateioPorProfissionalResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @phpstan-return array{
     *   colocacao:int,
     *   id_profissional:int,
     *   nome:string,
     *   total_geral:float,
     *   acompanhante:bool,
     *   nome_faixa:string|null,
     *   vl_viagem:float,
     *   loja:string,
     *   id_loja:int,
     *   valor_vendido:float,
     *   percentual:float,
     *   valor_a_pagar:float
     * }
     *
     * @psalm-return array{
     *   colocacao:int,
     *   id_profissional:int,
     *   nome:string,
     *   total_geral:float,
     *   acompanhante:bool,
     *   nome_faixa:?string,
     *   vl_viagem:float,
     *   loja:string,
     *   id_loja:int,
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
            'colocacao'       => (int)   $this->colocacao,
            'id_profissional' => (int)   $this->id_profissional,
            'nome'            => (string)$this->profissional_nome,
            'total_geral'     => (float) $this->total_geral,
            'acompanhante'    => (bool)  $this->acompanhante,
            'nome_faixa'      => $this->nome_faixa !== null ? (string)$this->nome_faixa : null,
            'vl_viagem'       => (float) ($this->vl_viagem ?? 0),
            'loja'            => (string)$this->loja,
            'id_loja'         => (int)   $this->id_loja,
            'valor_vendido'   => (int) $this->valor_vendido,
            'percentual'      => (float) $this->percentual,
            'valor_a_pagar'   => (float) $this->valor_a_pagar,
        ];
    }
}
