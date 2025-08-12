<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type Filtros array{
 *   data_base?: string|null,
 *   incluir_proximas_faixas?: bool|null,
 *   incluir_proximas_campanhas?: bool|null
 * }
 */
class FaixasProfissionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Apenas perfil Profissional (id 2)
        $user = $this->user();
        $perfil = (int) ($user->id_perfil ?? $user->perfil_id ?? 0);
        return $user !== null && $perfil === 2;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data_base'                 => ['nullable', 'date_format:Y-m-d'],
            'incluir_proximas_faixas'   => ['nullable', 'boolean'],
            'incluir_proximas_campanhas'=> ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'incluir_proximas_faixas'    => $this->toBool($this->input('incluir_proximas_faixas', true)),
            'incluir_proximas_campanhas' => $this->toBool($this->input('incluir_proximas_campanhas', true)),
        ]);
    }

    private function toBool(mixed $v): ?bool
    {
        if ($v === null) return null;
        return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
