<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type ListaFiltros array{
 *   status?: int,
 *   somente_ativas?: bool,
 *   data_base?: string,
 *   titulo?: string,
 *   ids?: array<int,int>,
 *   ordenar_por?: 'dt_inicio'|'dt_fim'|'titulo'|'id',
 *   orden?: 'asc'|'desc',
 *   page?: int,
 *   per_page?: int,
 *   include_faixas?: bool
 * }
 *
 * Request para GET /me/premios.
 * - Para PERFIL PROFISSIONAL (id=2): usa 'data_base' e as flags 'incluir_*'
 * - Para demais perfis: aceita filtros de lista (mesmos de /premios)
 */
class MePremiosRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Flags específicas da visão do profissional
            'data_base'                   => ['nullable', 'date_format:Y-m-d'],
            'incluir_proximas_faixas'     => ['nullable', 'boolean'],
            'incluir_proximas_campanhas'  => ['nullable', 'boolean'],

            // Filtros para visão de lista (demais perfis)
            'status'         => ['nullable', 'integer', 'in:0,1,2'],
            'somente_ativas' => ['nullable', 'boolean'],
            'titulo'         => ['nullable', 'string'],
            'ids'            => ['nullable', 'array'],
            'ids.*'          => ['integer'],
            'ordenar_por'    => ['nullable', 'string', 'in:dt_inicio,dt_fim,titulo,id'],
            'orden'          => ['nullable', 'string', 'in:asc,desc'],
            'page'           => ['nullable', 'integer', 'min:1'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:200'],
            'include_faixas' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public function messages(): array
    {
        return [
            'data_base.date_format'  => ['O campo data_base deve estar no formato Y-m-d.'],
            'ordenar_por.in'         => ['Campo ordenar_por inválido.'],
            'orden.in'               => ['Campo orden deve ser asc ou desc.'],
        ];
    }
}
