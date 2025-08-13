<?php

namespace App\Infra\Repositories;

use App\Domain\Premios\Contracts\PremioRepository;
use App\Models\Premio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;

class EloquentPremioRepository implements PremioRepository
{
    /**
     * @param array<string,mixed> $filtros
     */
    public function listarPorFiltros(array $filtros): LengthAwarePaginator
    {
        $ordenarPor = $filtros['ordenar_por'] ?? 'dt_inicio';
        $orden      = $filtros['orden'] ?? 'asc';
        $perPage    = (int) ($filtros['per_page'] ?? 15);
        $page       = (int) ($filtros['page'] ?? 1);
        $includeFaixas = (bool) ($filtros['include_faixas'] ?? false);

        // Whitelist definitiva para evitar SQL injection em orderBy
        $orderMap = [
            'id'        => 'premios.id',
            'titulo'    => 'premios.titulo',
            'dt_inicio' => 'premios.dt_inicio',
            'dt_fim'    => 'premios.dt_fim',
        ];
        $orderColumn = $orderMap[$ordenarPor] ?? 'premios.dt_inicio';
        $orderDir    = $orden === 'desc' ? 'desc' : 'asc';

        $q = Premio::query();

        if ($includeFaixas) {
            $q->with(['faixas' => fn($faixas) => $faixas->orderBy('pontos_min')]);
        }

        if (isset($filtros['status'])) {
            $q->where('status', (int) $filtros['status']);
        }

        if (!empty($filtros['ids']) && is_array($filtros['ids'])) {
            $q->whereIn('id', $filtros['ids']);
        }

        if (!empty($filtros['titulo'])) {
            $q->where('titulo', 'like', '%'.$filtros['titulo'].'%');
        }

        $somenteAtivas = filter_var($filtros['somente_ativas'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($somenteAtivas) {
            $tz       = Config::get('app.timezone', 'America/Belem');
            $dataBase = $filtros['data_base'] ?? now($tz)->toDateString();
            $q->ativosNoDia($dataBase);
        }

        $q->orderBy($orderColumn, $orderDir);

        // Importante: sempre paginar para respostas grandes
        return $q->paginate(perPage: $perPage, page: $page);
    }
}
