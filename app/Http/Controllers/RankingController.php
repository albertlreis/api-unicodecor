<?php

namespace App\Http\Controllers;

use App\Http\Requests\RankingRequest;
use App\Http\Resources\RankingDetalhadoResource;
use App\Http\Resources\RankingResource;
use App\Http\Resources\Top100Resource;
use App\Services\RankingService;
use Illuminate\Http\JsonResponse;

class RankingController extends Controller
{
    public function top100(RankingService $service): JsonResponse
    {
        $user = auth()->user();

        $data = $service->getTop100Data($user->id);

        return response()->json(new Top100Resource($data));
    }

    /**
     * Exibe o ranking geral de pontuações.
     *
     * @param \App\Http\Requests\RankingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(RankingRequest $request): JsonResponse
    {
        $resultado = (new RankingService())->listar($request);

        return response()->json([
            'premio' => $resultado['premio'],
            'dados' => RankingResource::collection($resultado['dados']),
        ]);
    }

    public function detalhado(RankingRequest $request): JsonResponse
    {
        $resultado = (new RankingService())->obterRankingDetalhadoPorPremio($request);

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Ranking detalhado carregado com sucesso.',
            'atingiram' => RankingDetalhadoResource::collection($resultado['atingiram']),
            'nao_atingiram' => RankingDetalhadoResource::collection($resultado['nao_atingiram']),
            'premio' => $resultado['premio'],
        ]);
    }
}
