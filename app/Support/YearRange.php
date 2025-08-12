<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Utilitário para obter início/fim do ano da data-base.
 */
final class YearRange
{
    /** @return array{0:string,1:string} [inicioAno, fimAno] no formato Y-m-d */
    public static function forDate(string $isoDate): array
    {
        $d = Carbon::parse($isoDate);
        $inicio = Carbon::create((int)$d->format('Y'))->toDateString();
        $fim    = Carbon::create((int)$d->format('Y'), 12, 31)->toDateString();
        return [$inicio, $fim];
    }
}
