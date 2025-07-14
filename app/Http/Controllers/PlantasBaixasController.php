<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlantasBaixasResource;
use App\Services\PlantasBaixasService;
use Illuminate\Http\JsonResponse;

/**
 * Class PlantasBaixasController
 *
 * Controlador responsável pela listagem agrupada de plantas baixas.
 */
class PlantasBaixasController extends Controller
{
    protected PlantasBaixasService $service;

    public function __construct(PlantasBaixasService $service)
    {
        $this->service = $service;
    }

    /**
     * Retorna lista de plantas baixas agrupadas por construtora e empreendimento.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $plantas = $this->service->listarAgrupado();

        return response()->json(PlantasBaixasResource::collection($plantas));
    }
}
