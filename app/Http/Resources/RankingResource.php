<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RankingResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_profissional' => $this->id_profissional,
            'pontuacao' => $this->pontuacao,
            'nome_profissional' => $this->nome_profissional
        ];
    }
}
