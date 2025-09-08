<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmpreendimentoStatusRequest;
use App\Http\Requests\EmpreendimentoStoreRequest;
use App\Http\Requests\EmpreendimentoUpdateRequest;
use App\Http\Resources\EmpreendimentoResource;
use App\Models\Empreendimento;
use App\Services\EmpreendimentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD de Empreendimentos.
 */
class EmpreendimentoController extends Controller
{
    public function __construct(
        private readonly EmpreendimentoService $service
    ) {
    }

    /**
     * Lista (com filtros) e paginação.
     *
     * Filtros suportados via query string:
     * - q: string (busca por nome)
     * - status: -1|0|1 (padrão: >=0)
     * - idConstrutoras: int
     * - per_page: int (padrão 15) / all=1 retorna tudo sem paginação
     */
    public function index(Request $request): ResourceCollection
    {
        $q              = (string) $request->query('q', '');
        $statusFilter   = $request->query('status'); // 'all' | -1 | 0 | 1 | null
        $idConstrutoras = $request->query('idConstrutoras');
        $perPage        = $request->integer('per_page') ?: 15;
        $all            = $request->boolean('all', false);

        $query = Empreendimento::query()
            ->with(['construtora:idConstrutoras,razao_social,status'])
            ->when($q !== '', fn ($qb) => $qb->where('nome', 'like', "%{$q}%"))
            ->when($idConstrutoras, fn ($qb) => $qb->where('idConstrutoras', (int) $idConstrutoras));

        // Normaliza se é um status específico (-1, 0 ou 1)
        $isSpecificStatus = in_array((string)$statusFilter, ['-1','0','1'], true)
            || in_array($statusFilter, [-1,0,1], true);

        if ($statusFilter === 'all') {
            // Sem filtro por status do EMPREENDIMENTO,
            // mas ainda assim evitamos construtoras deletadas (>= 0).
            $query->whereHas('construtora', fn($q) => $q->where('status', '>=', 0));
        } elseif ($isSpecificStatus) {
            $s = (int) $statusFilter;

            // Filtra APENAS pelo status do EMPREENDIMENTO
            $query->where('status', $s);

            // Para a CONSTRUTORA, não amarramos ao mesmo status.
            // Regra: excluir somente deletadas (status >= 0).
            $query->whereHas('construtora', fn($q) => $q->where('status', '>=', 0));
        } else {
            // Padrão: excluir excluídos (>= 0) em ambos
            $query->where('status', '>=', 0)
                ->whereHas('construtora', fn($q) => $q->where('status', '>=', 0));
        }

        $query->orderBy('nome');

        if ($all) {
            $items = $query->get();
            return EmpreendimentoResource::collection($items);
        }

        $paginator = $query->paginate($perPage)->appends($request->query());
        return EmpreendimentoResource::collection($paginator);
    }

    /**
     * Detalhe.
     *
     * @param Empreendimento $empreendimento
     * @return EmpreendimentoResource
     */
    public function show(Empreendimento $empreendimento): EmpreendimentoResource
    {
        $empreendimento->loadMissing(['construtora:idConstrutoras,razao_social']);
        return new EmpreendimentoResource($empreendimento);
    }

    /**
     * Cria.
     * @throws \Throwable
     */
    public function store(EmpreendimentoStoreRequest $request): JsonResponse
    {
        $emp = $this->service->create($request->validated());
        $emp->loadMissing(['construtora:idConstrutoras,razao_social']);

        return (new EmpreendimentoResource($emp))
            ->additional(['message' => 'Empreendimento criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Atualiza.
     *
     * @param EmpreendimentoUpdateRequest $request
     * @param Empreendimento $empreendimento
     * @return EmpreendimentoResource|JsonResponse
     * @throws \Throwable
     */
    public function update(EmpreendimentoUpdateRequest $request, Empreendimento $empreendimento): EmpreendimentoResource|JsonResponse
    {
        $emp = $this->service->update($empreendimento, $request->validated());
        $emp->loadMissing(['construtora:idConstrutoras,razao_social']);

        return (new EmpreendimentoResource($emp))
            ->additional(['message' => 'Empreendimento atualizado com sucesso.']);
    }

    /**
     * Exclusão lógica (status = -1).
     *
     * @param Empreendimento $empreendimento
     * @return JsonResponse
     * @throws \Throwable
     */
    public function destroy(Empreendimento $empreendimento): JsonResponse
    {
        $this->service->softDelete($empreendimento);

        return response()->json([
            'message' => 'Empreendimento excluído (lógico).',
        ], Response::HTTP_OK);
    }

    /**
     * Atualiza status (0|1).
     *
     * @param EmpreendimentoStatusRequest $request
     * @param Empreendimento $empreendimento
     * @return JsonResponse
     */
    public function updateStatus(EmpreendimentoStatusRequest $request, Empreendimento $empreendimento): JsonResponse
    {
        $empreendimento->status = (int) $request->validated('status');
        $empreendimento->save();

        return response()->json([
            'message' => 'Status atualizado com sucesso.',
            'data'    => (new EmpreendimentoResource($empreendimento->fresh('construtora'))),
        ]);
    }
}
