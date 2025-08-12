<?php

namespace App\Domain\Premios\Contracts;

use Illuminate\Support\Collection;

interface PremioRepository
{
    /**
     * Retorna campanhas ativas em 'hoje' (America/Belem).
     *
     * Regra: status=1 AND dt_inicio <= hoje AND (dt_fim IS NULL OR dt_fim >= hoje)
     *
     * @return Collection<int, \App\Models\Premio>
     */
    public function listarAtivos(): Collection;
}
