<?php

namespace App\Http\Controllers;

use App\Infra\Repositories\EloquentPontoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retorna listas dinâmicas (lojas, clientes, profissionais) recortadas
 * pelos pontos vinculados conforme filtros opcionais recebidos.
 *
 * Parâmetros aceitos (query string):
 * - profissional_id?: int
 * - loja_id?: int
 */
final class PontuacaoOpcoesController extends Controller
{
    public function __construct(private readonly EloquentPontoRepository $repo) {}

    /**
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $profissionalId = $request->integer('profissional_id') ?: null;
        $lojaId         = $request->integer('loja_id') ?: null;

        $listas = $this->repo->listarOpcoes($profissionalId, $lojaId);

        return response()->json([
            'sucesso' => true,
            'dados'   => $listas,
        ]);
    }
}
