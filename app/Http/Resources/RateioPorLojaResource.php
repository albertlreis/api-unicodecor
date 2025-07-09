<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RateioPorLojaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_loja' => $this->id_loja,
            'nome_loja' => $this->nome_loja,
            'id_profissional' => $this->id_profissional,
            'profissional_nome' => $this->profissional_nome,
            'colocacao' => $this->colocacao,
            'total_geral' => (float) $this->total_geral,
            'nome_faixa' => $this->nome_faixa,
            'vl_viagem' => (float) $this->vl_viagem,
            'valor_vendido' => (float) $this->valor_vendido,
            'percentual' => (float) $this->percentual,
            'valor_a_pagar' => (float) $this->valor_a_pagar,
        ];
    }
}
