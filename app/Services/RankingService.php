<?php

namespace App\Services;

use App\Models\Premio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingService
{
    /**
     * Retorna dados para o card do Top100 na Home, calculando
     * automaticamente o ciclo vigente (01/08 -> 31/07), mesmo
     * sem registro em `premios`.
     *
     * @param  int $userId
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

        // Subconsulta base: total de pontos por profissional no ciclo Top100
        $base = DB::table('usuario as u')
            ->leftJoin('pontos as p', function ($join) use ($inicio, $fim) {
                $join->on('p.id_profissional', '=', 'u.id')
                    ->where('p.status', 1)
                    ->whereBetween('p.dt_referencia', [$inicio->toDateString(), $fim->toDateString()]);
            })
            ->where('u.id_perfil', 2) // perfil Profissional
            ->groupBy('u.id')
            ->selectRaw('u.id as id_profissional, COALESCE(SUM(p.valor),0) as total');

        // Ranking por Window Function (MySQL 8+)
        $ranked = DB::query()
            ->fromSub($base, 'r')
            ->selectRaw('r.id_profissional, r.total, RANK() OVER (ORDER BY r.total DESC) as colocacao');

        // Linha do usuário logado
        $row = DB::query()
            ->fromSub($ranked, 't')
            ->where('t.id_profissional', $userId)
            ->first();

        $total = $row?->total ?? 0.0;
        $colocacao = $row?->colocacao ?? null;

        $hoje = Carbon::today();
        $diasRestantes = $fim->isFuture() ? $hoje->diffInDays($fim) : 0;

        return [
            'colocacao'          => $colocacao ? (int)$colocacao : null,
            'pontuacao_total'    => number_format((float)$total, 2, ',', '.'),
            'data_fim_campanha'  => $fim->format('d/m/Y'),
            'dias_restantes'     => $diasRestantes,
            // Extras úteis para front
            'dt_inicio_iso'      => $inicio->toDateString(),
            'dt_fim_iso'         => $fim->toDateString(),
            'periodo_label'      => $label, // ex.: "2025/2026"
        ];
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
            $inicio = Carbon::create($ano, 8, 1)->startOfDay();      // 01/08/ano
            $fim    = Carbon::create($ano + 1, 7, 31)->endOfDay();    // 31/07/ano+1
            $label  = sprintf('%d/%d', $ano, $ano + 1);
        } else {
            $inicio = Carbon::create($ano - 1, 8, 1)->startOfDay();   // 01/08/ano-1
            $fim    = Carbon::create($ano, 7, 31)->endOfDay();        // 31/07/ano
            $label  = sprintf('%d/%d', $ano - 1, $ano);
        }

        return [$inicio, $fim, $label];
    }

    /**
     * Retorna o ranking geral com base no prêmio (obrigatório).
     *
     * @param Request $request
     * @return array
     */
    public function listar(Request $request): array
    {
        $idPremio = $request->input('id_premio');
        $premio = Premio::findOrFail($idPremio);

        $isTop100 = $this->isTop100($premio);

        $dados = $isTop100
            ? DB::select('CALL sp_ranking_top100_profissionais(?)', [$idPremio])
            : DB::select('CALL sp_ranking_geral_profissionais(?)', [$idPremio]);

        return [
            'premio' => [
                'id' => $premio->id,
                'titulo' => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim' => $premio->dt_fim->format('Y-m-d'),
                'pontos' => $premio->pontos,
            ],
            'dados' => $dados,
        ];
    }

    /**
     * Retorna o ranking detalhado (profissionais que atingiram e que não atingiram).
     *
     * @param Request $request
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
                    'id' => $premio->id,
                    'titulo' => $premio->titulo,
                    'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                    'dt_fim' => $premio->dt_fim->format('Y-m-d'),
                    'pontos' => $premio->pontos,
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
                'id' => $premio->id,
                'titulo' => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim' => $premio->dt_fim->format('Y-m-d'),
                'pontos' => $premio->pontos,
            ],
            'atingiram' => $atingiram,
            'nao_atingiram' => $naoAtingiram,
        ];
    }

    /**
     * Converte linhas de ranking em estrutura agrupada por profissional.
     *
     * @param array $linhas
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
                    'nome' => $linha->nome_profissional,
                    'total' => 0.0,
                    'pontos' => [],
                ];
            }

            $valor = floatval($linha->total);
            $consolidado[$id]['pontos'][] = [
                'loja' => $linha->loja,
                'total' => $valor,
            ];

            $consolidado[$id]['total'] += $valor;
        }

        $resultado = array_values($consolidado);
        usort($resultado, fn($a, $b) => $b['total'] <=> $a['total']);

        return $resultado;
    }

    private function isTop100(Premio $premio): bool
    {
        return $premio->status === 1 &&
            is_null(DB::table('premio_faixas')->where('id_premio', $premio->id)->first()) &&
            $premio->pontos > 0;
    }
}
