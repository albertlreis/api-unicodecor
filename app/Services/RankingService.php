<?php

namespace App\Services;

use App\Models\Premio;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Serviço de ranking/top100.
 *
 * Regras importantes:
 * - Escopo 'geral' NUNCA envia id_loja para a procedure.
 * - Escopo 'loja':
 *   - Lojista (perfil 3) fica travado na própria loja (Auth::user()->loja_id).
 *   - Admin (perfil 1) deve informar id_loja explicitamente.
 * - Segurança aplicada no backend (não confiar apenas no front).
 */
class RankingService
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
     * Retorna dados para o card do Top100 na Home, calculando automaticamente o ciclo vigente
     * (01/08 → 31/07), mesmo sem registro em `premios`.
     *
     * @param  int $userId
     * @return array{
     *   colocacao: int|null,
     *   pontuacao_total: int,
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
        $base = $this->buildBaseSomaPontos($inicio, $fim);

        // 1) Com window function
        try {
            $ranked = DB::query()
                ->fromSub($base, 'r')
                ->selectRaw('r.id_profissional, r.total, RANK() OVER (ORDER BY r.total DESC) AS colocacao');

            $row = DB::query()
                ->fromSub($ranked, 't')
                ->where('t.id_profissional', '=', $userId)
                ->first();
        } catch (Throwable) {
            // 2) Fallback sem window
            $base2 = $this->buildBaseSomaPontos($inicio, $fim);

            $rankedFallback = DB::query()
                ->fromSub($base, 'r')
                ->selectRaw(
                    'r.id_profissional, r.total, (SELECT COUNT(DISTINCT r2.total) + 1 FROM ( ' .
                    $base2->toSql() .
                    ' ) AS r2 WHERE r2.total > r.total) AS colocacao'
                )
                ->mergeBindings($base2);

            $row = DB::query()
                ->fromSub($rankedFallback, 't')
                ->where('t.id_profissional', '=', $userId)
                ->first();
        }

        $total      = $row?->total ?? 0.0;
        $colocacao  = $row?->colocacao ?? null;
        $totalInt   = $this->toIntPoints($total);

        $hoje = Carbon::today();
        $diasRestantes = $fim->isFuture() ? $hoje->diffInDays($fim) : 0;

        return [
            'colocacao'          => $colocacao !== null ? (int) $colocacao : null,
            // agora inteiro
            'pontuacao_total'    => $totalInt,
            'data_fim_campanha'  => $fim->format('d/m/Y'),
            'dias_restantes'     => $diasRestantes,
            'dt_inicio_iso'      => $inicio->toDateString(),
            'dt_fim_iso'         => $fim->toDateString(),
            'periodo_label'      => $label,
        ];
    }

    /**
     * Constrói a subconsulta base com a soma de pontos por profissional.
     *
     * @param Carbon $inicio
     * @param Carbon $fim
     * @param int|null $lojaId
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildBaseSomaPontos(Carbon $inicio, Carbon $fim, ?int $lojaId = null): Builder
    {
        return DB::table('usuario as u')
            ->leftJoin('pontos as p', function ($join) use ($inicio, $fim, $lojaId) {
                $join->on('p.id_profissional', '=', 'u.id')
                    ->where('p.status', 1)
                    ->whereBetween('p.dt_referencia', [$inicio->toDateString(), $fim->toDateString()]);
                if ($lojaId) {
                    $join->whereRaw('COALESCE(p.id_loja, u.id_loja) = ?', [$lojaId]);
                }
            })
            ->where('u.id_perfil', 2) // Profissional
            ->groupBy('u.id', 'u.nome')
            ->selectRaw('u.id AS id_profissional, u.nome AS nome_profissional, COALESCE(SUM(p.valor), 0) AS total');
    }

    /**
     * Determina a janela atual do Top100.
     *
     * @return array{Carbon, Carbon, string}
     */
    private function resolverJanelaTop100(): array
    {
        $hoje = Carbon::today();
        $ano  = $hoje->year;

        if ($hoje->month >= 8) {
            $inicio = Carbon::create($ano, 8)->startOfDay();        // 01/08/ano
            $fim    = Carbon::create($ano + 1, 7, 31)->endOfDay();  // 31/07/ano+1
            $label  = sprintf('%d/%d', $ano, $ano + 1);
        } else {
            $inicio = Carbon::create($ano - 1, 8)->startOfDay();    // 01/08/ano-1
            $fim    = Carbon::create($ano, 7, 31)->endOfDay();      // 31/07/ano
            $label  = sprintf('%d/%d', $ano - 1, $ano);
        }

        return [$inicio, $fim, $label];
    }

    /**
     * Retorna o ranking com base no prêmio (obrigatório), considerando o escopo.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array{
     *   premio: array{id:int|string,titulo:string,dt_inicio:string,dt_fim:string,pontos:int,_virtual?:bool,_tipo?:string,_ano?:int},
     *   dados: array<int, object>
     * }
     */
    public function listar(Request $request): array
    {
        $idPremio = $request->input('id_premio');
        $escopo   = $request->input('escopo', 'geral'); // 'geral' | 'loja'
        $lojaId   = $request->filled('id_loja') ? (int) $request->input('id_loja') : null;

        $user     = Auth::user();
        $perfilId = (int) ($user->perfil_id ?? 0);
        $isAdmin  = $perfilId === 1;
        $isLojista= $perfilId === 3;

        if ($escopo === 'geral') {
            $lojaId = null;
        } else { // escopo === 'loja'
            if ($isLojista) {
                $lojaId = (int) ($user->loja_id ?? 0) ?: null;
            } elseif ($isAdmin) {
                if (!$lojaId) {
                    abort(422, 'Loja obrigatória no escopo por loja.');
                }
            } else {
                if (!$lojaId) {
                    abort(422, 'Loja obrigatória no escopo por loja.');
                }
            }
        }

        // Caso especial: prêmio virtual Top 100 - formato "top100:YYYY"
        if (is_string($idPremio) && preg_match('/^top100:(\d{4})$/', $idPremio, $m)) {
            $ano      = (int) $m[1];
            $dtInicio = Carbon::create($ano, 8)->toDateString();
            $dtFim    = Carbon::create($ano + 1, 7, 31)->toDateString();

            $dados = DB::select('CALL sp_ranking_top100_periodo(?, ?, ?)', [$dtInicio, $dtFim, $lojaId]);

            return [
                'premio' => [
                    'id'        => "top100:$ano",
                    'titulo'    => "Top 100 $ano",
                    'dt_inicio' => $dtInicio,
                    'dt_fim'    => $dtFim,
                    // mantém 0 como inteiro
                    'pontos'    => 0,
                    '_virtual'  => true,
                    '_tipo'     => 'top100',
                    '_ano'      => $ano,
                ],
                'dados' => $dados,
            ];
        }

        /** @var \App\Models\Premio $premio */
        $premio   = Premio::findOrFail((int) $idPremio);
        $isTop100 = $this->isTop100($premio);

        if ($isTop100) {
            $dtInicio = $premio->dt_inicio->format('Y-m-d');
            $dtFim    = $premio->dt_fim->format('Y-m-d');
            $dados    = DB::select('CALL sp_ranking_top100_periodo(?, ?, ?)', [$dtInicio, $dtFim, $lojaId]);
        } else {
            $dados    = DB::select('CALL sp_ranking_geral_profissionais(?, ?)', [(int) $premio->id, $lojaId]);
        }

        return [
            'premio' => [
                'id'        => $premio->id,
                'titulo'    => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim'    => $premio->dt_fim->format('Y-m-d'),
                // agora inteiro
                'pontos'    => $this->toIntPoints($premio->pontos),
            ],
            'dados' => $dados,
        ];
    }

    /**
     * Retorna o ranking detalhado (profissionais que atingiram e que não atingiram).
     *
     * @param  Request $request
     * @return array{
     *   premio: array{id:int,titulo:string,dt_inicio:string,dt_fim:string,pontos:int},
     *   atingiram?: array<int, array<string,mixed>>,
     *   nao_atingiram?: array<int, array<string,mixed>>,
     *   todos?: array<int, array<string,mixed>>
     * }
     */
    public function obterRankingDetalhadoPorPremio(Request $request): array
    {
        $premioId = $request->input('id_premio');
        /** @var \App\Models\Premio $premio */
        $premio = Premio::findOrFail($premioId);

        $isTop100 = $this->isTop100($premio);

        if ($isTop100) {
            $linhas = DB::select('CALL sp_ranking_detalhado_top100(?)', [$premioId]);
            $todos = $this->consolidarPorProfissional($linhas);

            return [
                'premio' => [
                    'id'        => $premio->id,
                    'titulo'    => $premio->titulo,
                    'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                    'dt_fim'    => $premio->dt_fim->format('Y-m-d'),
                    // inteiro
                    'pontos'    => $this->toIntPoints($premio->pontos),
                ],
                'todos' => $todos,
            ];
        }

        $linhasAtingiram    = DB::select('CALL sp_ranking_detalhado_por_loja(?)', [$premioId]);
        $atingiram          = $this->consolidarPorProfissional($linhasAtingiram);

        $linhasNaoAtingiram = DB::select('CALL sp_ranking_detalhado_nao_atingiram(?)', [$premioId]);
        $naoAtingiram       = $this->consolidarPorProfissional($linhasNaoAtingiram);

        return [
            'premio' => [
                'id'        => $premio->id,
                'titulo'    => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim'    => $premio->dt_fim->format('Y-m-d'),
                // inteiro
                'pontos'    => $this->toIntPoints($premio->pontos),
            ],
            'atingiram'     => $atingiram,
            'nao_atingiram' => $naoAtingiram,
        ];
    }

    /**
     * Converte linhas de ranking em estrutura agrupada por profissional.
     *
     * @param  array<int, object> $linhas
     * @return array<int, array{
     *   id_profissional:int,
     *   nome:string,
     *   total:int,
     *   pontos: array<int, array{loja:string,total:int}>
     * }>
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
                    // total inicia inteiro
                    'total'           => 0,
                    'pontos'          => [],
                ];
            }

            $valorInt = $this->toIntPoints($linha->total ?? 0);

            $consolidado[$id]['pontos'][] = [
                'loja'  => $linha->loja,
                'total' => $valorInt,
            ];

            $consolidado[$id]['total'] += $valorInt;
        }

        $resultado = array_values($consolidado);
        usort($resultado, fn($a, $b) => $b['total'] <=> $a['total']);

        return $resultado;
    }

    private function isTop100(Premio $premio): bool
    {
        return $premio->status === 1
            && is_null(DB::table('premio_faixas')->where('id_premio', $premio->id)->first())
            && (float) $premio->pontos > 0;
    }

    /**
     * Lista prêmios ATIVOS do banco para uso no ranking (sem virtuais).
     *
     * @return array<int, array{id:int,titulo:string,dt_inicio:string,dt_fim:string,pontos:int,status:int}>
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
                // inteiro
                'pontos'    => $this->toIntPoints($p->pontos),
                'status'    => (int) $p->status,
            ];
        })->values()->all();
    }

    /**
     * Gera os 2 prêmios virtuais Top 100 (atual e próximo) — uso exclusivo no ranking.
     *
     * @return array{0: array<string,mixed>, 1: array<string,mixed>}
     */
    public function buildTop100VirtualPrizes(): array
    {
        $hoje    = Carbon::today();
        $anoBase = $hoje->year - 1;

        $atual   = $hoje->month >= 8 ? $anoBase : $anoBase - 1;
        $proximo = $atual + 1;

        $mk = function (int $y): array {
            $inicio = Carbon::create($y, 8)->toDateString();
            $fim    = Carbon::create($y + 1, 7, 31)->toDateString();

            return [
                'id'        => "top100:$y",
                'titulo'    => "Top 100 $y/" . ($y + 1),
                'dt_inicio' => $inicio,
                'dt_fim'    => $fim,
                // inteiro
                'pontos'    => 0,
                'status'    => 1,
                '_virtual'  => true,
                '_tipo'     => 'top100',
                '_ano'      => $y,
            ];
        };

        return [$mk($atual), $mk($proximo)];
    }

    /** Resolve intervalo p/ período v2. @return array{inicio:Carbon,fim:Carbon,tipo:string,rotulo:string} */
    private function resolverJanelaPeriodoV2(string $periodo): array
    {
        $hoje = Carbon::today();

        if ($periodo === 'ano') {
            $inicio = Carbon::create($hoje->year)->startOfDay();
            $fim    = Carbon::create($hoje->year, 12, 31)->endOfDay();
            return ['inicio'=>$inicio,'fim'=>$fim,'tipo'=>'ano','rotulo'=>strval($hoje->year)];
        }

        // Top100: 01/08/YY -> 31/07/YY+1
        $baseAno = $hoje->month >= 8 ? $hoje->year : $hoje->year - 1;
        if ($periodo === 'top100_anterior') $baseAno -= 1;
        $inicio = Carbon::create($baseAno, 8, 1)->startOfDay();
        $fim    = Carbon::create($baseAno + 1, 7, 31)->endOfDay();

        return ['inicio'=>$inicio,'fim'=>$fim,'tipo'=>'top100','rotulo'=>sprintf('%d/%d',$baseAno,$baseAno+1)];
    }

    /**
     * Enquadra o profissional em UMA campanha anual (entre as 3), baseada nas faixas.
     * Critério: maior faixa cujo pontos_min <= total <= pontos_max (ou pontos_max NULL).
     * Retorna [premio_id,titulo,faixaDescricao|null] ou null se não enquadrar.
     *
     * @param array<int, array{id:int,titulo:string}> $campanhas
     */
    private function enquadrarEmCampanhaAnual(int $totalInt, array $campanhas): ?array
    {
        if (empty($campanhas)) return null;

        // Busca faixas dos prêmios anuais em 1 query
        $premioIds = array_column($campanhas, 'id');
        $faixas = DB::table('premio_faixas')
            ->whereIn('id_premio', $premioIds)
            ->orderBy('pontos_min', 'desc')
            ->get(['id_premio','descricao','pontos_min','pontos_max']);

        $melhor = null;
        foreach ($faixas as $f) {
            $min = (int) round((float) $f->pontos_min, 0, PHP_ROUND_HALF_UP);
            $max = is_null($f->pontos_max) ? null : (int) round((float) $f->pontos_max, 0, PHP_ROUND_HALF_UP);
            $ok  = $totalInt >= $min && ($max === null || $totalInt <= $max);
            if ($ok) {
                $prem = array_values(array_filter($campanhas, fn($c) => $c['id'] === $f->id_premio))[0] ?? null;
                if ($prem) {
                    $melhor = [
                        'premio_id' => $prem['id'],
                        'premio_titulo' => $prem['titulo'],
                        'faixa' => (string) $f->descricao,
                    ];
                    break; // como ordenamos por pontos_min desc, o primeiro que bater é o melhor
                }
            }
        }
        return $melhor;
    }

    /**
     * v2: Lista o ranking agrupado por campanha/top100/loja, com colocação (dense rank) reiniciando em 1 dentro de cada grupo.
     *
     * Regras:
     * - Lojista (perfil 3): sempre escopo 'loja', força id_loja do usuário, retorna um único grupo "loja".
     * - Admin/Secretaria: escolhem escopo ('geral'|'loja') e período ('ano'|'top100_atual'|'top100_anterior').
     *   Para 'ano', os profissionais são enquadrados em UMA campanha anual (faixas) e cada campanha vira um grupo.
     *   Para 'top100_*', retorna grupo único "Top 100".
     *
     * Ordenação adicional:
     * - Em período 'ano', as campanhas são ordenadas pela maior exigência de pontos para entrada:
     *   usa-se MIN(pontos_min) de suas faixas como “pontos de entrada” e ordena desc.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string,mixed>
     */
    public function listarV2(Request $request): array
    {
        $escopo = $request->string('escopo')->toString();

        $user      = Auth::user();
        $perfilId  = (int) ($user->id_perfil ?? 0);
        $isAdmin   = $perfilId === 1;
        $isLojista = $perfilId === 3;

        if ($isLojista) {
            $inicio  = Carbon::parse($request->input('data_inicio'));
            $fim     = Carbon::parse($request->input('data_fim'))->endOfDay();
            $periodo = null;
        } else {
            $periodo = $request->string('periodo')->toString() ?: 'ano';
            ['inicio' => $inicio, 'fim' => $fim] = $this->resolverJanelaPeriodoV2($periodo);

            if ($escopo === 'loja' && $request->filled('data_inicio') && $request->filled('data_fim')) {
                $inicio  = Carbon::parse($request->input('data_inicio'));
                $fim     = Carbon::parse($request->input('data_fim'))->endOfDay();
                $periodo = null;
            }
        }

        $lojaId = null;
        if ($escopo === 'loja') {
            $lojaId = $request->filled('id_loja') ? (int) $request->input('id_loja') : null;
        }
        if ($isLojista) {
            $escopo = 'loja';
            $lojaId = $request->filled('id_loja') ? (int) $request->input('id_loja') : $lojaId;
        }

        $base = $this->buildBaseSomaPontos($inicio, $fim, $lojaId);
        $rows = DB::query()
            ->fromSub($base, 'b')
            ->select('*')
            ->orderByDesc('total')
            ->orderBy('id_profissional')
            ->get();

        $profissionais = [];
        foreach ($rows as $r) {
            $profissionais[] = [
                'id_profissional'   => (int) $r->id_profissional,
                'nome_profissional' => (string) $r->nome_profissional,
                'pontuacao'         => $this->toIntPoints($r->total),
                'faixa'             => null,
                'campanha'          => null,
                'colocacao'         => null,
            ];
        }

        // Remove pontuação 0
        $profissionais = array_values(array_filter(
            $profissionais,
            static fn ($p) => (int)($p['pontuacao'] ?? 0) > 0
        ));

        // Caso Lojista: 1 grupo (loja)
        if ($isLojista) {
            usort(
                $profissionais,
                fn ($a, $b) => ($b['pontuacao'] <=> $a['pontuacao'])
                    ?: ($a['id_profissional'] <=> $b['id_profissional'])
            );
            $this->applyDenseRank($profissionais);

            return [
                'meta' => [
                    'escopo'    => $escopo,
                    'dt_inicio' => $inicio->toDateString(),
                    'dt_fim'    => $fim->toDateString(),
                    'periodo'   => null,
                ],
                'campanhas' => [[
                    'id'            => 'loja',
                    'titulo'        => 'Ranking da loja',
                    'dt_inicio'     => $inicio->toDateString(),
                    'dt_fim'        => $fim->toDateString(),
                    'tipo'          => 'loja',
                    'profissionais' => $profissionais,
                ]],
            ];
        }

        $campanhasOut = [];

        // Top100 => 1 grupo
        if ($periodo && str_starts_with($periodo, 'top100')) {
            usort(
                $profissionais,
                fn ($a, $b) => ($b['pontuacao'] <=> $a['pontuacao'])
                    ?: ($a['id_profissional'] <=> $b['id_profissional'])
            );
            $this->applyDenseRank($profissionais);

            $campanhasOut[] = [
                'id'            => 'top100',
                'titulo'        => 'Top 100',
                'dt_inicio'     => $inicio->toDateString(),
                'dt_fim'        => $fim->toDateString(),
                'tipo'          => 'top100',
                'profissionais' => $profissionais,
            ];
        } else {
            // Período do ano => N campanhas
            $ano = $inicio->year;

            // 1) Busca campanhas do ano vigente
            $premiosAnuais = Premio::query()
                ->whereYear('dt_inicio', '=', $ano)
                ->whereYear('dt_fim',    '=', $ano)
                ->where('status', 1)
                ->orderBy('id')
                ->get(['id', 'titulo', 'dt_inicio', 'dt_fim', 'pontos']);

            // 2) Calcula a “pontuação mínima de entrada” por campanha = MIN(pontos_min) das faixas
            $idsPremios = $premiosAnuais->pluck('id')->all();
            $minEntradaPorPremio = empty($idsPremios)
                ? []
                : DB::table('premio_faixas')
                    ->whereIn('id_premio', $idsPremios)
                    ->selectRaw('id_premio, MIN(pontos_min) AS min_req')
                    ->groupBy('id_premio')
                    ->pluck('min_req', 'id_premio')
                    ->map(fn ($v) => (float) $v)
                    ->all();

            // 3) Ordena campanhas: maior exigência -> menor
            $premiosOrdenados = $premiosAnuais->all();
            usort($premiosOrdenados, function (Premio $a, Premio $b) use ($minEntradaPorPremio) {
                $aKey = $minEntradaPorPremio[$a->id] ?? (is_null($a->pontos) ? -INF : (float) $a->pontos);
                $bKey = $minEntradaPorPremio[$b->id] ?? (is_null($b->pontos) ? -INF : (float) $b->pontos);

                // desc por “exigência de pontos”
                return ($bKey <=> $aKey) ?: ($a->id <=> $b->id);
            });

            // 4) Constrói base de campanhas já na ordem desejada
            $campBase = array_map(static function (Premio $p) {
                return [
                    'id'            => (int) $p->id,
                    'titulo'        => (string) $p->titulo,
                    'dt_inicio'     => Carbon::parse($p->dt_inicio)->format('Y-m-d'),
                    'dt_fim'        => Carbon::parse($p->dt_fim)->format('Y-m-d'),
                    'tipo'          => 'campanha',
                    'profissionais' => [],
                ];
            }, $premiosOrdenados);

            if ($periodo === null && $escopo === 'loja') {
                // janela manual + loja => 1 grupo (loja)
                usort(
                    $profissionais,
                    fn ($a, $b) => ($b['pontuacao'] <=> $a['pontuacao'])
                        ?: ($a['id_profissional'] <=> $b['id_profissional'])
                );
                $this->applyDenseRank($profissionais);

                $campanhasOut[] = [
                    'id'            => 'loja',
                    'titulo'        => 'Ranking da loja',
                    'dt_inicio'     => $inicio->toDateString(),
                    'dt_fim'        => $fim->toDateString(),
                    'tipo'          => 'loja',
                    'profissionais' => $profissionais,
                ];
            } else {
                // 5) Enquadra profissionais em UMA campanha (faixas)
                $campanhasSlim = array_map(
                    fn ($c) => ['id' => $c['id'], 'titulo' => $c['titulo']],
                    $campBase
                );

                foreach ($profissionais as &$p) {
                    $enq = $this->enquadrarEmCampanhaAnual($p['pontuacao'], $campanhasSlim);
                    if ($enq) {
                        $p['faixa']    = $enq['faixa'];
                        $p['campanha'] = ['id' => $enq['premio_id'], 'titulo' => $enq['premio_titulo']];
                    }
                }
                unset($p);

                // 6) Agrupa profissionais por campanha preservando a ordem “maior exigência -> menor”
                $map = [];
                foreach ($campBase as $c) {
                    $map[$c['id']] = $c;
                }
                foreach ($profissionais as $p) {
                    if ($p['campanha'] && isset($map[$p['campanha']['id']])) {
                        $map[$p['campanha']['id']]['profissionais'][] = $p;
                    }
                }

                // 7) Ordena cada grupo e aplica dense rank
                foreach ($map as &$c) {
                    $c['profissionais'] = array_values(array_filter(
                        $c['profissionais'],
                        static fn ($p) => (int)($p['pontuacao'] ?? 0) > 0
                    ));
                    usort(
                        $c['profissionais'],
                        fn ($a, $b) => ($b['pontuacao'] <=> $a['pontuacao'])
                            ?: ($a['id_profissional'] <=> $b['id_profissional'])
                    );
                    $this->applyDenseRank($c['profissionais']);
                }
                unset($c);

                // 8) Monta saída final respeitando a ordem de $campBase já ordenada por exigência
                $campanhasOut = [];
                foreach ($campBase as $c) {
                    $campanhasOut[] = $map[$c['id']];
                }
            }
        }

        return [
            'meta' => [
                'escopo'    => $escopo,
                'dt_inicio' => $inicio->toDateString(),
                'dt_fim'    => $fim->toDateString(),
                'periodo'   => $periodo,
            ],
            'campanhas' => $campanhasOut,
        ];
    }

    /**
     * Aplica dense rank (1-based) em um array de profissionais, modificando o campo 'colocacao'.
     * O array deve estar ordenado por pontuacao DESC (e opcionalmente por id).
     *
     * @param array<int, array<string,mixed>> $items
     * @return void
     */
    private function applyDenseRank(array &$items): void
    {
        $rank = 0;
        $seq  = 0;
        $prev = null;

        foreach ($items as $i => $p) {
            $seq++;
            $curr = (int) ($p['pontuacao'] ?? 0);
            if ($prev === null || $curr !== $prev) {
                $rank = $seq;     // começa em 1
                $prev = $curr;
            }
            $items[$i]['colocacao'] = $rank;
        }
    }

}
