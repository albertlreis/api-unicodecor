<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int    $id_profissional
 * @property string $nome
 * @property float  $total
 * @property array  $pontos
 */
class RankingDetalhadoResource extends JsonResource
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
     * Transforma o recurso em um array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_profissional' => $this['id_profissional'],
            'nome'            => $this['nome'],
            'total'           => $this->toIntPoints($this['total']),
            'pontos'          => collect($this['pontos'])->map(function ($item) {
                return [
                    'loja'  => $item['loja'],
                    'total' => $this->toIntPoints($item['total']),
                ];
            })->values(),
        ];
    }
}
