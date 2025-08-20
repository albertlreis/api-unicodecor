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
     * Lista paginada com filtros.
     *
     * Filtros suportados (query string):
     * - q: string (nome/login/email/cpf)
     * - id_perfil: int
     * - status: 1|2 (por padrão retorna !=2)
     * - per_page: int (default 15)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage  = (int) $request->integer('per_page', 15);
        $q        = $request->string('q')->toString();
        $idPerfil = $request->integer('id_perfil') ?: null;
        $status   = $request->integer('status');

        $query = Profissional::query();

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->ativos();
        }

        $query->perfil($idPerfil)->busca($q)->orderBy('nome');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data'  => ProfissionalResource::collection($paginator->items()),
            'meta'  => [
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

    /**
     * Lista aniversariantes do mês (?mes=MM).
     * Considera `dt_nasc` armazenado como 'dd/mm' OU 'YYYY-MM-DD'.
     */
    public function birthdays(Request $request): JsonResponse
    {
        $mes = $request->string('mes')->toString();
        if (!preg_match('/^(0[1-9]|1[0-2])$/', $mes)) {
            $mes = now()->format('m');
        }

        $items = Profissional::query()
            ->ativos()
            ->where('id_perfil', 2) // profissionais
            ->where(function ($q) use ($mes) {
                // 'dd/mm' termina com '/MM'
                $q->where('dt_nasc', 'like', '%/'.$mes)
                    // 'YYYY-MM-DD': mês é posição 6-7
                    ->orWhereRaw("substr(dt_nasc, 6, 2) = ?", [$mes]);
            })
            ->orderBy('dt_nasc')
            ->get();

        return response()->json(ProfissionalResource::collection($items));
    }
}
