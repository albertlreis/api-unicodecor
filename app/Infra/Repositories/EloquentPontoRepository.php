<?php

namespace App\Infra\Repositories;

use App\Domain\Pontuacoes\Contracts\PontoRepository;
use App\Domain\Pontuacoes\DTO\PontuacaoFiltro;
use App\Models\Ponto;
use App\Models\Premio;
use App\Support\BRNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Repositório Eloquent para Pontos.
 */
class EloquentPontoRepository implements PontoRepository
{
    /**
     * Mapeia/valida ordenação vinda do filtro para evitar SQL injection e quebras.
     *
     * Regras:
     * - Colunas permitidas: id, dt_referencia, valor, dt_cadastro, dt_edicao.
     * - Direção permitida: asc | desc (default: desc).
     * - Aceita prefixo "-coluna" para direção desc quando order_dir não vier.
     * - Fallback: dt_cadastro desc.
     *
     * @param  string|null $by  Valor recebido (ex.: 'dt_cadastro', '-valor', etc.)
     * @param  string|null $dir Direção recebida ('asc' | 'desc' | null)
     * @return array{0:string,1:string} [ colunaSegura, direcaoSegura ]
     */
    private function resolveOrder(?string $by, ?string $dir): array
    {
        $allowed = [
            'id'             => 'id',
            'dt_referencia'  => 'dt_referencia',
            'valor'          => 'valor',
            'dt_cadastro'    => 'dt_cadastro',
            'dt_edicao'      => 'dt_edicao',
        ];

        $direction = strtolower((string) $dir);
        $direction = $direction === 'asc' ? 'asc' : ($direction === 'desc' ? 'desc' : null);

        $column = $by ? trim(strtolower($by)) : '';

        // Suporte a prefixo "-coluna" => desc
        if ($column !== '' && str_starts_with($column, '-')) {
            $column = ltrim($column, '-');
            $direction = $direction ?? 'desc';
        }

        // Resolve coluna segura + fallbacks
        $safeColumn = $allowed[$column] ?? 'dt_cadastro';
        $direction  = $direction ?? 'desc';

        return [$safeColumn, $direction];
    }

    /**
     * Busca paginada de pontuações com filtros por perfil e ordenação segura.
     *
     * @param  PontuacaoFiltro $filtro
     * @param  int             $perfilId
     * @param  int             $usuarioId
     * @param  int|null        $usuarioLojaId
     * @return LengthAwarePaginator
     */
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
        if (!empty($filtro->valor)) {
            $valor = BRNumber::parseDecimal($filtro->valor);
            $q->where('valor', $valor);
        }

        if ($filtro->valor_min !== null || $filtro->valor_max !== null) {
            $min = $filtro->valor_min ?? 0;
            $max = $filtro->valor_max ?? PHP_FLOAT_MAX;
            $q->whereBetween('valor', [$min, $max]);
        }

        if (!empty($filtro->dt_inicio) && !empty($filtro->dt_fim)) {
            $q->whereBetween('dt_referencia', [$filtro->dt_inicio, $filtro->dt_fim]);
        } elseif (!empty($filtro->dt_inicio)) {
            $q->where('dt_referencia', '>=', $filtro->dt_inicio);
        } elseif (!empty($filtro->dt_fim)) {
            $q->where('dt_referencia', '<=', $filtro->dt_fim);
        }

        if (!empty($filtro->premio_id)) {
            $premio = Premio::query()
                ->select(['dt_inicio', 'dt_fim'])
                ->find($filtro->premio_id);

            if ($premio) {
                $q->whereBetween('dt_referencia', [$premio->dt_inicio, $premio->dt_fim]);
            }
        }

        if (!empty($filtro->loja_id))    { $q->where('id_loja', $filtro->loja_id); }
        if (!empty($filtro->cliente_id)) { $q->where('id_cliente', $filtro->cliente_id); }

        // ---------- Ordenação segura ----------
        [$orderBy, $orderDir] = $this->resolveOrder($filtro->order_by, $filtro->order_dir);

        // Nulls por último quando ordenar por dt_cadastro
        if ($orderBy === 'dt_cadastro') {
            // Em MySQL, FALSE(0)=não-nulo vem antes de TRUE(1)=nulo, mantendo nulos por último
            $q->orderByRaw('dt_cadastro IS NULL');
        }

        $q->with(['profissional', 'loja', 'lojista', 'cliente'])
            ->orderBy($orderBy, $orderDir);

        // Tiebreaker para estabilidade
        if ($orderBy !== 'id') {
            $q->orderBy('id', 'desc');
        }

        // ---------- Paginação (limites seguros) ----------
        $perPage = $filtro->per_page ?: 10;
        $perPage = max(1, min(100, $perPage));

        return $q->paginate($perPage)->withQueryString();
    }
}
