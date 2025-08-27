<?php

namespace App\Http\Controllers;

use App\Domain\Premios\Contracts\PremioRepository;
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
        private readonly PremioRepository $premios
    ) {}

    /**
     * GET /premios
     *
     * Lista com filtros e paginação (mantido).
     */
    public function index(PremioIndexRequest $request): JsonResponse
    {
        /** @var array<string,mixed> $filtros */
        $filtros = $request->validated();

        $paginator = $this->premios->listarPorFiltros($filtros);

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Lista de prêmios',
            'data'     => PremioResource::collection($paginator->items()),
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
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
}
