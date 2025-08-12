<?php

namespace App\Infra\Repositories;

use App\Domain\Pontuacoes\Contracts\PontoRepository;
use App\Domain\Pontuacoes\DTO\PontuacaoFiltro;
use App\Models\Ponto;
use App\Models\Premio;
use App\Support\BRNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPontoRepository implements PontoRepository
{
    public function buscarPaginado(
        PontuacaoFiltro $filtro,
        int $perfilId,
        int $usuarioId,
        ?int $usuarioLojaId = null
    ): LengthAwarePaginator {
        $q = Ponto::query()->where('status', 1);

        // Regras por perfil
        if ($perfilId === 2) {
            $q->where('id_profissional', $usuarioId);
        } elseif ($perfilId === 3) {
            $q->where(function ($sub) use ($usuarioId, $usuarioLojaId) {
                $sub->where('id_lojista', $usuarioId);
                if ($usuarioLojaId) {
                    $sub->orWhere('id_loja', $usuarioLojaId);
                }
            });
            if ($filtro->id_profissional) {
                $q->where('id_profissional', $filtro->id_profissional);
            }
        } elseif (in_array($perfilId, [1, 3], true) && $filtro->id_profissional) {
            $q->where('id_profissional', $filtro->id_profissional);
        }

        // Filtros
        if (!empty($filtro->valor)) {
            $valor = BRNumber::parseDecimal($filtro->valor);
            $q->where('valor', $valor);
        }

        if (!empty($filtro->dt_referencia) && !empty($filtro->dt_referencia_fim)) {
            $q->whereBetween('dt_referencia', [$filtro->dt_referencia, $filtro->dt_referencia_fim]);
        }

        if (!empty($filtro->id_concurso)) {
            if ($concurso = Premio::find($filtro->id_concurso)) {
                $q->whereBetween('dt_referencia', [$concurso->dt_inicio, $concurso->dt_fim]);
            }
        }

        if (!empty($filtro->id_loja))    $q->where('id_loja', $filtro->id_loja);
        if (!empty($filtro->id_cliente)) $q->where('id_cliente', $filtro->id_cliente);

        return $q->with(['profissional', 'loja', 'lojista', 'cliente'])
            ->orderByDesc('dt_referencia')
            ->paginate($filtro->per_page);
    }
}
