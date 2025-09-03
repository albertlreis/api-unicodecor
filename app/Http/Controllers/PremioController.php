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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controlador de prêmios/campanhas.
 */
class PremioController extends Controller
{
    public function __construct(
        private readonly PremioRepository $premios,
        private readonly PremioFaixaResolver $resolver,
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

        // 🔎 Descobrir se devemos calcular o enquadramento do usuário
        $highlightPremioId = null;
        $diasRestantesEnquadrado = null;

        $user   = $request->user();
        $perfil = (int) ($user->perfil_id ?? $user->id_perfil ?? 0);
        $querEnquadramento = (bool) ($filtros['incluir_enquadramento'] ?? false);

        if ($querEnquadramento && $perfil === 2) {
            // Reaproveita a lógica consolidada do resolver (sem expor /me/premios).
            $payload = $this->resolver->resolver(
                usuarioId: (int)$user->id,
                dataBase: $filtros['data_base'] ?? null,
                incluirProximasFaixas: false,    // não precisamos dessas listas aqui
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
     */
    public function store(PremioStoreRequest $request): JsonResponse
    {
        /** @var array<string,mixed> $data */
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data) {
            // Arquivos obrigatórios
            $bannerFilename = $this->storeBanner($request);
            $regFilename    = $this->storeRegulamento($request);

            // Cria prêmio (status sempre ativo)
            $premio = new Premio();
            $premio->titulo     = $data['titulo'];
            $premio->descricao  = $data['descricao'] ?? null;
            $premio->regras     = $data['regras']    ?? null;
            $premio->dt_inicio  = $data['dt_inicio'];
            $premio->dt_fim     = $data['dt_fim'];
            $premio->status     = 1;
            $premio->banner     = $bannerFilename;
            $premio->regulamento= $regFilename;
            $premio->dt_cadastro= now();

            $premio->save();

            // Sincroniza faixas
            $this->syncFaixas($premio, $data['faixas'] ?? []);

            $premio->load('faixas');

            return response()->json([
                'sucesso'  => true,
                'mensagem' => 'Prêmio criado com sucesso.',
                'data'     => new PremioResource($premio),
            ], 201);
        });
    }

    /**
     * PUT/PATCH /premios/{premio}
     *
     * Atualiza um prêmio:
     * - mantém o status existente (não alteramos aqui)
     * - troca arquivos apenas se enviados no request
     * - sincroniza faixas (upsert + remoção do que saiu)
     */
    public function update(PremioUpdateRequest $request, Premio $premio): JsonResponse
    {
        /** @var array<string,mixed> $data */
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data, $premio) {
            // Atualiza campos básicos
            $premio->titulo    = $data['titulo'];
            $premio->descricao = $data['descricao'] ?? null;
            $premio->regras    = $data['regras']    ?? null;
            $premio->dt_inicio = $data['dt_inicio'];
            $premio->dt_fim    = $data['dt_fim'];

            // Substitui arquivos quando enviados
            if ($request->hasFile('banner_file')) {
                $this->deleteIfExists($premio->banner, Premio::BANNER_DIR);
                $premio->banner = $this->storeBanner($request);
            }
            if ($request->hasFile('regulamento_file')) {
                $this->deleteIfExists($premio->regulamento, Premio::REGULAMENTO_DIR);
                $premio->regulamento = $this->storeRegulamento($request);
            }

            $premio->save();

            // Sincroniza faixas
            $this->syncFaixas($premio, $data['faixas'] ?? []);

            $premio->load('faixas');

            return response()->json([
                'sucesso'  => true,
                'mensagem' => 'Prêmio atualizado com sucesso.',
                'data'     => new PremioResource($premio),
            ]);
        });
    }

    /**
     * Salva o banner no disk 'public' e retorna APENAS o nome.ext salvo.
     *
     * @param  Request $request
     * @return string
     */
    private function storeBanner(Request $request): string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('banner_file');

        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $name = uniqid('banner_', true).'.'.$ext;

        // salva em 'premios/banners/<nome.ext>'
        $file->storeAs(Premio::BANNER_DIR, $name, ['disk' => 'public']);

        return $name;
    }

    /**
     * Salva o regulamento (PDF) no disk 'public' e retorna APENAS o nome.ext salvo.
     *
     * @param  Request $request
     * @return string
     */
    private function storeRegulamento(Request $request): string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('regulamento_file');

        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        $name = uniqid('reg_', true).'.'.$ext;

        // salva em 'premios/regulamentos/<nome.ext>'
        $file->storeAs(Premio::REGULAMENTO_DIR, $name, ['disk' => 'public']);

        return $name;
    }

    /**
     * Remove arquivo anterior (se existir), aceitando NOME ou caminho relativo legado.
     *
     * @param  string|null $filename Nome simples (ex.: foo.png) OU caminho relativo legado (ex.: premios/banners/foo.png)
     * @param  string      $dir      Diretório padrão quando $filename for apenas nome
     * @return void
     */
    private function deleteIfExists(?string $filename, string $dir): void
    {
        if (!$filename) return;

        // Se já vier com '/', tratamos como caminho relativo completo.
        $relativePath = Str::contains($filename, '/')
            ? $filename
            : (trim($dir, '/').'/'.$filename);

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    /**
     * Sincroniza as faixas do prêmio (upsert + exclusão das removidas).
     *
     * @param  Premio $premio
     * @param  array<int,array<string,mixed>> $faixas
     * @return void
     */
    private function syncFaixas(Premio $premio, array $faixas): void
    {
        $idsMantidos = [];

        foreach ($faixas as $fx) {
            $payload = [
                'pontos_min'  => (int) ($fx['pontos_min'] ?? 0),
                'pontos_max'  => array_key_exists('pontos_max', $fx) ? ($fx['pontos_max'] !== null ? (int) $fx['pontos_max'] : null) : null,
                'vl_viagem'   => (float) ($fx['vl_viagem'] ?? 0),
                'acompanhante'=> (int) ($fx['acompanhante'] ?? 0),
                'descricao'   => $fx['descricao'] ?? null,
            ];

            if (!empty($fx['id'])) {
                /** @var PremioFaixa $row */
                $row = PremioFaixa::query()
                    ->where('id', (int) $fx['id'])
                    ->where('id_premio', $premio->id)
                    ->first();

                if ($row) {
                    $row->fill($payload)->save();
                    $idsMantidos[] = $row->id;
                    continue;
                }
            }

            $novo = new PremioFaixa($payload);
            $novo->id_premio = $premio->id;
            $novo->save();
            $idsMantidos[] = $novo->id;
        }

        // apaga faixas que não vieram mais
        if (!empty($idsMantidos)) {
            PremioFaixa::query()
                ->where('id_premio', $premio->id)
                ->whereNotIn('id', $idsMantidos)
                ->delete();
        } else {
            // se veio vazio, remove todas
            PremioFaixa::query()
                ->where('id_premio', $premio->id)
                ->delete();
        }
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

        // Atualiza pontualmente
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
}
