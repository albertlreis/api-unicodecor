<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClienteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_pessoa' => ['required', 'in:F,J'],
            'nome'        => ['required', 'string', 'min:3', 'max:150'],

            // documento validado por closure para distinguir CPF/CNPJ
            'documento'   => ['required', 'string', function ($attr, $value, $fail) {
                $tipo = $this->input('tipo_pessoa');
                $digits = preg_replace('/\D+/', '', (string) $value);

                if ($tipo === 'F') {
                    if (strlen($digits) !== 11) {
                        return $fail('CPF deve conter 11 dígitos.');
                    }
                    // Validação CPF (mesma lógica do utils/cpf.ts adaptada):
                    if (preg_match('/^(\d)\1{10}$/', $digits)) {
                        return $fail('CPF inválido.');
                    }
                    $calcDV = function (string $base, int $start) {
                        $sum = 0;
                        for ($i = 0; $i < strlen($base); $i++) $sum += (int)$base[$i] * ($start - $i);
                        $rest = $sum % 11;
                        return $rest < 2 ? 0 : 11 - $rest;
                    };
                    $dv1 = $calcDV(substr($digits, 0, 9), 10);
                    $dv2 = $calcDV(substr($digits, 0, 10), 11);
                    if ($dv1 !== (int)$digits[9] || $dv2 !== (int)$digits[10]) {
                        return $fail('CPF inválido.');
                    }
                    return;
                }

                if ($tipo === 'J') {
                    if (strlen($digits) !== 14) {
                        return $fail('CNPJ deve conter 14 dígitos.');
                    }
                    if (preg_match('/^(\d)\1{13}$/', $digits)) {
                        return $fail('CNPJ inválido.');
                    }
                    $w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
                    $w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];

                    $sum1 = 0;
                    for ($i=0; $i<12; $i++) $sum1 += (int)$digits[$i] * $w1[$i];
                    $dv1 = $sum1 % 11 < 2 ? 0 : 11 - ($sum1 % 11);

                    $sum2 = 0;
                    for ($i=0; $i<13; $i++) $sum2 += (int)$digits[$i] * $w2[$i];
                    $dv2 = $sum2 % 11 < 2 ? 0 : 11 - ($sum2 % 11);

                    if ($dv1 !== (int)$digits[12] || $dv2 !== (int)$digits[13]) {
                        return $fail('CNPJ inválido.');
                    }
                    return;
                }

                return $fail('Tipo de pessoa inválido.');
            }],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_pessoa' => 'tipo de pessoa',
            'nome'        => 'nome completo/razão social',
            'documento'   => 'CPF/CNPJ',
        ];
    }
}
