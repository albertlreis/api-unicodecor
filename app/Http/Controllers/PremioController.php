<?php

namespace App\Http\Controllers;

use App\Domain\Premios\Contracts\PremioRepository;
use App\Domain\Premios\Services\PremioFaixaResolver;
use App\Http\Requests\PremioFaixaValorViagemRequest;
use App\Http\Requests\PremioIndexRequest;
use App\Http\Requests\PremioStoreRequest;
use App\Http\Requests\PremioUpdateRequest;
use App\Http\Resources\PremioResource;
use App\Models\Premio;
use App\Models\PremioFaixa;
use App\Services\PremioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de prêmios/campanhas.
 */
class PremioController extends Controller
{
    public function __construct(
        private readonly PremioRepository $premios,
        private readonly PremioFaixaResolver $resolver,
        private readonly PremioService $service,
    ) {}

    /**
     * GET /premios
     *
     * Lista com filtros e paginação. Quando solicitado (incluir_enquadramento=1) e o
     * usuário for PROFISSIONAL (perfil_id = 2), injeta em 'meta' o id da campanha
     * em que ele está enquadrado no dia (highlight_premio_id) e os dias restantes.
     */
    public function index(PremioIndexRequest $request): JsonResponse
    {
        /** @var array<string,mixed> $filtros */
        $filtros = $request->validated();

        $paginator = $this->premios->listarPorFiltros($filtros);

        $highlightPremioId = null;
        $diasRestantesEnquadrado = null;

        $user   = $request->user();
        $perfil = (int) ($user->perfil_id ?? $user->id_perfil ?? 0);
        $querEnquadramento = (bool) ($filtros['incluir_enquadramento'] ?? false);

        if ($querEnquadramento && $perfil === 2) {
            $payload = $this->resolver->resolver(
                usuarioId: (int)$user->id,
                dataBase: $filtros['data_base'] ?? null,
                incluirProximasFaixas: false,
                incluirProximasCampanhas: false
            );

            if (!empty($payload['campanha']['id'])) {
                $highlightPremioId       = (int) $payload['campanha']['id'];
                $diasRestantesEnquadrado = (int) ($payload['dias_restantes'] ?? 0);
            }
        }

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Lista de prêmios',
            'data'     => PremioResource::collection($paginator->items()),
            'meta'     => [
                'current_page'              => $paginator->currentPage(),
                'per_page'                  => $paginator->perPage(),
                'total'                     => $paginator->total(),
                'last_page'                 => $paginator->lastPage(),
                'highlight_premio_id'       => $highlightPremioId,
                'dias_restantes_enquadrado' => $diasRestantesEnquadrado,
            ],
        ]);
    }

    /**
     * GET /premios/{premio}
     *
     * Retorna um prêmio com faixas (útil na edição).
     */
    public function show(Premio $premio): JsonResponse
    {
        $premio->load('faixas');
        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Detalhe do prêmio',
            'data'     => new PremioResource($premio),
        ]);
    }

    /**
     * POST /premios
     *
     * Cria um prêmio com:
     * - status SEMPRE ativo (1)
     * - banner/regulamento por ANEXO (sem URL)
     * - faixas com valor por faixa
     * @throws \Throwable
     */
    public function store(PremioStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $arquivo = $request->file('arquivo');

        $premio = $this->service->criar($payload, $arquivo);


        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Prêmio criado com sucesso.',
            'data'     => new PremioResource($premio),
        ], 201);
    }

    /**
     * PUT/PATCH /premios/{premio}
     *
     * Atualiza um prêmio:
     * - mantém o status existente (não alteramos aqui)
     * - troca arquivos apenas se enviados no request
     * - sincroniza faixas (upsert + remoção do que saiu)
     * @throws \Throwable
     */
    public function update(PremioUpdateRequest $request, Premio $premio): JsonResponse
    {
        $payload = $request->validated();
        $arquivo = $request->file('arquivo');
        $premio = $this->service->atualizar($premio, $payload, $arquivo);

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Prêmio atualizado com sucesso.',
            'data'     => new PremioResource($premio),
        ]);
    }

    /**
     * PATCH /premios/faixas/{faixa}/valor-viagem
     *
     * Atualiza apenas o campo 'vl_viagem' da faixa informada.
     * - Restrito a administradores (perfil_id === 1), verificado no FormRequest.
     * - Não altera mais nada da faixa.
     *
     * @param  PremioFaixaValorViagemRequest $request
     * @param  PremioFaixa                   $faixa
     * @return JsonResponse
     */
    public function atualizarValorViagemFaixa(PremioFaixaValorViagemRequest $request, PremioFaixa $faixa): JsonResponse
    {
        /** @var array{vl_viagem: float|null} $data */
        $data = $request->validated();

        $faixa->vl_viagem = array_key_exists('vl_viagem', $data) ? $data['vl_viagem'] : $faixa->vl_viagem;
        $faixa->save();

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Valor da viagem atualizado com sucesso.',
            'dados'    => [
                'id'         => $faixa->id,
                'id_premio'  => $faixa->id_premio,
                'pontos_min' => $faixa->pontos_min,
                'pontos_max' => $faixa->pontos_max,
                'vl_viagem'  => $faixa->vl_viagem,
            ],
        ]);
    }

    /** @phpstan-return JsonResponse */
    public function alterarStatus(Request $request, Premio $premio): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        $premio = $this->service->alterarStatus($premio, (bool) $validated['status']);

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Status do prêmio atualizado.',
            'data'     => new PremioResource($premio),
        ]);
    }
}
