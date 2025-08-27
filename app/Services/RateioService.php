<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RateioService
{
    /**
     * Calcula rateio por profissional para um prêmio.
     *
     * @param  int        $idPremio
     * @param  int|null   $idProfissional  (Opcional) filtra por um profissional
     * @return array<int, object>          Linhas retornadas pela stored procedure
     */
    public function rateioPorProfissional(int $idPremio, ?int $idProfissional = null): array
    {
        $linhas = DB::select('CALL sp_rateio_premiacao_profissionais(?)', [$idPremio]);

        if ($idProfissional) {
            $linhas = array_values(array_filter($linhas, fn ($item) => (int) $item->id_profissional === $idProfissional));
        }

        // Garantir ordenação por colocação asc quando houver o campo
        usort($linhas, function ($a, $b) {
            return ($a->colocacao ?? PHP_INT_MAX) <=> ($b->colocacao ?? PHP_INT_MAX);
        });

        return $linhas;
    }

    /**
     * Calcula rateio por loja para um prêmio.
     *
     * @param  int       $idPremio
     * @param  int|null  $idLoja  (Opcional) filtra por uma loja
     * @return array<int, object>
     */
    public function rateioPorLoja(int $idPremio, ?int $idLoja = null): array
    {
        $linhas = DB::select('CALL sp_rateio_premiacao_lojas(?)', [$idPremio]);

        if ($idLoja) {
            $linhas = array_values(array_filter($linhas, fn ($item) => (int) $item->id_loja === $idLoja));
        }

        // Ordena por nome da loja para manter previsível
        usort($linhas, function ($a, $b) {
            return strcmp($a->nome_loja ?? '', $b->nome_loja ?? '');
        });

        return $linhas;
    }

    /**
     * Consolida rateio por loja a partir de múltiplos prêmios.
     *
     * @param  array<int,int>  $idsPremios
     * @return array<int, object>
     */
    public function rateioConsolidado(array $idsPremios): array
    {
        $param = implode(',', array_map('intval', $idsPremios));
        return DB::select('CALL sp_consolidado_rateio_lojas(?)', [$param]);
    }
}
