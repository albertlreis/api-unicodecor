<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConstrutoraRequest;
use App\Http\Requests\UpdateConstrutoraRequest;
use App\Http\Resources\ConstrutoraResource;
use App\Models\Construtora;
use App\Services\ConstrutoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CRUD de Construtoras.
 */
class ConstrutorasController extends Controller
{
    public function __construct(
        private readonly ConstrutoraService $service
    ) {}

    /**
     * Lista com filtros e paginação.
     *
     * Filtros:
     * - q: busca por razão social (LIKE)
     * - status: 1, 0 ou 'all' (padrão: status >= 0)
     */
    public function index(Request $request): JsonResponse
    {
        $q      = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $perPage = (int) $request->query('per_page', 15);

        $query = Construtora::query();

        if ($status !== 'all') {
            $query->naoExcluidas();
        }

        if ($status === '0' || $status === 0) {
            $query->where('status', 0);
        } elseif ($status === '1' || $status === 1) {
            $query->where('status', 1);
        }

        if ($q !== '') {
            $query->where('razao_social', 'like', '%' . $q . '%');
        }

        $query->orderBy('razao_social', 'asc');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'results' => ConstrutoraResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Detalhe.
     */
    public function show(int $id): ConstrutoraResource
    {
        $model = Construtora::query()->findOrFail($id);

        return new ConstrutoraResource($model);
    }

    /**
     * Criação (multipart para imagem).
     */
    public function store(StoreConstrutoraRequest $request): ConstrutoraResource
    {
        $model = $this->service->create($request->validated());

        return new ConstrutoraResource($model);
    }

    /**
     * Atualização (multipart para imagem).
     *
     * @param  UpdateConstrutoraRequest $request
     * @param  int $id
     * @return ConstrutoraResource
     */
    public function update(UpdateConstrutoraRequest $request, int $id): ConstrutoraResource
    {
        Log::info('UPD Construtora', ['id' => $id, 'payload' => $request->all()]);

        $construtora = Construtora::query()->findOrFail($id);

        $construtora = $this->service->update($construtora, $request->validated());

        return new ConstrutoraResource($construtora);
    }

    /**
     * “Exclusão” marcando status = -1.
     *
     * Query param opcional: delete_image=1 para também remover arquivo local.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $model = Construtora::query()->findOrFail($id);

        $deleteImage = (bool) ((int) $request->query('delete_image', 0));

        $this->service->softDelete($model, $deleteImage);

        return response()->json(['message' => 'Construtora removida com sucesso.']);
    }

    /**
     * Altera o status explicitamente (0 desabilitado, 1 ativo).
     */
    public function setStatus(Request $request, int $id): ConstrutoraResource
    {
        $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $model = Construtora::query()->findOrFail($id);
        $model->status = (int) $request->input('status');
        $model->save();

        return new ConstrutoraResource($model->fresh());
    }
}
