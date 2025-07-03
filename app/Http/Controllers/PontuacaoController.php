<?php

namespace App\Http\Controllers;

use App\Http\Requests\PontuacaoRequest;
use App\Http\Resources\PontoResource;
use App\Http\Resources\CampanhaPontuacaoResource;
use App\Services\PontuacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PontuacaoController extends Controller
{
    protected PontuacaoService $service;

    public function __construct(PontuacaoService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $result = (new PontuacaoService())->buscarPontuacoes($request, $user);

        return PontoResource::collection($result)->response();
    }

    /**
     * Retorna os dados resumidos da home do app.
     *
     * @param Request $request
     * @return \App\Http\Resources\CampanhaPontuacaoResource
     */
    public function infoHome(Request $request): CampanhaPontuacaoResource
    {
        $usuarioId = $request->user()->id;
        $dados = (new PontuacaoService())->obterCampanhasComPontuacao($usuarioId);

        return new CampanhaPontuacaoResource($dados);
    }

    public function store(PontuacaoRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $data = $request->validated();

        $ponto = $this->service->salvarPontuacao($data, $usuario);

        return response()->json([
            'success' => true,
            'data' => new PontoResource($ponto),
        ]);
    }
}
