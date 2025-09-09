<?php

namespace App\Http\Controllers;

use App\Http\Requests\RankingRequest;
use App\Http\Requests\RankingV2Request;
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

        return response()->json(new Top100Resource((object) $data));
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

        $response = [
            'sucesso' => true,
            'mensagem' => 'Ranking detalhado carregado com sucesso.',
            'premio' => $resultado['premio'],
        ];

        if (isset($resultado['todos'])) {
            $response['todos'] = RankingDetalhadoResource::collection($resultado['todos']);
        } else {
            $response['atingiram'] = RankingDetalhadoResource::collection($resultado['atingiram']);
            $response['nao_atingiram'] = RankingDetalhadoResource::collection($resultado['nao_atingiram']);
        }

        return response()->json($response);
    }

    /**
     * GET /ranking/premios
     * Retorna apenas para a tela de ranking:
     * - Prêmios ATIVOS do banco
     * - + 2 prêmios virtuais "Top 100" (atual e próximo)
     *
     * @return JsonResponse
     */
    public function premiosOptions(): JsonResponse
    {
        $svc = new RankingService();
        $ativos = $svc->listarPremiosAtivosBase();       // só banco
        [$t100Atual, $t100Prox] = $svc->buildTop100VirtualPrizes(); // só ranking

        // Mescla, sem alterar /premios global
        $items = array_values(array_merge($ativos, [$t100Atual, $t100Prox]));

        return response()->json(['data' => $items]);
    }

    /**
     * Ranking Geral v2
     * - Admin/Secretaria: periodo = 'ano'|'top100_atual'|'top100_anterior'
     * - Lojista: data_inicio / data_fim (default: 01/01/ano -> hoje no front)
     */
    public function indexV2(RankingV2Request $request, RankingService $service): JsonResponse
    {
        $out = $service->listarV2($request);

        return response()->json([
            'meta' => $out['meta'],
            'campanhas' => $out['campanhas'],
        ]);
    }
}
