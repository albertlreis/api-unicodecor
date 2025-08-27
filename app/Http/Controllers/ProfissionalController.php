<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfissionalRequest;
use App\Http\Requests\UpdateProfissionalRequest;
use App\Http\Resources\ProfissionalResource;
use App\Models\Profissional;
use App\Services\ProfissionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de Profissionais.
 */
class ProfissionalController extends Controller
{
    public function __construct(
        private readonly ProfissionalService $service = new ProfissionalService()
    ) {}

    /**
     * Lista profissionais com filtros e paginação opcional.
     *
     * Parâmetros (query string):
     * - q: string (nome/login/email/cpf)
     * - id_perfil: int
     * - status: 1|2 (por padrão retorna ativos)
     * - per_page: int (default 15)
     * - all: 0|1 (se 1, ignora paginação e retorna todos)
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage   = $request->integer('per_page', 15);
        $q         = $request->string('q')->toString();
        $idPerfil  = $request->integer('id_perfil') ?: null;
        $status    = $request->integer('status');
        $wantAll   = $request->boolean('all');

        $query = Profissional::query();

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->ativos();
        }

        $query->perfil($idPerfil)
            ->busca($q)
            ->orderBy('nome');

        if ($wantAll) {
            $lista = $query->get();
            return response()->json([
                'data' => ProfissionalResource::collection($lista),
                'meta' => [
                    'current_page' => 1,
                    'per_page'     => $lista->count(),
                    'total'        => $lista->count(),
                    'last_page'    => 1,
                ],
            ]);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ProfissionalResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Cria um profissional.
     */
    public function store(StoreProfissionalRequest $request): JsonResponse
    {
        $prof = $this->service->create($request->validated());

        return response()->json(new ProfissionalResource($prof), 201);
    }

    /**
     * Detalhe.
     */
    public function show(int $id): JsonResponse
    {
        $prof = Profissional::query()->ativos()->findOrFail($id);

        return response()->json(new ProfissionalResource($prof));
    }

    /**
     * Atualiza um profissional.
     */
    public function update(UpdateProfissionalRequest $request, int $id): JsonResponse
    {
        $prof = Profissional::query()->ativos()->findOrFail($id);

        $prof = $this->service->update($prof, $request->validated());

        return response()->json(new ProfissionalResource($prof));
    }

    /**
     * Exclusão lógica (status=2).
     */
    public function destroy(int $id): JsonResponse
    {
        $prof = Profissional::query()->ativos()->findOrFail($id);
        $this->service->delete($prof);

        return response()->json([], 204);
    }
}
