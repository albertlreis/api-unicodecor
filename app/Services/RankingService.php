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
}
