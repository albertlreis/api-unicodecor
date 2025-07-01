<?php

namespace App\Services;

use App\Models\Ponto;
use App\Models\Premio;
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
}
