<?php

namespace App\Http\Controllers;

use App\Http\Resources\PremioResource;
use App\Models\Premio;
use Illuminate\Http\JsonResponse;

class PremioController extends Controller
{
    /**
     * Lista todos os prêmios ativos.
     *
     * @return JsonResponse
     */
    public function ativos(): JsonResponse
    {
        $premios = Premio::with('faixas')
            ->where('status', 1)
            ->orderByDesc('dt_inicio')
            ->get();

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Lista de prêmios ativos',
            'dados' => PremioResource::collection($premios),
        ]);
    }
}
