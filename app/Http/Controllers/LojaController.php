<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLojaRequest;
use App\Http\Requests\UpdateLojaRequest;
use App\Http\Resources\LojaResource;
use App\Models\Loja;
use App\Services\LojaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LojaController extends Controller
{
    public function __construct(private readonly LojaService $service)
    {
        $this->middleware('auth:sanctum');
    }

    /** GET /lojas?q=&status=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $lista = $this->service->listarPaginado($request->only(['q', 'status', 'per_page']));

        return LojaResource::collection($lista)
            ->additional([
                'meta' => [
                    'current_page' => $lista->currentPage(),
                    'last_page'    => $lista->lastPage(),
                    'per_page'     => $lista->perPage(),
                    'total'        => $lista->total(),
                ],
            ])->response();
    }

    /** GET /lojas/ativas (sem paginação) */
    public function ativas(): JsonResponse
    {
        $itens = Loja::ativas()->orderBy('nome')->get();
        return LojaResource::collection($itens)->response();
    }

    /** POST /lojas */
    public function store(StoreLojaRequest $request): JsonResponse
    {
        $loja = $this->service->criar($request->validated());
        return response()->json(new LojaResource($loja), 201);
    }

    /** GET /lojas/{loja} */
    public function show(Loja $loja): JsonResponse
    {
        return response()->json(new LojaResource($loja));
    }

    /** PUT/PATCH /lojas/{loja} */
    public function update(UpdateLojaRequest $request, Loja $loja): JsonResponse
    {
        $loja = $this->service->atualizar($loja, $request->validated());
        return response()->json(new LojaResource($loja));
    }

    /** DELETE /lojas/{loja} (delete real; sem soft delete) */
    public function destroy(Loja $loja): JsonResponse
    {
        $this->service->remover($loja);
        return response()->json(['message' => 'Loja removida com sucesso.']);
    }

    /** PATCH /lojas/{loja}/status  Body: { "status": 0|1 } */
    public function alterarStatus(Request $request, Loja $loja): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'integer', 'in:0,1']]);
        $loja->update(['status' => (int)$validated['status']]);
        return response()->json(new LojaResource($loja));
    }
}
