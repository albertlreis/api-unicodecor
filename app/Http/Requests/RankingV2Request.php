<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @phpstan-type Periodo 'ano'|'top100_atual'|'top100_anterior'
 */
class RankingV2Request extends FormRequest
{
    public function authorize(): bool
    {
        $perfil = (int) $this->user()->id_perfil;
        return in_array($perfil, [1,3,5], true); // Admin, Lojista, Secretaria
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Admin/Secretaria: exige 'periodo'
        // Lojista: exige data_inicio/data_fim
        return [
            'escopo'      => ['required', 'in:geral,loja'],
            'id_loja'     => ['nullable', 'integer', 'exists:lojas,id'],
            'periodo'     => ['nullable', 'in:ano,top100_atual,top100_anterior'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim'    => ['nullable', 'date','after_or_equal:data_inicio'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ((int) $user->id_perfil === 3) {
            $lojaId = $user->id_loja ?? $user->loja_id ?? null;
            if ($lojaId) {
                $this->merge(['id_loja' => (int) $lojaId]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'escopo.required' => 'Informe o escopo da consulta (geral|loja).',
            'periodo.in' => 'Período inválido.',
            'data_fim.after_or_equal' => 'A data final deve ser maior ou igual à inicial.',
        ];
    }
}
