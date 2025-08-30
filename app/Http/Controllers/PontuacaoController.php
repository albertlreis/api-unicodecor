<?php

namespace App\Http\Controllers;

use App\Domain\Pontuacoes\Contracts\PontoRepository;
use App\Domain\Pontuacoes\DTO\PontuacaoFiltro;
use App\Domain\Pontuacoes\Services\PontuacaoCommandService;
use App\Http\Requests\PontuacaoIndexRequest;
use App\Http\Requests\PontuacaoRequest;
use App\Http\Requests\PontuacaoUpdateRequest;
use App\Http\Resources\PontoResource;
use App\Models\Ponto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controlador de pontuações (listagem e criação).
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
     * Lista paginada com filtros e regras por perfil.
     */
    public function index(PontuacaoIndexRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!isset($user->id_perfil)) {
            throw new AccessDeniedHttpException('Perfil do usuário não identificado.');
        }

        // Os dados já vêm normalizados para os nomes CANÔNICOS pelo FormRequest.
        $filtro = PontuacaoFiltro::fromArray($request->validated());

        $paginator = $this->repo->buscarPaginado(
            $filtro,
            (int) $user->id_perfil,
            (int) $user->id,
            $user->id_loja ?? null
        );

        return response()->json([
            'data' => PontoResource::collection($paginator->getCollection()),
            'meta' => [
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
            'data' => new PontoResource($ponto),
        ]);
    }

    /**
     * PUT/PATCH /pontuacoes/{ponto}
     * @param PontuacaoUpdateRequest $request
     * @param Ponto $ponto
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PontuacaoUpdateRequest $request, Ponto $ponto): JsonResponse
    {
        $usuario = $request->user();

        if (!isset($usuario->id_perfil)) {
            throw new AccessDeniedHttpException('Perfil do usuário não identificado.');
        }

        $data = $request->validated();
        $data['id'] = $ponto->id;

        $atualizado = $this->command->salvar($data, $usuario);

        return response()->json([
            'data' => new PontoResource($atualizado->fresh(['profissional','lojista','loja','cliente'])),
        ]);
    }

    /**
     * DELETE /pontuacoes/{ponto}
     * @param \Illuminate\Http\Request $request
     * @param Ponto $ponto
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Ponto $ponto): JsonResponse
    {
        $usuario = $request->user();

        if (!isset($usuario->id_perfil)) {
            throw new AccessDeniedHttpException('Perfil do usuário não identificado.');
        }

        $this->command->excluir($ponto->id, $usuario);

        return response()->json([
            'message' => 'Pontuação excluída com sucesso.'
        ]);
    }

    /**
     * GET /pontuacoes/{ponto}
     *
     * Retorna uma pontuação para edição.
     */
    public function show(int $pontoId): JsonResponse
    {
        $user = auth()->user();

        $ponto = Ponto::findOrFail($pontoId);
        if (!$ponto) {
            return response()->json(['mensagem' => 'Registro não encontrado'], 404);
        }

        // RBAC básico: Admin pode ver tudo; Lojista só da própria loja/lançamento; Profissional só se for dele
        $perfil = (int) $user->id_perfil;
        if ($perfil === 3) {
            if ((int) $ponto->id_lojista !== (int) $user->id && (int) $ponto->id_loja !== (int) ($user->id_loja ?? 0)) {
                throw new AccessDeniedHttpException('Sem permissão para visualizar este registro.');
            }
        } elseif ($perfil === 2) {
            if ((int) $ponto->id_profissional !== (int) $user->id) {
                throw new AccessDeniedHttpException('Sem permissão para visualizar este registro.');
            }
        }

        return response()->json([
            'data' => new PontoResource($ponto),
        ]);
    }

}
