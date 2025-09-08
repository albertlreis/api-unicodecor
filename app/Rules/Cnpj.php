<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validação de CNPJ com pesos corretos (14 dígitos, rejeita repetidos, confere DV1 e DV2).
 * Aceita com ou sem máscara.
 */
class Cnpj implements ValidationRule
{
    /**
     * @inheritDoc
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D+/', '', (string) $value);

        if (strlen($cnpj) !== 14) {
            $fail('O campo :attribute deve conter 14 dígitos.');
            return;
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O :attribute informado é inválido.');
            return;
        }

        // Pesos oficiais de CNPJ
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $calc = static function (string $base, array $weights): int {
            $sum = 0;
            foreach ($weights as $i => $w) {
                $sum += ((int) $base[$i]) * $w;
            }
            $rest = $sum % 11;
            return ($rest < 2) ? 0 : (11 - $rest);
        };

        $base12 = substr($cnpj, 0, 12);
        $dv1 = $calc($base12, $weights1);
        $dv2 = $calc($base12 . $dv1, $weights2);

        if ($dv1 !== (int) $cnpj[12] || $dv2 !== (int) $cnpj[13]) {
            $fail('O :attribute informado é inválido.');
        }
    }
}
