<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PremioFaixaResource extends JsonResource
{
    /**
     * Transforma o recurso em um array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'id_premio'      => $this->id_premio,
            'pontos_min'     => $this->pontos_min,
            'pontos_max'     => $this->pontos_max,
            'range'          => $this->pontos_range_formatado,
            'acompanhante'   => $this->acompanhante,
            'acompanhante_label' => $this->acompanhante_label,
            'descricao'      => $this->descricao,
            'valor'          => $this->vl_viagem,
            'valor_formatado' => $this->valor_viagem_formatado,
        ];
    }
}
