<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource: Consolidação de rateio por loja (vários prêmios), com detalhamento de profissionais.
 *
 * Espera:
 * - id_loja             int
 * - nome_loja           string
 * - total_profissionais int
 * - valor_total         numeric
 * - profissionais       array<object> (shape do RateioPorLojaResource)
 */
class RateioConsolidadoResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     *
     * @phpstan-return array{
     *  id_loja:int,
     *  nome_loja:string,
     *  total_profissionais:int,
     *  valor_total:float,
     *  profissionais: array<array{
     *      id_loja:int,
     *      nome_loja:string,
     *      id_profissional:int,
     *      profissional_nome:string,
     *      colocacao:int|null,
     *      total_geral:float,
     *      nome_faixa:?string,
     *      vl_viagem:float,
     *      valor_vendido:float,
     *      percentual:float,
     *      valor_a_pagar:float
     *  }>
     * }
     */
    public function toArray($request): array
    {
        return [
            'id_loja'             => (int)   $this['id_loja'],
            'nome_loja'           => (string)$this['nome_loja'],
            'total_profissionais' => (int)   $this['total_profissionais'],
            'valor_total'         => (float) $this['valor_total'],
            'profissionais'       => RateioPorLojaResource::collection(collect($this['profissionais']))->resolve(),
        ];
    }
}
