<?php

namespace App\Domain\Premios\Services;

use App\Models\Ponto;
use App\Models\Premio;
use App\Models\PremioFaixa;
use App\Support\YearRange;
use Carbon\Carbon;

/**
 * Serviço para resolver faixas e prêmios do profissional numa data base.
 */
final class PremioFaixaResolver
{
    /**
     * @param int         $usuarioId
     * @param string|null $dataBase  ISO Y-m-d (default: hoje)
     * @param bool        $incluirProximasFaixas
     * @param bool        $incluirProximasCampanhas
     * @return array{
     *   campanha: \App\Models\Premio|null,
     *   faixa_atual: \App\Models\PremioFaixa|null,
     *   proxima_faixa: \App\Models\PremioFaixa|null,
     *   dias_restantes: int,
     *   pontuacao_total: float,
     *   proximas_faixas?: array<int, array<string,mixed>>,
     *   proximas_campanhas?: array<int, array{id:int,titulo:string|null,pontos:float|int|null,faltam:int}>
     * }
     */
    public function resolver(
        int $usuarioId,
        ?string $dataBase = null,
        bool $incluirProximasFaixas = true,
        bool $incluirProximasCampanhas = true
    ): array {
        $hoje = Carbon::parse($dataBase ?: Carbon::today()->toDateString());
        [$inicioAno, $fimAno] = YearRange::forDate($hoje->toDateString());

        // 1) Pontuação anual do profissional
        $pontuacaoTotal = (float) Ponto::where('id_profissional', $usuarioId)
            ->whereBetween('dt_referencia', [$inicioAno, $fimAno])
            ->sum('valor');

        // 2) Campanhas ativas na data-base, com faixas
        $campanhasAtivas = Premio::query()
            ->with(['faixas' => fn($q) => $q->orderBy('pontos_min')])
            ->where('status', 1)
            ->whereDate('dt_inicio', '<=', $hoje->toDateString())
            ->where(fn($q) => $q->whereNull('dt_fim')->orWhereDate('dt_fim', '>=', $hoje->toDateString()))
            ->whereHas('faixas')
            ->orderBy('dt_inicio')
            ->get();

        $faixaAtual = null;
        $proximaFaixa = null;
        $campanhaAtual = null;

        // 3) Campanha/faixa atual
        foreach ($campanhasAtivas as $campanha) {
            $faixa = $campanha->faixas
                ->filter(fn(PremioFaixa $f) =>
                    $pontuacaoTotal >= (float) $f->pontos_min
                    && ($f->pontos_max === null || $pontuacaoTotal <= (float) $f->pontos_max)
                )
                ->sortByDesc('pontos_min')
                ->first();

            if ($faixa) {
                $faixaAtual    = $faixa;
                $campanhaAtual = $campanha;
                $proximaFaixa  = $campanha->faixas
                    ->filter(fn(PremioFaixa $f) => $pontuacaoTotal < (float) $f->pontos_min)
                    ->sortBy('pontos_min')
                    ->first();
                break;
            }
        }

        // 4) Sem faixa atual? escolhe a campanha com a menor faixa acima da pontuação
        if (!$campanhaAtual) {
            $candidato = $campanhasAtivas->map(function ($campanha) use ($pontuacaoTotal) {
                $prox = $campanha->faixas
                    ->filter(fn(PremioFaixa $f) => $pontuacaoTotal < (float) $f->pontos_min)
                    ->sortBy('pontos_min')
                    ->first();
                return $prox ? ['campanha' => $campanha, 'proxima' => $prox] : null;
            })->filter()->sortBy(fn ($x) => $x['proxima']->pontos_min)->first();

            if ($candidato) {
                $campanhaAtual = $candidato['campanha'];
                $proximaFaixa  = $candidato['proxima'];
            }
        }

        $diasRestantes = $campanhaAtual
            ? Carbon::parse($campanhaAtual->dt_fim)->diffInDays($hoje, false)
            : 0;

        $out = [
            'campanha'        => $campanhaAtual,
            'faixa_atual'     => $faixaAtual,
            'proxima_faixa'   => $proximaFaixa,
            'dias_restantes'  => max(0, $diasRestantes),
            'pontuacao_total' => $pontuacaoTotal,
        ];

        // 5) Próximas faixas (dentro da campanha atual)
        if ($incluirProximasFaixas && $campanhaAtual) {
            $out['proximas_faixas'] = $campanhaAtual->faixas
                ->whereNotNull('pontos_min')
                ->filter(fn ($f) => (float) $f->pontos_min > $pontuacaoTotal)
                ->sortBy('pontos_min')
                ->values()
                ->map(fn ($f) => [
                    'id'                     => $f->id,
                    'range'                  => $f->pontos_range_formatado,
                    'descricao'              => $f->descricao,
                    'acompanhante_label'     => $f->acompanhante_label,
                    'valor_viagem_formatado' => $f->valor_viagem_formatado,
                    'pontos_min'             => $f->pontos_min,
                    'pontos_max'             => $f->pontos_max,
                ])->all();
        } else {
            $out['proximas_faixas'] = [];
        }

        // 6) Próximas campanhas “alcançáveis” (ativas hoje, sem faixas, pontos > pontuacao_total)
        if ($incluirProximasCampanhas) {
            $out['proximas_campanhas'] = Premio::query()
                ->with('faixas')
                ->where('status', 1)
                ->whereDate('dt_inicio', '<=', $hoje->toDateString())
                ->where(fn($q) => $q->whereNull('dt_fim')->orWhereDate('dt_fim', '>=', $hoje->toDateString()))
                ->whereDoesntHave('faixas') // campanhas tipo TOP 100 (pontos mínimos)
                ->where('pontos', '>', $pontuacaoTotal)
                ->orderBy('pontos')
                ->get()
                ->map(fn ($c) => [
                    'id'     => $c->id,
                    'titulo' => $c->titulo,
                    'pontos' => $c->pontos,
                    'faltam' => max(0, (int) round(((float) $c->pontos) - $pontuacaoTotal)),
                ])
                ->values()
                ->all();
        } else {
            $out['proximas_campanhas'] = [];
        }

        return $out;
    }
}
