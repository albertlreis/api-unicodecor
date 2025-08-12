<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type Filtros array{
 *   valor?: string|null,
 *   dt_referencia?: string|null,
 *   dt_referencia_fim?: string|null,
 *   id_concurso?: int|null,
 *   id_loja?: int|null,
 *   id_cliente?: int|null,
 *   id_profissional?: int|null,
 *   per_page?: int|null
 * }
 */
class PontuacaoIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // se quiser, mova regras por perfil para Policy
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'valor'              => ['nullable', 'string', 'max:30'],
            'dt_referencia'      => ['nullable', 'date_format:Y-m-d'],
            'dt_referencia_fim'  => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dt_referencia'],
            'id_concurso'        => ['nullable', 'integer', 'min:1'],
            'id_loja'            => ['nullable', 'integer', 'min:1'],
            'id_cliente'         => ['nullable', 'integer', 'min:1'],
            'id_profissional'    => ['nullable', 'integer', 'min:1'],
            'per_page'           => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => (int) ($this->input('per_page', 10)),
        ]);
    }
}
