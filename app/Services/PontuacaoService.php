<?php

namespace App\Services;

use App\Models\Ponto;
use App\Models\Premio;
use App\Models\PremioFaixa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * Retorna as campanhas nas quais o profissional se enquadra em alguma faixa.
     * Agora a lógica parte das faixas, e somente campanhas com faixa atual válida são retornadas.
     *
     * @param int $usuarioId
     * @return array<int, array{
     *     campanha: \App\Models\Premio,
     *     faixa_atual: \App\Models\PremioFaixa|null,
     *     proxima_faixa: \App\Models\PremioFaixa|null,
     *     dias_restantes: int,
     *     pontuacao_total: float
     * }>
     */
    public function obterCampanhasComPontuacao(int $usuarioId): array
    {
        $hoje = Carbon::today();
        $anoAtual = Carbon::now()->year;
        $inicioAno = Carbon::create($anoAtual, 1, 1);
        $fimAno = Carbon::create($anoAtual, 12, 31);

        // Soma da pontuação no ano vigente
        $pontuacaoTotal = Ponto::where('id_profissional', $usuarioId)
            ->whereBetween('dt_referencia', [$inicioAno->toDateString(), $fimAno->toDateString()])
            ->sum('valor');

        // Busca todas as faixas com seus respectivos prêmios ativos no ano vigente
        $faixas = PremioFaixa::with('premio')
            ->whereHas('premio', function ($query) use ($hoje, $anoAtual) {
                $query->where('status', 1)
                    ->whereYear('dt_inicio', '<=', $anoAtual)
                    ->whereYear('dt_fim', '>=', $anoAtual)
                    ->where('dt_inicio', '<=', $hoje)
                    ->where('dt_fim', '>=', $hoje);
            })
            ->orderBy('pontos_min')
            ->get();

        // Faixa atual (a mais alta que ele alcançou)
        $faixaAtual = $faixas->filter(function ($faixa) use ($pontuacaoTotal) {
            return $pontuacaoTotal >= $faixa->pontos_min && $pontuacaoTotal <= $faixa->pontos_max;
        })->sortByDesc('pontos_min')->first();

        // Próxima faixa (a mais próxima que ele ainda não alcançou)
        $proximaFaixa = $faixas->filter(function ($faixa) use ($pontuacaoTotal) {
            return $pontuacaoTotal < $faixa->pontos_min;
        })->sortBy('pontos_min')->first();

        // Define a campanha principal como a da faixa atual (prioritária)
        $campanhaAtual = $faixaAtual?->premio;
        $campanhaProxima = $proximaFaixa?->premio;

        $diasRestantes = $campanhaAtual
            ? Carbon::parse($campanhaAtual->dt_fim)->diffInDays($hoje)
            : ($campanhaProxima ? Carbon::parse($campanhaProxima->dt_fim)->diffInDays($hoje) : 0);

        return [
            'campanha' => $campanhaAtual ?? $campanhaProxima,
            'faixa_atual' => $faixaAtual,
            'proxima_faixa' => $proximaFaixa,
            'dias_restantes' => $diasRestantes,
            'pontuacao_total' => $pontuacaoTotal,
        ];
    }
}
