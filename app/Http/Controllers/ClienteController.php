<?php

namespace App\Http\Controllers;

use App\Domain\Clientes\ClienteService;
use App\Http\Requests\ClienteStoreRequest;
use App\Http\Resources\ClienteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Clientes (armazenados temporariamente em `usuario` com perfil 6).
 * Mantém isolamento de domínio para migração futura para tabela própria.
 */
class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteService $service
    ) {}

    /**
     * Lista clientes com filtros simples (q = nome/documento).
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $dados = $this->service->listar($q);

        return response()->json([
            'sucesso' => true,
            'dados'   => ClienteResource::collection($dados),
        ]);
    }

    /**
     * Cria um cliente (perfil 6) em `usuario`.
     *
     * @param  ClienteStoreRequest $request
     * @return JsonResponse
     */
    public function store(ClienteStoreRequest $request): JsonResponse
    {
        $cliente = $this->service->criar($request->validated());

        return response()->json([
            'sucesso' => true,
            'mensagem'=> 'Cliente criado com sucesso.',
            'dados'   => new ClienteResource($cliente),
        ], 201);
    }
}
