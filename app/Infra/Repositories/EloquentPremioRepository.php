<?php

namespace App\Infra\Repositories;

use App\Domain\Premios\Contracts\PremioRepository;
use App\Models\Premio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;

class EloquentPremioRepository implements PremioRepository
{
    /**
     * Regra de ATIVO: status=1 AND dt_inicio <= :hoje AND dt_fim >= :hoje
     *
     * @param  array<string,mixed> $filtros
     */
    public function listarPorFiltros(array $filtros): LengthAwarePaginator
    {
        // -------- saneamento ----------
        $ordenarPor = (string)($filtros['ordenar_por'] ?? 'dt_inicio');
        $orden      = strtolower((string)($filtros['orden'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = (int)($filtros['per_page'] ?? 15);
        $perPage = ($perPage >= 1 && $perPage <= 100) ? $perPage : 15;

        $page = (int)($filtros['page'] ?? 1);
        $page = $page >= 1 ? $page : 1;

        $includeFaixas = (bool)($filtros['include_faixas'] ?? false);

        $orderMap = [
            'id'        => 'premios.id',
            'titulo'    => 'premios.titulo',
            'dt_inicio' => 'premios.dt_inicio',
            'dt_fim'    => 'premios.dt_fim',
        ];
        $orderColumn = $orderMap[$ordenarPor] ?? 'premios.dt_inicio';
        $orderDir    = $orden;

        $tz   = Config::get('app.timezone', 'America/Belem');
        $hoje = (string)($filtros['data_base'] ?? now($tz)->toDateString());
        $ativoExpr      = '(premios.status = 1 AND premios.dt_inicio <= ? AND premios.dt_fim >= ?)';
        $finalizadoExpr = '(premios.status = 1 AND premios.dt_fim < ?)';

        $q = Premio::query();

        if ($includeFaixas) {
            $q->with(['faixas' => static fn ($faixas) => $faixas->orderBy('pontos_min')]);
        }

        // -------- filtros ----------
        $temStatusFiltro = array_key_exists('status', $filtros) && $filtros['status'] !== '' && $filtros['status'] !== null;

        if ($temStatusFiltro) {
            $statusInt = (int) $filtros['status'];
            if ($statusInt === 1) {
                // ATIVOS: aplica regra completa (status+datas)
                $q->whereRaw($ativoExpr, [$hoje, $hoje]);
            } else {
                // INATIVOS: status=0 OU finalizados (status=1 e dt_fim < hoje)
                $q->where(static function ($w) use ($finalizadoExpr, $hoje) {
                    $w->where('premios.status', 0)
                        ->orWhereRaw($finalizadoExpr, [$hoje]);
                });
            }
        }

        if (!empty($filtros['ids']) && is_array($filtros['ids'])) {
            $q->whereIn('premios.id', array_filter($filtros['ids'], static fn ($v) => is_numeric($v)));
        }

        // Texto (q/titulo)
        $texto = trim((string)($filtros['q'] ?? ''));
        if ($texto === '' && !empty($filtros['titulo'])) {
            $texto = trim((string)$filtros['titulo']);
        }

        if ($texto !== '') {
            $textoEsc = addcslashes($texto, '%_\\');
            $q->where(static function ($w) use ($textoEsc) {
                $like = "%{$textoEsc}%";
                $w->where('premios.titulo', 'like', $like)
                    ->orWhere('premios.regras', 'like', $like);
            });
        }

        // somente_ativas mantém a regra de ativo usando a data-base
        $somenteAtivas = filter_var($filtros['somente_ativas'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($somenteAtivas) {
            $q->ativosNoDia($hoje);
        }

        // -------- ordenação ----------
        if (!$temStatusFiltro && !$somenteAtivas) {
            // "Todos": ATIVOS primeiro, depois título
            $q->orderByRaw($ativoExpr . ' DESC', [$hoje, $hoje])
                ->orderBy('premios.titulo', 'asc');
        } else {
            $q->orderBy($orderColumn, $orderDir);
        }

        // -------- paginação ----------
        $paginator = $q->paginate(perPage: $perPage, page: $page);
        $paginator->appends($filtros);

        return $paginator;
    }
}
