<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return $this->user() !== null;
    }

    /**
     * Normaliza inputs antes da validação.
     * - valor: "1.234,56" -> "1234.56"
     * - ids: "" -> null; "123" -> 123
     * - dt_referencia: corta HH:mm:ss se vier (mantém Y-m-d)
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();

        // valor: aceita vírgula e milhares
        if (array_key_exists('valor', $data)) {
            $v = $data['valor'];
            if (is_string($v)) {
                $vNorm = str_replace(['.', ' '], '', $v);
                $vNorm = str_replace(',', '.', $vNorm);
                if ($vNorm === '') {
                    // evita falha em 'numeric' quando string vazia for enviada
                    unset($data['valor']);
                } else {
                    $data['valor'] = $vNorm;
                }
            }
        }

        // ids: "" -> null; "123" -> 123
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

        // dt_referencia: garante 'Y-m-d'
        if (array_key_exists('dt_referencia', $data) && is_string($data['dt_referencia'])) {
            $data['dt_referencia'] = substr($data['dt_referencia'], 0, 10);
        }

        $this->replace($data);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_profissional' => ['sometimes','integer','exists:usuario,id'],
            'id_cliente'      => ['sometimes','nullable','integer','exists:usuario,id'],
            'id_loja'         => ['sometimes','nullable','integer','exists:lojas,id'],
            'valor'           => ['sometimes','numeric'],
            'orcamento'       => ['sometimes','nullable','string','max:255'],
            'dt_referencia'   => ['sometimes','date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'valor.numeric' => 'O campo valor deve ser numérico (ex.: 1234.56).',
            'dt_referencia.date_format' => 'A data deve estar no formato YYYY-MM-DD.',
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
