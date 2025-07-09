<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RateioService
{
    public function rateioPorProfissional(int $idPremio, ?int $idProfissional = null): array
    {
        $linhas = DB::select('CALL sp_rateio_premiacao_profissionais(?)', [$idPremio]);

        if ($idProfissional) {
            return array_filter($linhas, fn($item) => $item->id_profissional == $idProfissional);
        }

        return $linhas;
    }

    public function rateioPorLoja(int $idPremio, ?int $idLoja = null): array
    {
        $linhas = DB::select('CALL sp_rateio_premiacao_lojas(?)', [$idPremio]);

        if ($idLoja) {
            return array_filter($linhas, fn($item) => $item->id_loja == $idLoja);
        }

        return $linhas;
    }

    public function rateioConsolidado(array $idsPremios): array
    {
        $param = implode(',', array_map('intval', $idsPremios));
        return DB::select('CALL sp_consolidado_rateio_lojas(?)', [$param]);
    }
}
