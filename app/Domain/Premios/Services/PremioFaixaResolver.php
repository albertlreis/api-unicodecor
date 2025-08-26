<?php

namespace App\Domain\Premios\Services;

use App\Models\Ponto;
use App\Models\Premio;
use App\Models\PremioFaixa;
use App\Support\YearRange;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolve faixas/campanhas para um profissional em uma data-base.
 *
 * Regras principais:
 * - proxima_faixa: SEMPRE retornar a menor faixa com pontos_min > pontuação do usuário.
 *   1) Tenta na campanha atual;
 *   2) Se não houver, busca entre as demais campanhas ativas (a menor acima);
 *   3) Informa origem e premio_id da faixa retornada.
 */
final class PremioFaixaResolver
{
    /**
     * @param int $usuarioId
     * @param string|null $dataBase ISO Y-m-d (default: hoje)
     * @param bool $incluirProximasFaixas
     * @param bool $incluirProximasCampanhas
     * @return array{
     *   campanha: array{id:int,titulo:?string,banner:?string,regulamento:?string,dt_inicio_iso:?string,dt_fim_iso:?string,dt_inicio:?string,dt_fim:?string,periodo:?string}|null,
     *   faixa_atual: array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string,premio_id:int}|null,
     *   proxima_faixa: array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string,premio_id:int}|null,
     *   proxima_faixa_origem: 'campanha_atual'|'proxima_campanha'|null,
     *   proxima_faixa_premio_id: int|null,
     *   dias_restantes: int,
     *   pontuacao_total: float,
     *   pontuacao_total_formatado: string,
     *   proximas_faixas: array<int, array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string,premio_id:int}>,
     *   proximas_campanhas: array<int, array{
     *      id:int,titulo:?string,banner:?string,regulamento:?string,
     *      pontos:int,pontos_formatado:string,faltam:int,faltam_formatado:string,
     *      dt_inicio_iso:?string,dt_fim_iso:?string,dt_inicio:?string,dt_fim:?string,
     *      destaque_faixa_id:int|null,
     *      faixas: array<int, array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string,premio_id:int}>
     *   }>
     * }
     */
    public function resolver(
        int     $usuarioId,
        ?string $dataBase = null,
        bool    $incluirProximasFaixas = true,
        bool    $incluirProximasCampanhas = true
    ): array
    {
        $hoje = Carbon::parse($dataBase ?: Carbon::today()->toDateString());
        [$inicioAno, $fimAno] = YearRange::forDate($hoje->toDateString());

        // 1) Pontuação anual do profissional
        $pontuacaoTotal = (float)Ponto::where('id_profissional', $usuarioId)
            ->whereBetween('dt_referencia', [$inicioAno, $fimAno])
            ->sum('valor');

        // 2) Campanhas ativas com faixas
        /** @var Collection<int,Premio> $campanhasAtivas */
        $campanhasAtivas = Premio::query()
            ->with(['faixas' => fn($q) => $q->orderBy('pontos_min')])
            ->ativosNoDia($hoje->toDateString())
            ->whereHas('faixas')
            ->orderBy('dt_fim')->orderBy('dt_inicio')
            ->get()
            ->filter(function (Premio $campanha) use ($pontuacaoTotal) {
                $temAberta = $campanha->faixas->contains(fn(PremioFaixa $f) => $f->pontos_max === null);
                if ($temAberta) return true;
                $max = $campanha->faixas->max('pontos_max');
                return $max === null || $pontuacaoTotal <= (float)$max;
            })
            ->values();

        // 3) Campanha/faixa atual
        $campanhaAtual = null;
        $faixaAtual = null;

        foreach ($campanhasAtivas as $campanha) {
            $faixa = $campanha->faixas
                ->filter(fn(PremioFaixa $f) => $pontuacaoTotal >= (float)$f->pontos_min
                    && ($f->pontos_max === null || $pontuacaoTotal <= (float)$f->pontos_max)
                )
                ->sortByDesc('pontos_min')
                ->first();

            if ($faixa) {
                $campanhaAtual = $campanha;
                $faixaAtual = $faixa;
                break;
            }
        }

        if (!$campanhaAtual) {
            // Se não está dentro de nenhuma, escolhe a campanha cuja menor faixa acima é a mais próxima
            $cand = $campanhasAtivas
                ->map(function (Premio $c) use ($pontuacaoTotal) {
                    $prox = $c->faixas->first(fn(PremioFaixa $f) => (int)$f->pontos_min > $pontuacaoTotal);
                    return $prox ? ['campanha' => $c, 'proxima' => $prox] : null;
                })
                ->filter()
                ->sortBy(fn($x) => (int)$x['proxima']->pontos_min)
                ->first();
            if ($cand) $campanhaAtual = $cand['campanha'];
        }

        // 4) Dias restantes
        $diasRestantes = 0;
        if ($campanhaAtual && $campanhaAtual->dt_fim) {
            $agora = $hoje->copy()->startOfDay();
            $fim = Carbon::parse($campanhaAtual->dt_fim)->endOfDay();
            $diasRestantes = max(0, $agora->diffInDays($fim, false));
        }

        // 5) Próximas FAIXAS (campanha atual) + cálculo global da "sempre próxima"
        /** @var PremioFaixa|null $proximaFaixaModel */
        $proximaFaixaModel = null;
        $proximasFaixasModels = collect();

        if ($campanhaAtual && $incluirProximasFaixas) {
            $acima = $campanhaAtual->faixas
                ->filter(fn(PremioFaixa $f) => (int)$f->pontos_min > $pontuacaoTotal)
                ->sortBy('pontos_min')
                ->values();

            $proximaFaixaModel = $acima->first() ?: null;     // próxima da campanha atual
            $proximasFaixasModels = $acima->slice(1)->values();  // demais da campanha atual
        }

        // 6) Próximas CAMPANHAS + “global next” (menor faixa acima em qualquer campanha)
        $proximasCampanhas = [];
        /** @var PremioFaixa|null $globalNextFaixaModel */
        $globalNextFaixaModel = $proximaFaixaModel;
        $globalNextPremioId = $proximaFaixaModel ? (int)$proximaFaixaModel->id_premio : ($campanhaAtual?->id ?? null);

        if ($incluirProximasCampanhas) {
            $outrasCampanhas = $campanhasAtivas
                ->filter(fn(Premio $c) => !$campanhaAtual || $c->id !== $campanhaAtual->id)
                ->values();

            $proximasCampanhas = $outrasCampanhas
                ->map(function (Premio $c) use ($pontuacaoTotal, &$globalNextFaixaModel, &$globalNextPremioId) {
                    $faixasAcima = $c->faixas
                        ->filter(fn(PremioFaixa $f) => (int)$f->pontos_min > $pontuacaoTotal)
                        ->sortBy('pontos_min')
                        ->values();

                    if ($faixasAcima->isEmpty()) {
                        return null;
                    }

                    /** @var PremioFaixa $faixaAlvo */
                    $faixaAlvo = $faixasAcima->first();

                    // Atualiza “global next” (menor pontos_min dentre TODAS as campanhas)
                    if ($globalNextFaixaModel === null || (int)$faixaAlvo->pontos_min < (int)$globalNextFaixaModel->pontos_min) {
                        $globalNextFaixaModel = $faixaAlvo;
                        $globalNextPremioId = (int)$c->id;
                    }

                    $alvo = (int)$faixaAlvo->pontos_min;
                    $faltam = max(0, (int)round($alvo - $pontuacaoTotal));

                    $dtInicio = $c->dt_inicio ? Carbon::parse($c->dt_inicio) : null;
                    $dtFim = $c->dt_fim ? Carbon::parse($c->dt_fim) : null;

                    return [
                        'id' => $c->id,
                        'titulo' => $c->titulo,
                        'banner' => $c->banner,
                        'regulamento' => $c->regulamento,
                        'pontos' => $alvo,
                        'pontos_formatado' => number_format($alvo, 0, ',', '.'),
                        'faltam' => $faltam,
                        'faltam_formatado' => number_format($faltam, 0, ',', '.'),
                        'dt_inicio_iso' => $dtInicio?->toDateString(),
                        'dt_fim_iso' => $dtFim?->toDateString(),
                        'dt_inicio' => $dtInicio ? $dtInicio->format('d/m/Y') : null,
                        'dt_fim' => $dtFim ? $dtFim->format('d/m/Y') : null,
                        'destaque_faixa_id' => $faixaAlvo->id,
                        'faixas' => $faixasAcima->map(fn(PremioFaixa $f) => $this->mapFaixa($f))->all(),
                    ];
                })
                ->filter()
                ->sortBy('pontos') // metas mais próximas primeiro
                ->values()
                ->all();
        }

        // 7) Sempre retornar proxima_faixa (da atual ou de outra campanha)
        $proximaFaixaOrigem = null;
        $proximaFaixaPremioId = null;

        if ($globalNextFaixaModel) {
            $proximaFaixaPremioId = (int)$globalNextPremioId;
            if ($campanhaAtual && $proximaFaixaPremioId === (int)$campanhaAtual->id) {
                $proximaFaixaOrigem = 'campanha_atual';
            } else {
                $proximaFaixaOrigem = 'proxima_campanha';
            }
        }

        return [
            'campanha' => $campanhaAtual ? $this->mapCampanha($campanhaAtual) : null,
            'faixa_atual' => $faixaAtual ? $this->mapFaixa($faixaAtual) : null,

            // ✅ SEMPRE presente se existir alguma faixa acima em qualquer campanha
            'proxima_faixa' => $globalNextFaixaModel ? $this->mapFaixa($globalNextFaixaModel) : null,
            'proxima_faixa_origem' => $proximaFaixaOrigem,
            'proxima_faixa_premio_id' => $proximaFaixaPremioId,

            // Demais faixas da campanha atual (se houver)
            'proximas_faixas' => $proximasFaixasModels->map(fn($f) => $this->mapFaixa($f))->all(),

            'dias_restantes' => $diasRestantes,
            'pontuacao_total' => $pontuacaoTotal,
            'pontuacao_total_formatado' => number_format($pontuacaoTotal, 0, ',', '.'),

            'proximas_campanhas' => $proximasCampanhas,
        ];
    }

    /**
     * @param Premio $c
     * @return array{id:int,titulo:?string,banner:?string,regulamento:?string,dt_inicio_iso:?string,dt_fim_iso:?string,dt_inicio:?string,dt_fim:?string,periodo:?string}
     */
    private function mapCampanha(Premio $c): array
    {
        $dtInicio = $c->dt_inicio ? Carbon::parse($c->dt_inicio) : null;
        $dtFim = $c->dt_fim ? Carbon::parse($c->dt_fim) : null;

        return [
            'id' => $c->id,
            'titulo' => $c->titulo,
            'banner' => $c->banner,
            'regulamento' => $c->regulamento,
            'dt_inicio_iso' => $dtInicio?->toDateString(),
            'dt_fim_iso' => $dtFim?->toDateString(),
            'dt_inicio' => $dtInicio ? $dtInicio->format('d/m/Y') : null,
            'dt_fim' => $dtFim ? $dtFim->format('d/m/Y') : null,
            'periodo' => ($dtInicio && $dtFim) ? $dtInicio->format('d/m/Y') . ' até ' . $dtFim->format('d/m/Y') : null,
        ];
    }

    /**
     * @param PremioFaixa $f
     * @return array{id:int,descricao:?string,pontos_min:int,pontos_min_formatado:string,pontos_max:?int,pontos_max_formatado:?string,pontos_range:string,acompanhante:bool,acompanhante_texto:string,vl_viagem:?float,vl_viagem_formatado:?string,premio_id:int}
     */
    private function mapFaixa(PremioFaixa $f): array
    {
        $min = (int)$f->pontos_min;
        $max = $f->pontos_max !== null ? (int)$f->pontos_max : null;

        return [
            'id' => $f->id,
            'descricao' => $f->descricao,
            'pontos_min' => $min,
            'pontos_min_formatado' => number_format($min, 0, ',', '.'),
            'pontos_max' => $max,
            'pontos_max_formatado' => $max !== null ? number_format($max, 0, ',', '.') : null,
            'pontos_range' => $max === null
                ? 'a partir de ' . number_format($min, 0, ',', '.')
                : number_format($min, 0, ',', '.') . ' a ' . number_format($max, 0, ',', '.'),
            'acompanhante' => (bool)($f->acompanhante ?? false),
            'acompanhante_texto' => (bool)($f->acompanhante ?? false) ? 'Com acompanhante' : 'Somente profissional',
            'vl_viagem' => $f->vl_viagem !== null ? (float)$f->vl_viagem : null,
            'vl_viagem_formatado' => $f->vl_viagem !== null ? number_format((float)$f->vl_viagem, 2, ',', '.') : null,
            'premio_id' => $f->id_premio,
        ];
    }
}
