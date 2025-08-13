<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Resource de resposta para /me/premios (visão PROFISSIONAL).
 *
 * @phpstan-type ProximaCampanha array{
 *   id:int,
 *   titulo:string|null,
 *   pontos:float|int|null,
 *   pontos_formatado:string|null,
 *   faltam:int,
 *   faltam_formatado:string
 * }
 * @phpstan-type Input array{
 *   pontuacao_total: float|int,
 *   dias_restantes: int,
 *   campanha: \App\Models\Premio|null,
 *   faixa_atual: \App\Models\PremioFaixa|null,
 *   proxima_faixa: \App\Models\PremioFaixa|null,
 *   proximas_faixas: array<int, array<string,mixed>>,
 *   proximas_campanhas: array<int, ProximaCampanha>
 * }
 *
 * @property-read array $resource
 */
class PremiosFaixasProfissionalResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string,mixed>
     */
    public function toArray($request): array
    {
        /** @var array{
         *   pontuacao_total: float|int,
         *   dias_restantes: int,
         *   campanha: mixed,
         *   faixa_atual: mixed,
         *   proxima_faixa: mixed,
         *   proximas_faixas?: array<int, array<string,mixed>>,
         *   proximas_campanhas?: array<int, array<string,mixed>>
         * } $data
         */
        $data = $this->resource;

        $pontuacaoTotal = (float) ($data['pontuacao_total'] ?? 0);
        $campanha       = $data['campanha'] ?? null;
        $faixaAtual     = $data['faixa_atual'] ?? null;
        $proximaFaixa   = $data['proxima_faixa'] ?? null;

        return [
            // Pontuação do profissional
            'pontuacao_total'           => $pontuacaoTotal,
            'pontuacao_total_formatado' => number_format($pontuacaoTotal, 0, ',', '.'),

            // Progresso
            'dias_restantes'            => (int)($data['dias_restantes'] ?? 0),

            // Campanha atual (quando houver)
            'campanha' => $campanha ? [
                'id'               => $campanha->id,
                'titulo'           => $campanha->titulo,
                'banner'           => $campanha->banner,
                // Datas ISO + BR
                'dt_inicio_iso'    => self::toIso($campanha->dt_inicio),
                'dt_fim_iso'       => self::toIso($campanha->dt_fim),
                'dt_inicio'        => self::brDate($campanha->dt_inicio),
                'dt_fim'           => self::brDate($campanha->dt_fim),
                'periodo'          => self::periodo($campanha->dt_inicio, $campanha->dt_fim),
            ] : null,

            // Faixa atual (quando houver)
            'faixa_atual' => $faixaAtual ? [
                'id'                   => $faixaAtual->id,
                'descricao'            => $faixaAtual->descricao,
                'pontos_min'           => (float) $faixaAtual->pontos_min,
                'pontos_min_formatado' => number_format((float)$faixaAtual->pontos_min, 0, ',', '.'),
                'pontos_max'           => self::toNullableFloat($faixaAtual->pontos_max),
                'pontos_max_formatado' => $faixaAtual->pontos_max !== null
                    ? number_format((float)$faixaAtual->pontos_max, 0, ',', '.')
                    : null,
                'pontos_range'         => self::rangeText($faixaAtual->pontos_min, $faixaAtual->pontos_max),
                'acompanhante'         => (bool) $faixaAtual->acompanhante,
                'acompanhante_texto'   => $faixaAtual->acompanhante ? 'Com acompanhante' : 'Somente profissional',
                'vl_viagem'            => self::toNullableFloat($faixaAtual->vl_viagem),
                'vl_viagem_formatado'  => $faixaAtual->vl_viagem !== null
                    ? number_format((float)$faixaAtual->vl_viagem, 2, ',', '.')
                    : null,
            ] : null,

            // Próxima faixa (quando houver)
            'proxima_faixa' => $proximaFaixa ? [
                'id'                   => $proximaFaixa->id,
                'descricao'            => $proximaFaixa->descricao,
                'pontos_min'           => (float) $proximaFaixa->pontos_min,
                'pontos_min_formatado' => number_format((float)$proximaFaixa->pontos_min, 0, ',', '.'),
                'pontos_max'           => self::toNullableFloat($proximaFaixa->pontos_max),
                'pontos_max_formatado' => $proximaFaixa->pontos_max !== null
                    ? number_format((float)$proximaFaixa->pontos_max, 0, ',', '.')
                    : null,
                'pontos_range'         => self::rangeText($proximaFaixa->pontos_min, $proximaFaixa->pontos_max),
                'acompanhante'         => (bool) $proximaFaixa->acompanhante,
                'acompanhante_texto'   => $proximaFaixa->acompanhante ? 'Com acompanhante' : 'Somente profissional',
                'vl_viagem'            => self::toNullableFloat($proximaFaixa->vl_viagem),
                'vl_viagem_formatado'  => $proximaFaixa->vl_viagem !== null
                    ? number_format((float)$proximaFaixa->vl_viagem, 2, ',', '.')
                    : null,
            ] : null,

            // Listas auxiliares (já com valores formatados vindos do Service)
            'proximas_faixas'    => array_values($data['proximas_faixas'] ?? []),
            'proximas_campanhas' => array_values($data['proximas_campanhas'] ?? []),
        ];
    }

    private static function brDate($date): ?string
    {
        if (!$date) return null;
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function toIso($date): ?string
    {
        if (!$date) return null;
        try {
            return Carbon::parse($date)->toDateString(); // Y-m-d
        } catch (\Throwable) {
            return null;
        }
    }

    private static function periodo($inicio, $fim): ?string
    {
        $i = self::brDate($inicio);
        $f = self::brDate($fim);
        return ($i && $f) ? ($i . ' até ' . $f) : null;
    }

    /**
     * @param mixed $value
     */
    private static function toNullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    /**
     * Monta o texto de faixa considerando max nulo (intervalo aberto).
     * Ex.: "a partir de 10.000" ou "10.000 a 20.000"
     * @param float|int $min
     * @param float|int|null $max
     */
    private static function rangeText($min, $max): string
    {
        $minF = number_format((float)$min, 0, ',', '.');
        if ($max === null || $max === '') {
            return "a partir de {$minF}";
        }
        $maxF = number_format((float)$max, 0, ',', '.');
        return "{$minF} a {$maxF}";
    }
}
