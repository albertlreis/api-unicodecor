<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RankingResource extends JsonResource
{
    /**
     * Converte um valor numérico (float|string|null) para inteiro de pontos.
     *
     * @param  mixed $value
     * @return int
     */
    private function toIntPoints(mixed $value): int
    {
        return (int) round((float) ($value ?? 0), 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Transforma o recurso em array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_profissional'   => $this->id_profissional,
            'pontuacao'         => $this->toIntPoints($this->pontuacao),
            'nome_profissional' => $this->nome_profissional,
            'faixa'             => $this->faixa ?? null,
        ];
    }
}
