<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantaBaixaRequest;
use App\Http\Requests\UpdatePlantaBaixaRequest;
use App\Http\Resources\PlantasBaixasResource;
use App\Models\Construtora;
use App\Models\Empreendimento;
use App\Models\PlantaBaixa;
use App\Services\PlantasBaixasService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador responsável pela listagem e CRUD de Plantas Baixas.
 */
class PlantasBaixasController extends Controller
{
    public function __construct(private readonly PlantasBaixasService $service) {}

    /** Listagem agrupada (existente) */
    public function index(): JsonResponse
    {
        $plantas = $this->service->listarAgrupado();
        return response()->json(PlantasBaixasResource::collection($plantas));
    }

    /**
     * Cadastra uma nova planta baixa com upload de PDF.
     *
     * @param StorePlantaBaixaRequest $request
     * @return JsonResponse
     */
    public function store(StorePlantaBaixaRequest $request): JsonResponse
    {
        /** @var array $validated */
        $validated = $request->validated();

        $planta = $this->service->criar($validated, $request->file('arquivo'));

        return response()->json([
            'message' => 'Planta baixa criada com sucesso.',
            'data'    => $planta,
        ], 201);
    }

    /**
     * Atualiza metadados e/ou arquivo da planta.
     *
     * @param int $id
     * @param UpdatePlantaBaixaRequest $request
     * @return JsonResponse
     */
    public function update(int $id, UpdatePlantaBaixaRequest $request): JsonResponse
    {
        $planta = PlantaBaixa::findOrFail($id);
        /** @var array $validated */
        $validated = $request->validated();

        $planta = $this->service->atualizar($planta, $validated, $request->file('arquivo'));

        return response()->json([
            'message' => 'Planta baixa atualizada com sucesso.',
            'data'    => $planta,
        ]);
    }

    /**
     * Remove a planta e o arquivo físico (se existir).
     *
     * @param int $id
     */
    public function destroy(int $id): JsonResponse
    {
        $planta = PlantaBaixa::findOrFail($id);
        $this->service->excluir($planta);

        return response()->json(['message' => 'Planta baixa excluída com sucesso.']);
    }

    /** Listagem de construtoras (para select) */
    public function construtoras(): JsonResponse
    {
        $rows = Construtora::query()
            ->orderBy('razao_social')
            ->get(['idConstrutoras as id','razao_social','imagem']);

        return response()->json($rows);
    }

    /** Empreendimentos por construtora (para select em cascata) */
    public function empreendimentosPorConstrutora(int $id): JsonResponse
    {
        $rows = Empreendimento::query()
            ->where('idConstrutoras', $id)
            ->orderBy('nome')
            ->get(['idEmpreendimentos as id','nome','imagem']);

        return response()->json($rows);
    }
}
