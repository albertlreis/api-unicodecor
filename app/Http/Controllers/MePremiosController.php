<?php

namespace App\Http\Controllers;

use App\Domain\Premios\Contracts\PremioRepository;
use App\Domain\Premios\Services\PremioFaixaResolver;
use App\Http\Requests\MePremiosRequest;
use App\Http\Resources\PremioResource;
use App\Http\Resources\PremiosFaixasProfissionalResource;
use Illuminate\Http\JsonResponse;

/**
 * Controlador do endpoint GET /me/premios.
 */
class MePremiosController extends Controller
{
    public function __construct(
        private readonly PremioRepository    $premios,
        private readonly PremioFaixaResolver $resolver
    ) {}

    /**
     * @route GET /me/premios
     *
     * @param  MePremiosRequest $request
     * @return JsonResponse
     *
     * @throws \Throwable
     */
    public function index(MePremiosRequest $request): JsonResponse
    {
        $user = $request->user();

        // 🔒 Normaliza o campo de perfil (legado usa id_perfil)
        /** @var int $perfil */
        $perfil = (int) ($user->perfil_id ?? $user->id_perfil ?? 0);

        // 👤 PROFISSIONAL (perfil 2) -> usa resolver (retorna pontuação, campanha atual, faixas etc.)
        if ($perfil === 2) {
            $payload = $this->resolver->resolver(
                usuarioId: (int) $user->id,
                dataBase: $request->input('data_base'),
                incluirProximasFaixas: (bool) $request->boolean('incluir_proximas_faixas', true),
                incluirProximasCampanhas: (bool) $request->boolean('incluir_proximas_campanhas', true)
            );

            return response()->json([
                'sucesso'  => true,
                'mensagem' => 'Prêmios do profissional',
                'dados'    => (new PremiosFaixasProfissionalResource($payload))->toArray($request),
            ]);
        }

        // 📋 Demais perfis -> lista paginada (sem pontuação e sem "enquadramento")
        /** @var array<string,mixed> $filtros */
        $filtros = $request->validated();
        $filtros['include_faixas'] = true;
        $filtros['somente_ativas'] = $filtros['somente_ativas'] ?? true;

        $paginator = $this->premios->listarPorFiltros($filtros);

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Lista de prêmios',
            'dados'    => PremioResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
