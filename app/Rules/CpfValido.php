<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @phpdoc
 * Regra para validar CPF brasileiro (somente números + dígitos verificadores).
 */
class CpfValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D+/', '', (string) $value ?? '');

        if (strlen($cpf) !== 11) {
            $fail('O :attribute informado é inválido.');
            return;
        }

        // Rejeita CPFs com todos dígitos iguais (111..., 222..., etc.)
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('O :attribute informado é inválido.');
            return;
        }

        // Valida DV
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int)$cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int)$cpf[$t] !== $d) {
                $fail('O :attribute informado é inválido.');
                return;
            }
        }
    }
}
