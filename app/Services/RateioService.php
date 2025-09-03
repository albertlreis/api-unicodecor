<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RateioService
{
    /**
     * Calcula rateio por profissional para um prêmio.
     *
     * @param  int        $idPremio
     * @param  int|null   $idProfissional  Filtrar um profissional específico (opcional)
     * @param  int|null   $idLojaRestrita  Se informado, limita os dados a esta loja (lojista)
     * @return array<int, object>
     */
    public function rateioPorProfissional(int $idPremio, ?int $idProfissional = null, ?int $idLojaRestrita = null): array
    {
        $linhas = DB::select('CALL sp_rateio_premiacao_profissionais(?, ?)', [$idPremio, $idLojaRestrita]);

        if ($idProfissional) {
            $linhas = array_values(array_filter($linhas, fn ($item) => (int) $item->id_profissional === $idProfissional));
        }

        usort($linhas, fn ($a, $b) => ($a->colocacao ?? PHP_INT_MAX) <=> ($b->colocacao ?? PHP_INT_MAX));
        return $linhas;
    }

    /**
     * Calcula rateio por loja para um prêmio.
     * @param int       $idPremio
     * @param int|null  $idLoja           Filtro opcional (admin)
     * @param int|null  $idLojaRestrita   Se informado, força a loja (lojista)
     * @return array<int, object>
     */
    public function rateioPorLoja(int $idPremio, ?int $idLoja = null, ?int $idLojaRestrita = null): array
    {
        $linhas = DB::select('CALL sp_rateio_premiacao_lojas(?, ?)', [$idPremio, $idLojaRestrita]);

        if ($idLoja) {
            $linhas = array_values(array_filter($linhas, fn ($i) => (int) $i->id_loja === $idLoja));
        }

        usort($linhas, fn ($a, $b) => strcmp($a->nome_loja ?? '', $b->nome_loja ?? ''));
        return $linhas;
    }

    /**
     * Consolida rateio por loja a partir de múltiplos prêmios.
     * @param array<int,int> $idsPremios
     * @param int|null       $idLojaRestrita
     * @return array<int, object>
     */
    public function rateioConsolidado(array $idsPremios, ?int $idLojaRestrita = null): array
    {
        $param = implode(',', array_map('intval', $idsPremios));
        return DB::select('CALL sp_consolidado_rateio_lojas(?, ?)', [$param, $idLojaRestrita]);
    }
}
