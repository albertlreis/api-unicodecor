<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateioPorProfissionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'colocacao' => $this->colocacao,
            'id_profissional' => $this->id_profissional,
            'nome' => $this->profissional_nome,
            'total_geral' => (float) $this->total_geral,
            'acompanhante' => (bool) $this->acompanhante,
            'nome_faixa' => $this->nome_faixa,
            'vl_viagem' => (float) $this->vl_viagem,
            'loja' => $this->loja,
            'id_loja' => $this->id_loja,
            'valor_vendido' => (float) $this->valor_vendido,
            'percentual' => (float) $this->percentual,
            'valor_a_pagar' => (float) $this->valor_a_pagar,
        ];
    }
}
