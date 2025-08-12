<?php

namespace App\Domain\Premios\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contrato de acesso a campanhas/prêmios.
 */
interface PremioRepository
{
    /**
     * Lista campanhas conforme filtros livres (para GET /premios).
     *
     * @param array<string, mixed> $filtros
     */
    public function listarPorFiltros(array $filtros): LengthAwarePaginator;
}
