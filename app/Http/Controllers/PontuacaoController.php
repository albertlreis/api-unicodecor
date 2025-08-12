<?php

namespace App\Http\Controllers;

use App\Domain\Pontuacoes\Contracts\PontoRepository;
use App\Domain\Pontuacoes\DTO\PontuacaoFiltro;
use App\Domain\Pontuacoes\Services\PontuacaoCommandService;
use App\Http\Requests\PontuacaoIndexRequest;
use App\Http\Requests\PontuacaoRequest;
use App\Http\Resources\PontoResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controlador de pontuações (listagem, info da home e criação/atualização).
 */
class PontuacaoController extends Controller
{
    public function __construct(
        private readonly PontoRepository $repo,
        private readonly PontuacaoCommandService $command
    ) {}

    /**
     * GET /pontuacoes
     *
     * Lista paginada com filtros aplicados e regras por perfil.
     */
    public function index(PontuacaoIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!isset($user->id_perfil)) {
            throw new AccessDeniedHttpException('Perfil do usuário não identificado.');
        }

        $filtro = PontuacaoFiltro::fromArray($request->validated());

        $paginator = $this->repo->buscarPaginado(
            $filtro,
            (int) $user->id_perfil,
            (int) $user->id,
            $user->id_loja ?? null
        );

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Lista de pontuações',
            'dados'    => $paginator->items(),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /pontuacoes
     *
     * Cria/atualiza pontuação em transação e registra histórico quando aplicável.
     */
    public function store(PontuacaoRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $data    = $request->validated();

        $ponto = $this->command->salvar($data, $usuario);

        return response()->json([
            'success' => true,
            'data'    => new PontoResource($ponto),
        ]);
    }
}
