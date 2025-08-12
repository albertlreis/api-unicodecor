<?php

namespace App\Services;

use App\Models\HistoricoEdicaoPonto;
use App\Models\Ponto;
use App\Models\Premio;
use App\Models\PremioFaixa;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PontuacaoService
{
    /**
     * Busca pontuações com filtros e paginação.
     *
     * @param Request $request
     * @param Authenticatable $user
     * @return LengthAwarePaginator
     */
    public function buscarPontuacoes(Request $request, Authenticatable $user): LengthAwarePaginator
    {
        $query = Ponto::query()->where('status', 1);

        if (!isset($user->id_perfil)) {
            throw new AccessDeniedHttpException('Perfil do usuário não identificado.');
        }

        // Perfis
        if ($user->id_perfil === 2) {
            $query->where('id_profissional', $user->id);
        } elseif ($user->id_perfil === 3) {
            $query->where(function ($q) use ($user) {
                $q->where('id_lojista', $user->id)
                    ->orWhere('id_loja', $user->id_loja);
            });

            if ($request->filled('id_profissional')) {
                $query->where('id_profissional', $request->id_profissional);
            }
        }

        if (in_array($user->id_perfil, [1, 3]) && $request->filled('id_profissional')) {
            $query->where('id_profissional', $request->id_profissional);
        }

        // Filtros
        if ($request->filled('valor')) {
            $valor = str_replace(['.', ','], ['', '.'], $request->valor);
            $query->where('valor', $valor);
        }

        if ($request->filled('dt_referencia') && $request->filled('dt_referencia_fim')) {
            $query->whereBetween('dt_referencia', [
                $request->dt_referencia,
                $request->dt_referencia_fim
            ]);
        }

        if ($request->filled('id_concurso')) {
            $concurso = Premio::find($request->id_concurso);
            if ($concurso) {
                $query->whereBetween('dt_referencia', [
                    $concurso->dt_inicio,
                    $concurso->dt_fim
                ]);
            }
        }

        if ($request->filled('id_loja')) {
            $query->where('id_loja', $request->id_loja);
        }

        if ($request->filled('id_cliente')) {
            $query->where('id_cliente', $request->id_cliente);
        }

        return $query
            ->with(['profissional', 'loja', 'lojista', 'cliente'])
            ->orderByDesc('dt_referencia')
            ->paginate($request->get('per_page', 10));
    }

    /**
     * Retorna a campanha/faixas para o profissional considerando a data base.
     *
     * @param int $usuarioId
     * @param string|null $dataBase ISO Y-m-d (default: hoje)
     * @return array{
     *   campanha: \App\Models\Premio|null,
     *   faixa_atual: \App\Models\PremioFaixa|null,
     *   proxima_faixa: \App\Models\PremioFaixa|null,
     *   dias_restantes: int,
     *   pontuacao_total: float
     * }
     */
    public function obterCampanhasComPontuacao(int $usuarioId, ?string $dataBase = null): array
    {
        $hoje = Carbon::parse($dataBase ?: Carbon::today()->toDateString());
        $ano  = (int) $hoje->format('Y');
        $inicioAno = Carbon::create($ano, 1, 1);
        $fimAno    = Carbon::create($ano, 12, 31);

        // 1) Pontuação do profissional no ANO da data-base (compatível com regra anual)
        $pontuacaoTotal = (float) Ponto::where('id_profissional', $usuarioId)
            ->whereBetween('dt_referencia', [$inicioAno->toDateString(), $fimAno->toDateString()])
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

        // 3) Determina a campanha/faixa atual (a maior faixa cujo min/max contem a pontuação)
        foreach ($campanhasAtivas as $campanha) {
            $faixa = $campanha->faixas
                ->filter(fn(PremioFaixa $f) =>
                    $pontuacaoTotal >= (float)$f->pontos_min
                    && ($f->pontos_max === null || $pontuacaoTotal <= (float)$f->pontos_max)
                )
                ->sortByDesc('pontos_min')
                ->first();

            if ($faixa) {
                $faixaAtual = $faixa;
                $campanhaAtual = $campanha;

                // Próxima faixa dentro da mesma campanha
                $proximaFaixa = $campanha->faixas
                    ->filter(fn(PremioFaixa $f) => $pontuacaoTotal < (float)$f->pontos_min)
                    ->sortBy('pontos_min')
                    ->first();
                break;
            }
        }

        // 4) Caso não tenha faixa atual, assume a campanha que possui a menor faixa acima da pontuação
        if (!$campanhaAtual) {
            $candidato = $campanhasAtivas->map(function ($campanha) use ($pontuacaoTotal) {
                $prox = $campanha->faixas
                    ->filter(fn(PremioFaixa $f) => $pontuacaoTotal < (float)$f->pontos_min)
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

        return [
            'campanha'         => $campanhaAtual,
            'faixa_atual'      => $faixaAtual,
            'proxima_faixa'    => $proximaFaixa,
            'dias_restantes'   => max(0, $diasRestantes),
            'pontuacao_total'  => $pontuacaoTotal,
        ];
    }

    public function salvarPontuacao(array $data, Usuario $usuario): Ponto
    {
        return DB::transaction(function () use ($data, $usuario) {
            $data['id_loja'] = $usuario->id_loja;
            $data['id_lojista'] = $usuario->id;
            $data['dt_edicao'] = now();

            if (isset($data['id'])) {
                $ponto = Ponto::findOrFail($data['id']);

                HistoricoEdicaoPonto::create([
                    'id_pontos' => $ponto->id,
                    'id_usuario_alteracao' => $usuario->id,
                    'valor_anterior' => $ponto->valor,
                    'valor_novo' => $data['valor'],
                    'dt_referencia_anterior' => $ponto->dt_referencia,
                    'dt_referencia_novo' => $data['dt_referencia'],
                    'dt_alteracao' => Carbon::now(),
                ]);

                $ponto->update($data);
            } else {
                $data['dt_cadastro'] = now();
                $data['status'] = 1;
                $ponto = Ponto::create($data);
            }

            return $ponto;
        });
    }
}
