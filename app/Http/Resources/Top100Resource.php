<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource do card Top100.
 *
 * Aceita array ou objeto como resource.
 *
 * @property-read mixed $resource
 */
class Top100Resource extends JsonResource
{
    /**
     * @inheritDoc
     * @return array{
     *   colocacao:int|null,
     *   pontuacao_total:string,
     *   data_fim_campanha:string,
     *   dias_restantes:int,
     *   dt_inicio_iso?:string,
     *   dt_fim_iso?:string,
     *   periodo_label?:string
     * }
     */
    public function toArray($request): array
    {
        // Usa data_get para funcionar com array ou objeto
        return [
            'colocacao'         => data_get($this->resource, 'colocacao'),
            'pontuacao_total'   => (string) data_get($this->resource, 'pontuacao_total', '0,00'),
            'data_fim_campanha' => (string) data_get($this->resource, 'data_fim_campanha', ''),
            'dias_restantes'    => (int) data_get($this->resource, 'dias_restantes', 0),
            'dt_inicio_iso'     => data_get($this->resource, 'dt_inicio_iso'),
            'dt_fim_iso'        => data_get($this->resource, 'dt_fim_iso'),
            'periodo_label'     => data_get($this->resource, 'periodo_label'),
        ];
    }
}
