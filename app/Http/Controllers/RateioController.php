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
     * Retorna os dados de rateio por modo:
     * - modo=profissional: requer id_premio; opcional id_profissional
     * - modo=loja: requer id_premio; opcional id_loja
     * - modo=consolidado: requer ids_premios (array de IDs)
     *
     * @param  RateioRequest  $request
     * @param  RateioService  $service
     * @return JsonResponse
     */
    public function index(RateioRequest $request, RateioService $service): JsonResponse
    {
        $modo           = $request->input('modo', 'profissional');
        $idPremio       = $request->input('id_premio');
        $idsPremios     = $request->input('ids_premios', []);
        $idProfissional = $request->input('id_profissional');
        $idLoja         = $request->input('id_loja');

        if ($modo === 'profissional' && $idPremio) {
            $dados = $service->rateioPorProfissional((int) $idPremio, $idProfissional ? (int) $idProfissional : null);
            return response()->json([
                'modo'  => 'profissional',
                'dados' => RateioPorProfissionalResource::collection($dados),
            ]);
        }

        if ($modo === 'loja' && $idPremio) {
            $dados = $service->rateioPorLoja((int) $idPremio, $idLoja ? (int) $idLoja : null);
            return response()->json([
                'modo'  => 'loja',
                'dados' => RateioPorLojaResource::collection($dados),
            ]);
        }

        if ($modo === 'consolidado' && !empty($idsPremios)) {
            $dados = $service->rateioConsolidado(array_map('intval', (array) $idsPremios));
            return response()->json([
                'modo'  => 'consolidado',
                'dados' => RateioConsolidadoResource::collection($dados),
            ]);
        }

        return response()->json([
            'erro' => 'Parâmetros inválidos ou incompletos.'
        ], 422);
    }
}
