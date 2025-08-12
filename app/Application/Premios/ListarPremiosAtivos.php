<?php

namespace App\Application\Premios;

use App\Domain\Premios\Contracts\PremioRepository;
use Illuminate\Support\Collection;

/**
 * Caso de uso: listar campanhas ativas.
 */
class ListarPremiosAtivos
{
    public function __construct(
        private readonly PremioRepository $repository
    ) {}

    /**
     * Executa o caso de uso.
     *
     * @return Collection<int, \App\Models\Premio>
     */
    public function handle(): Collection
    {
        return $this->repository->listarAtivos();
    }
}
