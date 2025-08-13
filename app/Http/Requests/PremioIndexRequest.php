<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type Filtros array{
 *   status?: int|null,
 *   somente_ativas?: bool|null,
 *   titulo?: string|null,
 *   ordenar_por?: string|null,
 *   orden?: 'asc'|'desc'|null,
 *   page?: int|null,
 *   per_page?: int|null,
 *   include_faixas?: bool|null,
 *   data_base?: string|null,
 *   ids?: array<int>|null
 * }
 */
class PremioIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'          => ['nullable', 'integer', 'in:0,1,2'],
            'somente_ativas'  => ['nullable', 'boolean'],
            'titulo'          => ['nullable', 'string', 'max:200'],

            // nomes oficiais
            'ordenar_por'     => ['nullable', 'in:dt_inicio,dt_fim,titulo,id'],
            'orden'           => ['nullable', 'in:asc,desc'],

            // aliases aceitos pelo front
            'orderBy'         => ['nullable', 'in:dt_inicio,dt_fim,titulo,id'],
            'orderDir'        => ['nullable', 'in:asc,desc'],

            'page'            => ['nullable', 'integer', 'min:1'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_faixas'  => ['nullable', 'boolean'],
            'data_base'       => ['nullable', 'date_format:Y-m-d'],
            'ids'             => ['nullable', 'array'],
            'ids.*'           => ['integer', 'min:1'],
        ];
    }

    /**
     * Normaliza tipos, aplica aliases e defaults.
     */
    protected function prepareForValidation(): void
    {
        // aliases do front → nomes oficiais
        $ordenar = $this->input('ordenar_por', $this->input('orderBy'));
        $orden   = $this->input('orden', $this->input('orderDir'));

        $this->merge([
            'ordenar_por'    => $ordenar ?? 'dt_inicio',
            'orden'          => $orden   ?? 'asc',
            // ⚠️ admin precisa ver tudo por padrão
            'somente_ativas' => $this->toBoolean($this->input('somente_ativas', false)),
            'include_faixas' => $this->toBoolean($this->input('include_faixas')),
            'page'           => (int) $this->input('page', 1),
            'per_page'       => (int) $this->input('per_page', 15),
        ]);
    }

    private function toBoolean(mixed $value): ?bool
    {
        if ($value === null) return null;
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
