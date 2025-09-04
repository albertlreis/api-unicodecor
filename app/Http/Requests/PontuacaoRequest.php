<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * @phpstan-type PontuacaoPayload array{
 *   valor: float|int|string,
 *   dt_referencia: string,
 *   id_profissional: int,
 *   id_cliente: int,
 *   id_loja?: int|null,
 *   orcamento?: string|null
 * }
 */
class PontuacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perfil = $this->user()->id_perfil ?? null;
        return in_array((int)$perfil, [1, 3], true);
    }

    /**
     * Força dt_referencia = hoje para não-admin antes da validação.
     */
    protected function prepareForValidation(): void
    {
        $perfil = (int)($this->user()->id_perfil ?? 0);
        if ($perfil !== 1) {
            // 🔒 não-admin: sempre hoje
            $today = Carbon::today(config('app.timezone'))->toDateString();
            $data = $this->all();
            $data['dt_referencia'] = $today;
            $this->replace($data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $perfil = (int)($this->user()->id_perfil ?? 0);
        $today  = Carbon::today(config('app.timezone'))->toDateString();

        $rules = [
            'valor'           => ['required', 'numeric', 'min:0.01'],
            'dt_referencia'   => ['required', 'date_format:Y-m-d'],
            'id_profissional' => ['required', 'exists:usuario,id'],
            'id_cliente'      => ['required', 'exists:usuario,id'],
            'orcamento'       => ['nullable', 'string', 'max:255'],
        ];

        if ($perfil === 1) {
            $rules['id_loja'] = ['required', 'exists:lojas,id'];
        } else {
            $rules['id_loja'] = ['nullable', 'exists:lojas,id'];
            $rules['dt_referencia'][] = Rule::in([$today]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'dt_referencia.date_format' => 'A data deve estar no formato YYYY-MM-DD.',
            'dt_referencia.in' => 'Para seu perfil, a data de referência deve ser a data de hoje.',
        ];
    }
}
