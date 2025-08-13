<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validação simples de CNPJ (dígitos + DV).
 * Aceita com ou sem máscara.
 */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D+/', '', (string) $value);

        if (strlen($cnpj) !== 14) {
            $fail('O campo :attribute deve conter 14 dígitos.');
            return;
        }

        if (preg_match('/^(\\d)\\1{13}$/', $cnpj)) {
            $fail('O :attribute informado é inválido.');
            return;
        }

        // Cálculo DV
        $dv = static function ($cnpj, $posicoes) {
            $soma = 0;
            $pos = $posicoes;
            for ($i = 0; $i < $posicoes - 1; $i++) {
                $soma += $cnpj[$i] * $pos--;
                if ($pos < 2) $pos = 9;
            }
            $resultado = $soma % 11;
            return ($resultado < 2) ? 0 : 11 - $resultado;
        };

        $calc1 = $dv($cnpj, 13);
        $calc2 = $dv($cnpj, 14);

        if ((int)$cnpj[12] !== $calc1 || (int)$cnpj[13] !== $calc2) {
            $fail('O :attribute informado é inválido.');
        }
    }
}
