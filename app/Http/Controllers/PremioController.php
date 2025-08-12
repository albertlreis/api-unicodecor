<?php

namespace App\Http\Controllers;

use App\Application\Premios\ListarPremiosAtivos;
use App\Http\Resources\PremioResource;
use Illuminate\Http\JsonResponse;

/**
 * @phpstan-type PremioCollection array<int, array<string, mixed>>
 */
class PremioController extends Controller
{
    public function __construct(
        private readonly ListarPremiosAtivos $listarPremiosAtivos
    ) {}

    /**
     * Lista prêmios/campanhas ativas, com faixas.
     *
     * @return JsonResponse
     */
    public function ativos(): JsonResponse
    {
        $premios = $this->listarPremiosAtivos->handle();

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Lista de prêmios ativos',
            'dados'    => PremioResource::collection($premios),
        ]);
    }
}
