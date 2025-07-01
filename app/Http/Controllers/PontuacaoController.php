<?php

namespace App\Http\Controllers;

use App\Http\Resources\PontoResource;
use App\Services\PontuacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PontuacaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $result = (new PontuacaoService())->buscarPontuacoes($request, $user);

        return PontoResource::collection($result)->response();
    }
}
