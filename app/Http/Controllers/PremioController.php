<?php

namespace App\Http\Controllers;

use App\Domain\Premios\Contracts\PremioRepository;
use App\Http\Requests\PremioIndexRequest;
use App\Http\Resources\PremioResource;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de prêmios/campanhas (lista com filtros e paginação).
 */
class PremioController extends Controller
{
    public function __construct(
        private readonly PremioRepository $premios
    ) {}

    /**
     * GET /premios
     *
     * Filtros:
     * - status (0,1,2)
     * - somente_ativas (bool) + data_base (Y-m-d) opcional
     * - titulo (like)
     * - ids[] (IN)
     * - ordenar_por (dt_inicio, dt_fim, titulo, id) + orden (asc|desc)
     * - page, per_page
     * - include_faixas (bool) para eager load condicional
     */
    public function index(PremioIndexRequest $request): JsonResponse
    {
        /** @var array<string,mixed> $filtros */
        $filtros = $request->validated();

        $paginator = $this->premios->listarPorFiltros($filtros);

        // Mantém seu contrato "sucesso/mensagem" e adiciona meta da paginação
        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Lista de prêmios',
            'data'    => PremioResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
