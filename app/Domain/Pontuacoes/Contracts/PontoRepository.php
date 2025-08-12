<?php

namespace App\Domain\Pontuacoes\Contracts;

use App\Domain\Pontuacoes\DTO\PontuacaoFiltro;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PontoRepository
{
    /**
     * Busca pontos paginados aplicando filtros e regras por perfil.
     *
     * @param PontuacaoFiltro $filtro
     * @param int $perfilId
     * @param int $usuarioId
     * @param int|null $usuarioLojaId  (para perfil lojista)
     */
    public function buscarPaginado(
        PontuacaoFiltro $filtro,
        int $perfilId,
        int $usuarioId,
        ?int $usuarioLojaId = null
    ): LengthAwarePaginator;
}
