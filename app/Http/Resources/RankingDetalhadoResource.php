<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RankingDetalhadoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_profissional' => $this['id_profissional'],
            'nome' => $this['nome'],
            'total' => number_format($this['total'], 2, ',', '.'),
            'pontos' => collect($this['pontos'])->map(function ($item) {
                return [
                    'loja' => $item['loja'],
                    'total' => number_format($item['total'], 2, ',', '.')
                ];
            }),
        ];
    }
}
