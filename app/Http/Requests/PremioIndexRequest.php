<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type Filtros array{
 *   q?: string|null,
 *   titulo?: string|null,
 *   status?: int|null,
 *   somente_ativas?: bool|null,
 *   ordenar_por?: 'dt_inicio'|'dt_fim'|'titulo'|'id'|null,
 *   orden?: 'asc'|'desc'|null,
 *   orderBy?: 'dt_inicio'|'dt_fim'|'titulo'|'id'|null,
 *   orderDir?: 'asc'|'desc'|null,
 *   page?: int|null,
 *   per_page?: int|null,
 *   include_faixas?: bool|null,
 *   data_base?: string|null,
 *   incluir_enquadramento?: bool|null,
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
            'q'               => ['nullable', 'string', 'max:200'],
            'titulo'          => ['nullable', 'string', 'max:200'], // legado
            'status'          => ['nullable', 'integer', 'in:0,1'],
            'somente_ativas'  => ['nullable', 'boolean'],

            'ordenar_por'     => ['nullable', 'in:dt_inicio,dt_fim,titulo,id'],
            'orden'           => ['nullable', 'in:asc,desc'],

            'orderBy'         => ['nullable', 'in:dt_inicio,dt_fim,titulo,id'],
            'orderDir'        => ['nullable', 'in:asc,desc'],

            'page'            => ['nullable', 'integer', 'min:1'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_faixas'  => ['nullable', 'boolean'],
            'data_base'       => ['nullable', 'date_format:Y-m-d'],
            'incluir_enquadramento'=> ['nullable', 'boolean'],

            'ids'             => ['nullable', 'array'],
            'ids.*'           => ['integer', 'min:1'],
        ];
    }

    /**
     * Normaliza tipos, aplica aliases e defaults.
     */
    protected function prepareForValidation(): void
    {
        $bool = fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $normStr = function ($v): ?string {
            if ($v === null) return null;
            $s = trim((string) $v);
            return $s === '' ? null : $s;
        };

        $ordenar = $this->input('ordenar_por', $this->input('orderBy'));
        $orden   = $this->input('orden',       $this->input('orderDir'));

        $q       = $normStr($this->input('q'));
        $titulo  = $normStr($this->input('titulo')); // legado

        $page    = (int) ($this->input('page', 1));
        $page    = $page >= 1 ? $page : 1;

        $perPage = (int) ($this->input('per_page', 15));
        if ($perPage < 1)   $perPage = 15;
        if ($perPage > 100) $perPage = 100;

        $somenteAtivas        = $bool($this->input('somente_ativas', false));
        $includeFaixas        = $bool($this->input('include_faixas'));
        $incluirEnquadramento = $bool($this->input('incluir_enquadramento'));

        $status = $this->input('status');
        if ($status !== null && $status !== '') {
            $status = (int) $status;
        } else {
            $status = null;
        }

        $this->merge([
            'q'                    => $q,
            'titulo'               => $titulo,
            'status'               => $status,
            'ordenar_por'          => $ordenar ?? 'dt_inicio',
            'orden'                => in_array($orden, ['asc','desc'], true) ? $orden : 'asc',
            'somente_ativas'       => $somenteAtivas,
            'include_faixas'       => $includeFaixas,
            'incluir_enquadramento'=> $incluirEnquadramento,
            'page'                 => $page,
            'per_page'             => $perPage,
        ]);
    }
}
