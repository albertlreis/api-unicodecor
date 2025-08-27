<?php

namespace App\Services;

use App\Models\Premio;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Serviço de ranking/top100.
 */
class RankingService
{
    /**
     * Retorna dados para o card do Top100 na Home, calculando
     * automaticamente o ciclo vigente (01/08 → 31/07), mesmo
     * sem registro em `premios`.
     *
     * @param int $userId
     * @return array{
     *   colocacao: int|null,
     *   pontuacao_total: string,
     *   data_fim_campanha: string,
     *   dias_restantes: int,
     *   dt_inicio_iso: string,
     *   dt_fim_iso: string,
     *   periodo_label: string
     * }
     */
    public function getTop100Data(int $userId): array
    {
        [$inicio, $fim, $label] = $this->resolverJanelaTop100();

        // ===== Subconsulta base: total de pontos por profissional no ciclo Top100
        $base = $this->buildBaseSomaPontos($inicio, $fim);

        // ===== 1) Tenta com window function (MySQL 8+ / MariaDB com suporte)
        try {
            $ranked = DB::query()
                ->fromSub($base, 'r')
                ->selectRaw('r.id_profissional, r.total, RANK() OVER (ORDER BY r.total DESC) AS colocacao');

            $row = DB::query()
                ->fromSub($ranked, 't')
                ->where('t.id_profissional', '=', $userId)
                ->first();
        } catch (Throwable) {
            // ===== 2) Fallback portátil (sem window): conta quantos têm total maior e soma 1
            // Para evitar reaproveitar a instância $base (bindings), reconstruímos a subconsulta.
            $base2 = $this->buildBaseSomaPontos($inicio, $fim);

            $rankedFallback = DB::query()
                ->fromSub($base, 'r')
                ->selectRaw(
                // colocação = qtd de totais distintos maiores + 1 (empates têm a mesma colocação - efeito semelhante ao RANK)
                    'r.id_profissional, r.total, (SELECT COUNT(DISTINCT r2.total) + 1 FROM ( ' .
                    $base2->toSql() .
                    ' ) AS r2 WHERE r2.total > r.total) AS colocacao'
                )
                ->mergeBindings($base2); // traz os bindings do $base2 para esta query

            $row = DB::query()
                ->fromSub($rankedFallback, 't')
                ->where('t.id_profissional', '=', $userId)
                ->first();
        }

        $total = $row?->total ?? 0.0;
        $colocacao = $row?->colocacao ?? null;

        $hoje = Carbon::today();
        $diasRestantes = $fim->isFuture() ? $hoje->diffInDays($fim) : 0;

        return [
            'colocacao'          => $colocacao !== null ? (int) $colocacao : null,
            'pontuacao_total'    => number_format((float) $total, 2, ',', '.'),
            'data_fim_campanha'  => $fim->format('d/m/Y'),
            'dias_restantes'     => $diasRestantes,
            'dt_inicio_iso'      => $inicio->toDateString(),
            'dt_fim_iso'         => $fim->toDateString(),
            'periodo_label'      => $label,
        ];
    }

    /**
     * Constrói a subconsulta base com a soma de pontos por profissional,
     * filtrando por status e período. Usa bind de parâmetros (sem datas cruas).
     *
     * @param  Carbon $inicio
     * @param  Carbon $fim
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildBaseSomaPontos(Carbon $inicio, Carbon $fim): Builder
    {
        return DB::table('usuario as u')
            ->leftJoin('pontos as p', function ($join) use ($inicio, $fim) {
                $join->on('p.id_profissional', '=', 'u.id')
                    ->where('p.status', '=', 1)
                    ->whereBetween('p.dt_referencia', [$inicio->toDateString(), $fim->toDateString()]);
            })
            ->where('u.id_perfil', '=', 2) // perfil Profissional
            ->groupBy('u.id')
            ->selectRaw('u.id AS id_profissional, COALESCE(SUM(p.valor), 0) AS total');
    }

    /**
     * Determina a janela atual do Top100.
     * Regra: se mês >= 8 (ago), ciclo = ano atual/ano+1, senão = ano-1/ano.
     *
     * @return array{Carbon, Carbon, string} [inicio, fim, label]
     */
    private function resolverJanelaTop100(): array
    {
        $hoje = Carbon::today();
        $ano  = $hoje->year;

        if ($hoje->month >= 8) {
            $inicio = Carbon::create($ano, 8)->startOfDay();   // 01/08/ano
            $fim    = Carbon::create($ano + 1, 7, 31)->endOfDay(); // 31/07/ano+1
            $label  = sprintf('%d/%d', $ano, $ano + 1);
        } else {
            $inicio = Carbon::create($ano - 1, 8)->startOfDay(); // 01/08/ano-1
            $fim    = Carbon::create($ano, 7, 31)->endOfDay();      // 31/07/ano
            $label  = sprintf('%d/%d', $ano - 1, $ano);
        }

        return [$inicio, $fim, $label];
    }

    /**
     * Retorna o ranking geral com base no prêmio (obrigatório), com filtro opcional por loja.
     *
     * @param \Illuminate\Http\Request $request
     * @return array{
     *   premio: array{id:int,titulo:string,dt_inicio:string,dt_fim:string,pontos:float|int},
     *   dados: array<int, object>
     * }
     */
    public function listar(Request $request): array
    {
        $idPremio = $request->input('id_premio'); // int OU "top100:YYYY"
        $lojaId   = $request->filled('id_loja') ? (int) $request->input('id_loja') : null;

        // Caso especial: prêmio virtual Top 100
        if (is_string($idPremio) && preg_match('/^top100:(\d{4})$/', $idPremio, $m)) {
            $ano = (int) $m[1];
            $dtInicio = Carbon::create($ano, 8)->toDateString();
            $dtFim    = Carbon::create($ano + 1, 7, 31)->toDateString();

            $dados = DB::select('CALL sp_ranking_top100_periodo(?, ?, ?)', [$dtInicio, $dtFim, $lojaId]);

            return [
                'premio' => [
                    'id'        => "top100:$ano",
                    'titulo'    => "Top 100 $ano",
                    'dt_inicio' => $dtInicio,
                    'dt_fim'    => $dtFim,
                    'pontos'    => 0,
                    '_virtual'  => true,
                    '_tipo'     => 'top100',
                    '_ano'      => $ano,
                ],
                'dados' => $dados,
            ];
        }

        // Fluxo normal (prêmio do banco)
        $premio = Premio::findOrFail((int) $idPremio);

        // "Top100 cadastrado" = sem faixas e pontos > 0
        $isTop100 = $this->isTop100($premio);

        if ($isTop100) {
            $dtInicio = $premio->dt_inicio->format('Y-m-d');
            $dtFim    = $premio->dt_fim->format('Y-m-d');
            $dados = DB::select('CALL sp_ranking_top100_periodo(?, ?, ?)', [$dtInicio, $dtFim, $lojaId]);
        } else {
            $dados = DB::select('CALL sp_ranking_geral_profissionais(?, ?)', [(int) $premio->id, $lojaId]);
        }

        return [
            'premio' => [
                'id'        => $premio->id,
                'titulo'    => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim'    => $premio->dt_fim->format('Y-m-d'),
                'pontos'    => $premio->pontos,
            ],
            'dados' => $dados,
        ];
    }



    /**
     * Retorna o ‘ranking’ detalhado (profissionais que atingiram e que não atingiram).
     *
     * @param  Request $request
     * @return array
     */
    public function obterRankingDetalhadoPorPremio(Request $request): array
    {
        $premioId = $request->input('id_premio');
        $premio = Premio::findOrFail($premioId);

        $isTop100 = $this->isTop100($premio);

        if ($isTop100) {
            // Busca única com todos os profissionais
            $linhas = DB::select('CALL sp_ranking_detalhado_top100(?)', [$premioId]);
            $todos = $this->consolidarPorProfissional($linhas);

            return [
                'premio' => [
                    'id'       => $premio->id,
                    'titulo'   => $premio->titulo,
                    'dt_inicio'=> $premio->dt_inicio->format('Y-m-d'),
                    'dt_fim'   => $premio->dt_fim->format('Y-m-d'),
                    'pontos'   => $premio->pontos,
                ],
                'todos' => $todos,
            ];
        }

        // 1. Profissionais que atingiram pelo menos uma faixa
        $linhasAtingiram = DB::select('CALL sp_ranking_detalhado_por_loja(?)', [$premioId]);
        $atingiram = $this->consolidarPorProfissional($linhasAtingiram);

        // 2. Profissionais que não atingiram nenhuma faixa
        $linhasNaoAtingiram = DB::select('CALL sp_ranking_detalhado_nao_atingiram(?)', [$premioId]);
        $naoAtingiram = $this->consolidarPorProfissional($linhasNaoAtingiram);

        return [
            'premio' => [
                'id'       => $premio->id,
                'titulo'   => $premio->titulo,
                'dt_inicio'=> $premio->dt_inicio->format('Y-m-d'),
                'dt_fim'   => $premio->dt_fim->format('Y-m-d'),
                'pontos'   => $premio->pontos,
            ],
            'atingiram'      => $atingiram,
            'nao_atingiram'  => $naoAtingiram,
        ];
    }

    /**
     * Converte linhas de ‘ranking’ em estrutura agrupada por profissional.
     *
     * @param  array $linhas
     * @return array
     */
    private function consolidarPorProfissional(array $linhas): array
    {
        $consolidado = [];

        foreach ($linhas as $linha) {
            $id = $linha->id_profissional;

            if (!isset($consolidado[$id])) {
                $consolidado[$id] = [
                    'id_profissional' => $id,
                    'nome'            => $linha->nome_profissional,
                    'total'           => 0.0,
                    'pontos'          => [],
                ];
            }

            $valor = (float) $linha->total;
            $consolidado[$id]['pontos'][] = [
                'loja'  => $linha->loja,
                'total' => $valor,
            ];

            $consolidado[$id]['total'] += $valor;
        }

        $resultado = array_values($consolidado);
        usort($resultado, fn($a, $b) => $b['total'] <=> $a['total']);

        return $resultado;
    }

    /**
     * Determina se um prêmio é do tipo Top100 (sem faixas e com pontos > 0).
     *
     * @param  Premio $premio
     * @return bool
     */
    private function isTop100(Premio $premio): bool
    {
        return $premio->status === 1
            && is_null(DB::table('premio_faixas')->where('id_premio', $premio->id)->first())
            && $premio->pontos > 0;
    }

    /**
     * Lista prêmios ATIVOS do banco para uso no ranking (sem virtuais).
     *
     * @return array<int, array{id:int,titulo:string,dt_inicio:string,dt_fim:string,pontos:float|int,status:int}>
     */
    public function listarPremiosAtivosBase(): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Premio> $list */
        $list = Premio::query()
            ->ativosNoDia()
            ->where('status', 1)
            ->orderByDesc('dt_inicio')
            ->get();

        return $list->map(function (Premio $p) {
            return [
                'id'        => $p->id,
                'titulo'    => (string) $p->titulo,
                'dt_inicio' => $p->dt_inicio->format('Y-m-d'),
                'dt_fim'    => $p->dt_fim->format('Y-m-d'),
                'pontos'    => (float) $p->pontos,
                'status'    => (int) $p->status,
            ];
        })->values()->all();
    }

    /**
     * Gera os 2 prêmios virtuais Top 100 (atual e próximo) — uso exclusivo no ranking.
     *
     * Regra:
     * - Se hoje ∈ [01/08/Y, 31/07/Y+1], retorna (Top 100 Y/Y+1, Top 100 Y+1/Y+2).
     * - Após 31/07/Y+1, retorna (Top 100 Y+1/Y+2, Top 100 Y+2/Y+3).
     *
     * @return array{0: array<string,mixed>, 1: array<string,mixed>}
     */
    public function buildTop100VirtualPrizes(): array
    {
        $hoje = Carbon::today();
        $anoBase = $hoje->year - 1;

        $atual = $hoje->month >= 8 ? $anoBase : $anoBase - 1;
        $proximo = $atual + 1;

        $mk = static function (int $y): array {
            $inicio = Carbon::create($y, 8)->toDateString();
            $fim    = Carbon::create($y + 1, 7, 31)->toDateString();

            return [
                'id'        => "top100:$y",
                'titulo'    => "Top 100 $y/" . ($y + 1),
                'dt_inicio' => $inicio,
                'dt_fim'    => $fim,
                'pontos'    => 0,
                'status'    => 1,
                '_virtual'  => true,
                '_tipo'     => 'top100',
                '_ano'      => $y,
            ];
        };

        return [$mk($atual), $mk($proximo)];
    }

}
