<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * @phpstan-type PontuacaoUpdatePayload array{
 *   id_profissional?: int,
 *   id_cliente?: int|null,
 *   id_loja?: int|null,
 *   valor?: numeric-string|float|int,
 *   orcamento?: string|null,
 *   dt_referencia?: string
 * }
 */
class PontuacaoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user   = $this->user();
        $perfil = (int)($user->id_perfil ?? 0);

        if ($perfil === 1) {
            return true;
        }

        $ponto = $this->route('ponto');
        if (!$ponto) {
            return false;
        }

        $today = Carbon::today(config('app.timezone'))->toDateString();
        return (substr((string)$ponto->dt_referencia, 0, 10) === $today);
    }

    /**
     * Normalizações e trava de data para não-admin.
     */
    protected function prepareForValidation(): void
    {
        $data   = $this->all();
        $perfil = (int)($this->user()->id_perfil ?? 0);

        if (array_key_exists('valor', $data)) {
            $v = $data['valor'];
            if (is_string($v)) {
                $vNorm = str_replace(['.', ' '], '', $v);
                $vNorm = str_replace(',', '.', $vNorm);
                if ($vNorm === '') {
                    unset($data['valor']);
                } else {
                    $data['valor'] = $vNorm;
                }
            }
        }

        foreach (['id_profissional','id_cliente','id_loja'] as $k) {
            if (array_key_exists($k, $data)) {
                $val = $data[$k];
                if ($val === '' || $val === null) {
                    $data[$k] = null;
                } elseif (is_numeric($val)) {
                    $data[$k] = (int) $val;
                }
            }
        }

        if (array_key_exists('dt_referencia', $data) && is_string($data['dt_referencia'])) {
            $data['dt_referencia'] = substr($data['dt_referencia'], 0, 10);
        }

        if ($perfil !== 1) {
            $data['dt_referencia'] = Carbon::today(config('app.timezone'))->toDateString();
        }

        $this->replace($data);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $perfil = (int)($this->user()->id_perfil ?? 0);
        $today  = Carbon::today(config('app.timezone'))->toDateString();

        $rules = [
            'id_profissional' => ['sometimes','integer','exists:usuario,id'],
            'id_cliente'      => ['sometimes','nullable','integer','exists:usuario,id'],
            'id_loja'         => ['sometimes','nullable','integer','exists:lojas,id'],
            'valor'           => ['sometimes','numeric'],
            'orcamento'       => ['sometimes','nullable','string','max:255'],
            'dt_referencia'   => ['sometimes','date_format:Y-m-d'],
        ];

        if ($perfil !== 1) {
            $rules['dt_referencia'][] = Rule::in([$today]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'valor.numeric' => 'O campo valor deve ser numérico (ex.: 1234.56).',
            'dt_referencia.date_format' => 'A data deve estar no formato YYYY-MM-DD.',
            'dt_referencia.in' => 'Para seu perfil, a data de referência deve ser a data de hoje.',
        ];
    }

    /** @return PontuacaoUpdatePayload */
    public function validated($key = null, $default = null): array
    {
        /** @var array $data */
        $data = parent::validated();
        return $data;
    }
}
