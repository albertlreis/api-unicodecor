<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateioConsolidadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_loja' => $this->id_loja,
            'nome_loja' => $this->nome_loja,
            'total_profissionais' => $this->total_profissionais,
            'valor_total' => (float) $this->valor_total,
        ];
    }
}
