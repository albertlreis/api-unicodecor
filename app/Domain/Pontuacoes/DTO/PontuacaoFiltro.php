<?php

namespace App\Domain\Pontuacoes\DTO;

/**
 * @phpstan-type FiltroArr array{
 *   valor?: string|null,
 *   dt_referencia?: string|null,
 *   dt_referencia_fim?: string|null,
 *   id_concurso?: int|null,
 *   id_loja?: int|null,
 *   id_cliente?: int|null,
 *   id_profissional?: int|null,
 *   per_page?: int
 * }
 */
final class PontuacaoFiltro
{
    public function __construct(
        public readonly ?string $valor = null,
        public readonly ?string $dt_referencia = null,
        public readonly ?string $dt_referencia_fim = null,
        public readonly ?int $id_concurso = null,
        public readonly ?int $id_loja = null,
        public readonly ?int $id_cliente = null,
        public readonly ?int $id_profissional = null,
        public readonly int $per_page = 10,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            valor: $data['valor'] ?? null,
            dt_referencia: $data['dt_referencia'] ?? null,
            dt_referencia_fim: $data['dt_referencia_fim'] ?? null,
            id_concurso: isset($data['id_concurso']) ? (int)$data['id_concurso'] : null,
            id_loja: isset($data['id_loja']) ? (int)$data['id_loja'] : null,
            id_cliente: isset($data['id_cliente']) ? (int)$data['id_cliente'] : null,
            id_profissional: isset($data['id_profissional']) ? (int)$data['id_profissional'] : null,
            per_page: isset($data['per_page']) ? (int)$data['per_page'] : 10,
        );
    }
}
