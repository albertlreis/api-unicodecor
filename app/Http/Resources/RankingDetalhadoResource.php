<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id_profissional
 * @property string $nome
 * @property float $total
 * @property array $pontos
 */
class RankingDetalhadoResource extends JsonResource
{
    /**
     * Transforma o recurso em um array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_profissional' => $this['id_profissional'],
            'nome' => $this['nome'],
            'total' => number_format(floatval($this['total']), 2, ',', '.'),
            'pontos' => collect($this['pontos'])->map(function ($item) {
                return [
                    'loja' => $item['loja'],
                    'total' => number_format(floatval($item['total']), 2, ',', '.'),
                ];
            })->values(),
        ];
    }
}
