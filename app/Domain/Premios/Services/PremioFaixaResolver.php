<?php

namespace App\Domain\Premios\Services;

use App\Models\Ponto;
use App\Models\Premio;
use App\Models\PremioFaixa;
use App\Support\YearRange;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolve faixas e campanhas (todas com faixas) para um profissional em uma data-base.
 *
 * Regras principais:
 * - campanha: campanha atual (a que contém a pontuação ou a mais próxima acima).
 * - faixa_atual: faixa que contém a pontuação na campanha atual (se houver).
 * - proxima_faixa: **primeira faixa GLOBAL** com pontos_min > pontuacao_total.
 * - proximas_faixas: **demais faixas GLOBAIS** acima da pontuação, ordenadas por pontos_min.
 * - proximas_campanhas: campanhas únicas (diferentes da atual), cada uma com o menor pontos_min acima da pontuação.
 */
final class PremioFaixaResolver
{
    /**
     * @param int         $usuarioId
     * @param string|null $dataBase  ISO Y-m-d (default: hoje)
     * @param bool        $incluirProximasFaixas
     * @param bool        $incluirProximasCampanhas
     * @return array{
     *   campanha: array{id:int,titulo:string|null,banner:?string,regulamento:?string,dt_inicio_iso:?string,dt_fim_iso:?string,dt_inicio:?string,dt_fim:?string,periodo:?string}|null,
     *   faixa_atual: array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string}|null,
     *   proxima_faixa: array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string}|null,
     *   dias_restantes: int,
     *   pontuacao_total: float,
     *   pontuacao_total_formatado: string,
     *   proximas_faixas: array<int, array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string}>,
     *   proximas_campanhas: array<int, array{id:int,titulo:?string,banner:?string,regulamento:?string,pontos:int,pontos_formatado:string,faltam:int,faltam_formatado:string}>
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

        // 2) Campanhas ativas com faixas (ordenadas para determinismo)
        /** @var Collection<int,Premio> $campanhasAtivas */
        $campanhasAtivas = Premio::query()
            ->with(['faixas' => fn($q) => $q->orderBy('pontos_min')])
            ->ativosNoDia($hoje->toDateString())
            ->whereHas('faixas')
            ->orderBy('dt_fim')
            ->orderBy('dt_inicio')
            ->get()
            // descarta campanhas cuja faixa máxima < pontuação (a menos que exista faixa aberta)
            ->filter(function (Premio $campanha) use ($pontuacaoTotal) {
                $temAberta = $campanha->faixas->contains(fn (PremioFaixa $f) => $f->pontos_max === null);
                if ($temAberta) return true;
                $max = $campanha->faixas->max('pontos_max');
                return $max === null || $pontuacaoTotal <= (float) $max;
            })
            ->values();

        // 3) Campanha/faixa atual
        $campanhaAtual = null;
        $faixaAtual    = null;

        foreach ($campanhasAtivas as $campanha) {
            $faixa = $campanha->faixas
                ->filter(fn(PremioFaixa $f) =>
                    $pontuacaoTotal >= (float) $f->pontos_min
                    && ($f->pontos_max === null || $pontuacaoTotal <= (float) $f->pontos_max)
                )
                ->sortByDesc('pontos_min')
                ->first();

            if ($faixa) {
                $campanhaAtual = $campanha;
                $faixaAtual    = $faixa;
                break;
            }
        }

        // Se não estiver contido em nenhuma faixa, use a campanha cuja menor faixa acima é a mais próxima
        if (!$campanhaAtual) {
            $candidato = $campanhasAtivas
                ->map(function (Premio $campanha) use ($pontuacaoTotal) {
                    $prox = $campanha->faixas
                        ->first(fn (PremioFaixa $f) => (float)$f->pontos_min > $pontuacaoTotal);
                    return $prox ? ['campanha' => $campanha, 'proxima' => $prox] : null;
                })
                ->filter()
                ->sortBy(fn ($x) => (int)$x['proxima']->pontos_min)
                ->first();

            if ($candidato) {
                $campanhaAtual = $candidato['campanha'];
                // $faixaAtual permanece null (ainda não alcançou nenhuma faixa desta campanha)
            }
        }

        // 4) Dias restantes (nunca negativo)
        $diasRestantes = 0;
        if ($campanhaAtual && $campanhaAtual->dt_fim) {
            $agora = $hoje->copy()->startOfDay();
            $fim   = Carbon::parse($campanhaAtual->dt_fim)->endOfDay();
            $diasRestantes = max(0, $agora->diffInDays($fim, false));
        }

        // 5) Lista GLOBAL de faixas acima da pontuação
        /** @var Collection<int,PremioFaixa> $globalFaixasAcima */
        $globalFaixasAcima = $campanhasAtivas
            ->flatMap(fn (Premio $c) => $c->faixas)
            ->filter(fn (PremioFaixa $f) => (int)$f->pontos_min > $pontuacaoTotal)
            ->sortBy(fn (PremioFaixa $f) => (int)$f->pontos_min)
            ->values();

        // 6) proxima_faixa = primeira GLOBAL; proximas_faixas = restantes GLOBAIS
        $proximaFaixaModel = $globalFaixasAcima->first();
        $proximasFaixasModels = $globalFaixasAcima->slice(1)->values();

        // 7) proximas_campanhas (campanhas únicas com sua menor faixa acima)
        $proximasCampanhas = [];
        if ($incluirProximasCampanhas) {
            /** @var Collection<int,array{campanha:Premio,faixa:PremioFaixa}> $menoresPorCampanha */
            $menoresPorCampanha = $campanhasAtivas
                ->flatMap(function (Premio $c) use ($pontuacaoTotal) {
                    return $c->faixas
                        ->filter(fn (PremioFaixa $f) => (int)$f->pontos_min > $pontuacaoTotal)
                        ->map(fn (PremioFaixa $f) => ['campanha' => $c, 'faixa' => $f]);
                })
                ->groupBy(fn ($x) => $x['campanha']->id)
                ->map(fn (Collection $grupo) =>
                $grupo->sortBy(fn ($x) => (int)$x['faixa']->pontos_min)->first()
                )
                ->values();

            if ($campanhaAtual) {
                $menoresPorCampanha = $menoresPorCampanha
                    ->filter(fn ($x) => $x['campanha']->id !== $campanhaAtual->id)
                    ->values();
            }

            $proximasCampanhas = $menoresPorCampanha
                ->sortBy(fn ($x) => (int)$x['faixa']->pontos_min)
                ->values()
                ->map(function ($x) use ($pontuacaoTotal) {
                    /** @var Premio $c */
                    $c = $x['campanha'];
                    /** @var PremioFaixa $f */
                    $f = $x['faixa'];
                    $alvo   = (int)$f->pontos_min;
                    $faltam = max(0, (int)round($alvo - $pontuacaoTotal));
                    return [
                        'id'                => $c->id,
                        'titulo'            => $c->titulo,
                        'banner'            => $c->banner,
                        'regulamento'       => $c->regulamento,
                        'pontos'            => $alvo,
                        'pontos_formatado'  => number_format($alvo, 0, ',', '.'),
                        'faltam'            => $faltam,
                        'faltam_formatado'  => number_format($faltam, 0, ',', '.'),
                    ];
                })
                ->all();
        }

        // 8) Monta saída
        return [
            'campanha'                  => $campanhaAtual ? $this->mapCampanha($campanhaAtual) : null,
            'faixa_atual'               => $faixaAtual ? $this->mapFaixa($faixaAtual) : null,

            // Agora global:
            'proxima_faixa'             => $proximaFaixaModel ? $this->mapFaixa($proximaFaixaModel) : null,
            'proximas_faixas'           => $proximasFaixasModels->map(fn ($f) => $this->mapFaixa($f))->all(),

            'dias_restantes'            => $diasRestantes,
            'pontuacao_total'           => $pontuacaoTotal,
            'pontuacao_total_formatado' => number_format($pontuacaoTotal, 0, ',', '.'),

            'proximas_campanhas'        => $proximasCampanhas,
        ];
    }

    /**
     * Mapeia uma campanha para o formato público usado pelo app.
     * @param Premio $c
     * @return array{id:int,titulo:?string,banner:?string,regulamento:?string,dt_inicio_iso:?string,dt_fim_iso:?string,dt_inicio:?string,dt_fim:?string,periodo:?string}
     */
    private function mapCampanha(Premio $c): array
    {
        $dtInicio = $c->dt_inicio ? Carbon::parse($c->dt_inicio) : null;
        $dtFim    = $c->dt_fim ? Carbon::parse($c->dt_fim) : null;

        return [
            'id'            => $c->id,
            'titulo'        => $c->titulo,
            'banner'        => $c->banner,
            'regulamento'   => $c->regulamento,
            'dt_inicio_iso' => $dtInicio?->toDateString(),
            'dt_fim_iso'    => $dtFim?->toDateString(),
            'dt_inicio'     => $dtInicio ? $dtInicio->format('d/m/Y') : null,
            'dt_fim'        => $dtFim ? $dtFim->format('d/m/Y') : null,
            'periodo'       => ($dtInicio && $dtFim) ? $dtInicio->format('d/m/Y').' até '.$dtFim->format('d/m/Y') : null,
        ];
    }

    /**
     * Mapeia uma faixa para o formato público usado pelo app.
     * @param PremioFaixa $f
     * @return array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string}
     */
    private function mapFaixa(PremioFaixa $f): array
    {
        $min = (int)$f->pontos_min;
        $max = $f->pontos_max !== null ? (int)$f->pontos_max : null;

        return [
            'id'                   => $f->id,
            'descricao'            => $f->descricao,
            'pontos_min'           => $min,
            'pontos_min_formatado' => number_format($min, 0, ',', '.'),
            'pontos_max'           => $max,
            'pontos_max_formatado' => $max !== null ? number_format($max, 0, ',', '.') : null,
            'pontos_range'         => $max === null
                ? 'a partir de ' . number_format($min, 0, ',', '.')
                : number_format($min, 0, ',', '.') . ' a ' . number_format($max, 0, ',', '.'),
            'acompanhante'         => (bool)($f->acompanhante ?? false),
            'acompanhante_texto'   => (bool)($f->acompanhante ?? false) ? 'Com acompanhante' : 'Somente profissional',
            'vl_viagem'            => $f->vl_viagem !== null ? (float)$f->vl_viagem : null,
            'vl_viagem_formatado'  => $f->vl_viagem !== null ? number_format((float)$f->vl_viagem, 2, ',', '.') : null,
        ];
    }
}
