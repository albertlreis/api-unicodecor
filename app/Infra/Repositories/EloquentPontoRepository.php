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

        // ---------- Regras por perfil ----------
        if ($perfilId === 2) { // Profissional
            $q->where('id_profissional', $usuarioId);
        } elseif ($perfilId === 3) { // Lojista
            $q->where(function ($sub) use ($usuarioId, $usuarioLojaId) {
                $sub->where('id_lojista', $usuarioId);
                if ($usuarioLojaId) {
                    $sub->orWhere('id_loja', $usuarioLojaId);
                }
            });

            if ($filtro->profissional_id) {
                $q->where('id_profissional', $filtro->profissional_id);
            }
        } elseif (in_array($perfilId, [1, 3], true) && $filtro->profissional_id) {
            $q->where('id_profissional', $filtro->profissional_id);
        }

        // ---------- Filtros ----------
        // valor (exato) e/ou faixa de valor
        if (!empty($filtro->valor)) {
            $valor = BRNumber::parseDecimal($filtro->valor); // ex.: "1.234,56" -> 1234.56
            $q->where('valor', $valor);
        }

        if ($filtro->valor_min !== null || $filtro->valor_max !== null) {
            $min = $filtro->valor_min ?? 0;
            $max = $filtro->valor_max ?? PHP_FLOAT_MAX;
            $q->whereBetween('valor', [$min, $max]);
        }

        // faixa de datas
        if (!empty($filtro->dt_inicio) && !empty($filtro->dt_fim)) {
            $q->whereBetween('dt_referencia', [$filtro->dt_inicio, $filtro->dt_fim]);
        } elseif (!empty($filtro->dt_inicio)) {
            $q->where('dt_referencia', '>=', $filtro->dt_inicio);
        } elseif (!empty($filtro->dt_fim)) {
            $q->where('dt_referencia', '<=', $filtro->dt_fim);
        }

        // filtra por campanha (premio): mapeia para intervalo de datas
        if (!empty($filtro->premio_id)) {
            $premio = Premio::query()->select(['dt_inicio', 'dt_fim'])->find($filtro->premio_id);
            if ($premio) {
                $q->whereBetween('dt_referencia', [$premio->dt_inicio, $premio->dt_fim]);
            }
        }

        if (!empty($filtro->loja_id))    { $q->where('id_loja', $filtro->loja_id); }
        if (!empty($filtro->cliente_id)) { $q->where('id_cliente', $filtro->cliente_id); }

        // ---------- Ordenação segura ----------
        $orderBy  = in_array($filtro->order_by, ['dt_referencia', 'valor', 'id'], true) ? $filtro->order_by : 'dt_referencia';
        $orderDir = in_array($filtro->order_dir, ['asc', 'desc'], true) ? $filtro->order_dir : 'desc';

        $q->with(['profissional', 'loja', 'lojista', 'cliente'])
            ->orderBy($orderBy, $orderDir)
            ->orderBy('id', 'desc'); // estabiliza ordenação

        // ---------- Paginação ----------
        return $q->paginate($filtro->per_page)->withQueryString();
    }
}
