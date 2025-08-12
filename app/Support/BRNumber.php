<?php

namespace App\Support;

final class BRNumber
{
    /**
     * Converte "1.234,56" / "1234,56" / "1234.56" para float.
     */
    public static function parseDecimal(string|int|float $valor): float
    {
        if (is_numeric($valor)) return (float) $valor;
        $san = str_replace(['.', ','], ['', '.'], (string) $valor);
        return (float) $san;
    }
}
