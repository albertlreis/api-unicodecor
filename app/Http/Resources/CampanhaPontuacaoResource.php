<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property-read array $resource
 */
class CampanhaPontuacaoResource extends JsonResource
{
    /**
     * Transforma a campanha com pontuação e faixas para resposta em JSON.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pontuacaoTotal = $this->resource['pontuacao_total'];
        $campanha = $this->resource['campanha'];
        $faixaAtual = $this->resource['faixa_atual'];
        $proximaFaixa = $this->resource['proxima_faixa'];

        return [
            'pontuacao_total' => number_format($pontuacaoTotal, 0, ',', '.'),
            'dias_restantes' => $this->resource['dias_restantes'],
            'campanha' => [
                'id' => $campanha->id,
                'titulo' => $campanha->titulo,
                'banner' => $campanha->banner,
                'dt_inicio' => Carbon::parse($campanha->dt_inicio)->format('d/m/Y'),
                'dt_fim' => Carbon::parse($campanha->dt_fim)->format('d/m/Y'),
                'periodo' => Carbon::parse($campanha->dt_inicio)->format('d/m/Y') . ' até ' . Carbon::parse($campanha->dt_fim)->format('d/m/Y'),
            ],
            'faixa_atual' => $faixaAtual ? [
                'id' => $faixaAtual->id,
                'descricao' => $faixaAtual->descricao,
                'pontos_min' => $faixaAtual->pontos_min,
                'pontos_max' => $faixaAtual->pontos_max,
                'pontos_range' => number_format($faixaAtual->pontos_min, 0, ',', '.') . ' a ' . number_format($faixaAtual->pontos_max, 0, ',', '.'),
                'acompanhante' => $faixaAtual->acompanhante,
                'acompanhante_texto' => $faixaAtual->acompanhante ? 'Com acompanhante' : 'Somente profissional',
                'vl_viagem' => $faixaAtual->vl_viagem,
            ] : null,
            'proxima_faixa' => $proximaFaixa ? [
                'id' => $proximaFaixa->id,
                'descricao' => $proximaFaixa->descricao,
                'pontos_min' => $proximaFaixa->pontos_min,
                'pontos_max' => $proximaFaixa->pontos_max,
                'pontos_range' => number_format($proximaFaixa->pontos_min, 0, ',', '.') . ' a ' . number_format($proximaFaixa->pontos_max, 0, ',', '.'),
                'acompanhante' => $proximaFaixa->acompanhante,
                'acompanhante_texto' => $proximaFaixa->acompanhante ? 'Com acompanhante' : 'Somente profissional',
                'vl_viagem' => $proximaFaixa->vl_viagem,
            ] : null,
        ];
    }
}
