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
     * Consolida rateio por loja a partir de múltiplos prêmios
     * e retorna duas coleções:
     *  - detalhe por loja+profissional (shape do RateioPorLojaResource)
     *  - totais por loja (valor_total, total_profissionais)
     *
     * @param array<int,int> $idsPremios
     * @param int|null       $idLojaRestrita
     * @return array{0: array<int, object>, 1: array<int, object>}
     */
    public function rateioConsolidadoComProfissionais(array $idsPremios, ?int $idLojaRestrita = null): array
    {
        $param = implode(',', array_map('intval', $idsPremios));

        // 1) Detalhe (loja+profissional) agregado pelos ids_premios
        $detalhe = DB::select('CALL sp_consolidado_rateio_lojas_detalhe(?, ?)', [$param, $idLojaRestrita]);

        // 2) Totais por loja (mantém compatibilidade com seu card consolidado)
        $totais  = DB::select('CALL sp_consolidado_rateio_lojas(?, ?)',       [$param, $idLojaRestrita]);

        /**
         * Ordena profissionais dentro de cada loja por:
         *  1) total_geral desc (pontos do período somados)
         *  2) profissional_nome asc
         * Obs.: a ordenação por colocação agregada é feita aqui,
         *       já que a colocação por prêmio é heterogênea.
         */
        $byLoja = [];
        foreach ($detalhe as $row) {
            $byLoja[(int)$row->id_loja][] = $row;
        }
        foreach ($byLoja as $id => $rows) {
            usort($rows, function ($a, $b) {
                $cmp = ($b->total_geral <=> $a->total_geral);
                return $cmp !== 0 ? $cmp : strcmp($a->profissional_nome, $b->profissional_nome);
            });
            // atualiza coleção ordenada
            $byLoja[$id] = $rows;
        }

        // “Flattens” ordenado
        $detalheOrdenado = [];
        foreach ($byLoja as $rows) {
            foreach ($rows as $r) $detalheOrdenado[] = $r;
        }

        return [$detalheOrdenado, $totais];
    }

    /**
     * Lista faixas de um prêmio com custo de viagem ausente/zerado.
     *
     * @param  int $idPremio
     * @return array<int, array{id_premio:int, premio:string, id_faixa:int, faixa:string}>
     */
    public function faixasSemCustoPorPremio(int $idPremio): array
    {
        $rows = DB::table('premio_faixas as f')
            ->join('premios as p', 'p.id', '=', 'f.id_premio')
            ->where('f.id_premio', $idPremio)
            ->where(function ($q) {
                $q->whereNull('f.vl_viagem')->orWhere('f.vl_viagem', '<=', 0);
            })
            ->orderBy('f.pontos_min')
            ->get([
                DB::raw('p.id as id_premio'),
                DB::raw('p.titulo as premio'),
                DB::raw('f.id as id_faixa'),
                DB::raw('f.descricao as faixa'),
            ]);

        return $rows->map(fn ($r) => [
            'id_premio' => (int) $r->id_premio,
            'premio'    => (string) $r->premio,
            'id_faixa'  => (int) $r->id_faixa,
            'faixa'     => (string) $r->faixa,
        ])->all();
    }

    /**
     * Lista faixas (para vários prêmios) com custo ausente/zerado.
     *
     * @param  array<int,int> $idsPremios
     * @return array<int, array{id_premio:int, premio:string, id_faixa:int, faixa:string}>
     */
    public function faixasSemCustoPorPremios(array $idsPremios): array
    {
        if (empty($idsPremios)) {
            return [];
        }

        $rows = DB::table('premio_faixas as f')
            ->join('premios as p', 'p.id', '=', 'f.id_premio')
            ->whereIn('f.id_premio', $idsPremios)
            ->where(function ($q) {
                $q->whereNull('f.vl_viagem')->orWhere('f.vl_viagem', '<=', 0);
            })
            ->orderBy('p.titulo')
            ->orderBy('f.pontos_min')
            ->get([
                DB::raw('p.id as id_premio'),
                DB::raw('p.titulo as premio'),
                DB::raw('f.id as id_faixa'),
                DB::raw('f.descricao as faixa'),
            ]);

        return $rows->map(fn ($r) => [
            'id_premio' => (int) $r->id_premio,
            'premio'    => (string) $r->premio,
            'id_faixa'  => (int) $r->id_faixa,
            'faixa'     => (string) $r->faixa,
        ])->all();
    }
}
