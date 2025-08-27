<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource: Consolidação de rateio por loja (vários prêmios).
 *
 * Espera que $this->resource (stdClass/array) possua:
 * - id_loja             int
 * - nome_loja           string
 * - total_profissionais int
 * - valor_total         numeric
 */
class RateioConsolidadoResource extends JsonResource
{
    /**
     * @param  Request  $request
     *
     * @phpstan-return array{
     *   id_loja:int,
     *   nome_loja:string,
     *   total_profissionais:int,
     *   valor_total:float
     * }
     *
     * @psalm-return array{
     *   id_loja:int,
     *   nome_loja:string,
     *   total_profissionais:int,
     *   valor_total:float
     * }
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id_loja'             => (int)   $this->id_loja,
            'nome_loja'           => (string)$this->nome_loja,
            'total_profissionais' => (int)   $this->total_profissionais,
            'valor_total'         => (float) $this->valor_total,
        ];
    }
}
