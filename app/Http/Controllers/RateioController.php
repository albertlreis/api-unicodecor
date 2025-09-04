<?php

namespace App\Http\Controllers;

use App\Http\Requests\RateioRequest;
use App\Http\Resources\RateioConsolidadoResource;
use App\Http\Resources\RateioPorLojaResource;
use App\Http\Resources\RateioPorProfissionalResource;
use App\Services\RateioService;
use Illuminate\Http\JsonResponse;

class RateioController extends Controller
{
    /**
     * @route GET /rateio
     * @param RateioRequest $request
     * @param RateioService $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(RateioRequest $request, RateioService $service): JsonResponse
    {
        $modo           = $request->input('modo', 'profissional');
        $idPremio       = $request->input('id_premio');
        $idsPremios     = (array) $request->input('ids_premios', []);
        $idProfissional = $request->input('id_profissional');
        $idLojaInput    = $request->input('id_loja');

        $user = $request->user();

        $isLojista       = (int) ($user->id_perfil ?? 0) === 3;
        $idLojaRestrita  = $isLojista ? (int) $user->id_loja : null;
        $idLojaFinal     = $isLojista ? $idLojaRestrita : ($idLojaInput ? (int) $idLojaInput : null);

        if ($modo === 'profissional' && $idPremio) {
            $dados = $service->rateioPorProfissional(
                (int) $idPremio,
                $idProfissional ? (int) $idProfissional : null,
                $idLojaRestrita
            );

            $faixas = $service->faixasSemCustoPorPremio((int) $idPremio);

            return response()->json([
                'modo'  => 'profissional',
                'dados' => RateioPorProfissionalResource::collection($dados),
                'faixas_sem_custo'  => $faixas,
            ]);
        }

        if ($modo === 'loja' && $idPremio) {
            $dados = $service->rateioPorLoja(
                (int) $idPremio,
                $idLojaFinal,
                $idLojaRestrita
            );

            $faixas = $service->faixasSemCustoPorPremio((int) $idPremio);

            return response()->json([
                'modo'              => 'loja',
                'dados'             => RateioPorLojaResource::collection($dados),
                'faixas_sem_custo'  => $faixas,
            ]);
        }

        if ($modo === 'consolidado' && !empty($idsPremios)) {
            [$linhasDetalhe, $totaisPorLoja] = $service->rateioConsolidadoComProfissionais(
                array_map('intval', $idsPremios),
                $idLojaRestrita
            );

            $byLoja = [];
            foreach ($linhasDetalhe as $row) {
                $idLoja = (int) $row->id_loja;
                if (!isset($byLoja[$idLoja])) {
                    $byLoja[$idLoja] = [
                        'id_loja'             => $idLoja,
                        'nome_loja'           => (string) $row->nome_loja,
                        'total_profissionais' => 0,
                        'valor_total'         => 0.0,
                        'profissionais'       => [],
                    ];
                }

                $byLoja[$idLoja]['profissionais'][] = $row;
            }

            foreach ($totaisPorLoja as $t) {
                $idLoja = (int) $t->id_loja;
                if (isset($byLoja[$idLoja])) {
                    $byLoja[$idLoja]['valor_total']         = (float) $t->valor_total;
                    $byLoja[$idLoja]['total_profissionais'] = (int) $t->total_profissionais;
                }
            }

            usort($byLoja, fn($a, $b) => strcmp($a['nome_loja'], $b['nome_loja']));

            $faixas = $service->faixasSemCustoPorPremios(array_map('intval', $idsPremios));

            return response()->json([
                'modo'              => 'consolidado',
                'dados'             => RateioConsolidadoResource::collection(collect($byLoja)),
                'faixas_sem_custo'  => $faixas,
            ]);
        }

        return response()->json(['erro' => 'Parâmetros inválidos ou incompletos.'], 422);
    }
}
