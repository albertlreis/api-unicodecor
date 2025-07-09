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
    public function index(RateioRequest $request, RateioService $service): JsonResponse
    {
        $modo = $request->input('modo', 'profissional');
        $idPremio = $request->input('id_premio');
        $idsPremios = $request->input('ids_premios', []);
        $idProfissional = $request->input('id_profissional');
        $idLoja = $request->input('id_loja');

        if ($modo === 'profissional' && $idPremio) {
            $dados = $service->rateioPorProfissional($idPremio, $idProfissional);
            return response()->json([
                'modo' => 'profissional',
                'dados' => RateioPorProfissionalResource::collection($dados),
            ]);
        }

        if ($modo === 'loja' && $idPremio) {
            $dados = $service->rateioPorLoja($idPremio, $idLoja);
            return response()->json([
                'modo' => 'loja',
                'dados' => RateioPorLojaResource::collection($dados),
            ]);
        }

        if ($modo === 'consolidado' && !empty($idsPremios)) {
            $dados = $service->rateioConsolidado($idsPremios);
            return response()->json([
                'modo' => 'consolidado',
                'dados' => RateioConsolidadoResource::collection($dados),
            ]);
        }

        return response()->json([
            'erro' => 'Parâmetros inválidos ou incompletos.'
        ], 422);
    }
}
