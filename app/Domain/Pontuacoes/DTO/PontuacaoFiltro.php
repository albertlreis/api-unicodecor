<?php

namespace App\Domain\Pontuacoes\DTO;

/**
 * @phpstan-type FiltroArr array{
 *   valor?: string|null,
 *   valor_min?: float|null,
 *   valor_max?: float|null,
 *   dt_inicio?: string|null,
 *   dt_fim?: string|null,
 *   premio_id?: int|null,
 *   loja_id?: int|null,
 *   cliente_id?: int|null,
 *   profissional_id?: int|null,
 *   order_by?: string|null,
 *   order_dir?: string|null,
 *   per_page?: int
 * }
 */
final class PontuacaoFiltro
{
    public function __construct(
        public readonly ?string $valor = null,
        public readonly ?float $valor_min = null,
        public readonly ?float $valor_max = null,
        public readonly ?string $dt_inicio = null,
        public readonly ?string $dt_fim = null,
        public readonly ?int $premio_id = null,
        public readonly ?int $loja_id = null,
        public readonly ?int $cliente_id = null,
        public readonly ?int $profissional_id = null,
        public readonly ?string $order_by = 'dt_cadastro',
        public readonly ?string $order_dir = 'desc',
        public readonly int $per_page = 10,
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            valor: $data['valor'] ?? null,
            valor_min: isset($data['valor_min']) ? (float) $data['valor_min'] : null,
            valor_max: isset($data['valor_max']) ? (float) $data['valor_max'] : null,
            dt_inicio: $data['dt_inicio'] ?? null,
            dt_fim: $data['dt_fim'] ?? null,
            premio_id: isset($data['premio_id']) ? (int) $data['premio_id'] : null,
            loja_id: isset($data['loja_id']) ? (int) $data['loja_id'] : null,
            cliente_id: isset($data['cliente_id']) ? (int) $data['cliente_id'] : null,
            profissional_id: isset($data['profissional_id']) ? (int) $data['profissional_id'] : null,
            order_by: $data['order_by'] ?? 'dt_cadastro',
            order_dir: $data['order_dir'] ?? 'desc',
            per_page: isset($data['per_page']) ? (int) $data['per_page'] : 10,
        );
    }
}
