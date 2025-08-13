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
     *   proximas_campanhas?: array<int, array{
     *       id:int,titulo:string|null,pontos:float|int|null,pontos_formatado:string|null,faltam:int,faltam_formatado:string
     *   }>
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
            ->ativosNoDia($hoje->toDateString())
            ->whereHas('faixas')
            ->get()
            // DESCARTA campanhas cuja faixa máxima é menor que a pontuação do profissional
            ->filter(function (Premio $campanha) use ($pontuacaoTotal) {
                $temIntervaloAberto = $campanha->faixas->contains(fn (PremioFaixa $f) => $f->pontos_max === null);
                if ($temIntervaloAberto) return true;

                $pontosMaxCampanha = $campanha->faixas->max('pontos_max');
                return $pontosMaxCampanha === null || $pontuacaoTotal <= (float) $pontosMaxCampanha;
            })
            ->values();

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
            $candidato = $campanhasAtivas
                ->map(function (Premio $campanha) use ($pontuacaoTotal) {
                    $prox = $campanha->faixas
                        ->filter(fn(PremioFaixa $f) => $pontuacaoTotal < (float) $f->pontos_min)
                        ->sortBy('pontos_min')
                        ->first();
                    return $prox ? ['campanha' => $campanha, 'proxima' => $prox] : null;
                })
                ->filter()
                ->sortBy(fn ($x) => $x['proxima']->pontos_min)
                ->first();

            if ($candidato) {
                $campanhaAtual = $candidato['campanha'];
                $proximaFaixa  = $candidato['proxima'];
            }
        }

        // 5) Dias restantes (corrigido)
        // Conta em dias inteiros de hoje (startOfDay) até o fim (endOfDay), nunca negativo.
        $diasRestantes = 0;
        if ($campanhaAtual && $campanhaAtual->dt_fim) {
            $agora = $hoje->copy()->startOfDay();
            $fim   = Carbon::parse($campanhaAtual->dt_fim)->endOfDay();
            $diasRestantes = $agora->diffInDays($fim, false);
            $diasRestantes = max(0, $diasRestantes);
        }

        $out = [
            'campanha'        => $campanhaAtual,
            'faixa_atual'     => $faixaAtual,
            'proxima_faixa'   => $proximaFaixa,
            'dias_restantes'  => $diasRestantes,
            'pontuacao_total' => $pontuacaoTotal,
        ];

        // 6) Próximas faixas (na campanha atual) — com campos formatados BR
        if ($incluirProximasFaixas && $campanhaAtual) {
            $out['proximas_faixas'] = $campanhaAtual->faixas
                ->whereNotNull('pontos_min')
                ->filter(fn (PremioFaixa $f) => (float) $f->pontos_min > $pontuacaoTotal)
                ->sortBy('pontos_min')
                ->values()
                ->map(fn (PremioFaixa $f) => [
                    'id'                     => $f->id,
                    'descricao'              => $f->descricao,
                    'pontos_min'             => (float)$f->pontos_min,
                    'pontos_min_formatado'   => number_format((float)$f->pontos_min, 0, ',', '.'),
                    'pontos_max'             => $f->pontos_max !== null ? (float)$f->pontos_max : null,
                    'pontos_max_formatado'   => $f->pontos_max !== null ? number_format((float)$f->pontos_max, 0, ',', '.') : null,
                    'range'                  => $f->pontos_max === null
                        ? 'a partir de ' . number_format((float)$f->pontos_min, 0, ',', '.')
                        : number_format((float)$f->pontos_min, 0, ',', '.') . ' a ' . number_format((float)$f->pontos_max, 0, ',', '.'),
                    'acompanhante_label'     => $f->acompanhante_label,
                    'valor_viagem_formatado' => $f->valor_viagem_formatado, // já BR no accessor
                ])->all();
        } else {
            $out['proximas_faixas'] = [];
        }

        // 7) Próximas campanhas “alcançáveis” — com campos formatados BR
        if ($incluirProximasCampanhas) {
            $out['proximas_campanhas'] = Premio::query()
                ->with('faixas')
                ->ativosNoDia($hoje->toDateString())
                ->whereDoesntHave('faixas')          // campanhas do tipo "pontos mínimos" no próprio prêmio
                ->where('pontos', '>', $pontuacaoTotal)
                ->orderBy('pontos')
                ->get()
                ->map(function (Premio $c) use ($pontuacaoTotal) {
                    $faltam = max(0, (int) round(((float) $c->pontos) - $pontuacaoTotal));
                    return [
                        'id'                => $c->id,
                        'titulo'            => $c->titulo,
                        'pontos'            => $c->pontos,
                        'pontos_formatado'  => $c->pontos !== null ? number_format((float)$c->pontos, 0, ',', '.') : null,
                        'faltam'            => $faltam,
                        'faltam_formatado'  => number_format($faltam, 0, ',', '.'),
                    ];
                })
                ->values()
                ->all();
        } else {
            $out['proximas_campanhas'] = [];
        }

        return $out;
    }
}
