<?php

namespace App\Http\Controllers;

use App\Http\Resources\GaleriaImagemResource;
use App\Http\Resources\GaleriaResource;
use App\Http\Resources\GaleriaShowResource;
use App\Models\Galeria;
use App\Models\GaleriaImagem;
use App\Services\GaleriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de Galerias (álbuns) e Imagens.
 */
class GaleriaController extends Controller
{
    public function __construct(private readonly GaleriaService $service) {}

    /**
     * GET /galerias
     * Admin: pode filtrar status e min_qtd; Demais: força status=1 e min_qtd>=1.
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $status = $request->has('status')
            ? $request->integer('status')
            : null;

        $filtros = [
            'status'   => $status,
            'min_qtd'  => $request->integer('min_qtd', null),
            'per_page' => $request->integer('per_page', null),
        ];

        $data = $this->service->listar($filtros, (int) $user->id_perfil);

        return response()->json(GaleriaResource::collection($data));
    }

    /**
     * GET /galerias/{id}
     *
     * @param int $id
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $perfilId = (int) $request->user()->id_perfil;
        $galeria = $this->service->detalhar($id, $perfilId);

        return response()->json(new GaleriaShowResource($galeria));
    }

    /**
     * POST /galerias
     * Apenas admin.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-galerias');

        $data = $request->validate([
            'descricao' => ['required', 'string', 'max:3000'],
        ]);

        $galeria = $this->service->criar($data);

        return response()->json([
            'message' => 'Galeria criada com sucesso.',
            'id'      => $galeria->idGalerias,
        ], 201);
    }

    /**
     * PUT /galerias/{id}
     * Apenas admin.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        Gate::authorize('manage-galerias');

        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);

        $data = $request->validate([
            'descricao' => ['required', 'string', 'max:3000'],
        ]);

        $this->service->atualizar($galeria, $data);

        return response()->json(['message' => 'Galeria atualizada com sucesso.']);
    }
    /**
     * DELETE /galerias/{id}
     * Apenas admin.
     *
     * @param  int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        Gate::authorize('manage-galerias');

        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);

        $novoStatus = $galeria->status === 1 ? 0 : 1;
        $this->service->alterarStatus($galeria, $novoStatus);

        return response()->json([
            'message' => $novoStatus === 1 ? 'Galeria ativada com sucesso.' : 'Galeria inativada com sucesso.',
            'status'  => $novoStatus,
            'id'      => (int) $galeria->idGalerias,
        ]);
    }

    /**
     * POST /galerias/{id}/imagens
     * Apenas admin.
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeImagem(Request $request, int $id): JsonResponse
    {
        Gate::authorize('manage-galerias');

        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);

        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $file = $request->file('arquivo');
        if (!$file) {
            throw ValidationException::withMessages(['arquivo' => 'Arquivo inválido.']);
        }

        $img = $this->service->adicionarImagem($galeria, $file);

        return response()->json(new GaleriaImagemResource($img), 201);
    }

    /**
     * DELETE /galerias/{id}/imagens/{idImagem}
     * Apenas admin.
     */
    public function destroyImagem(int $id, int $idImagem): JsonResponse
    {
        Gate::authorize('manage-galerias');

        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);
        $img = GaleriaImagem::where('idGaleriaImagens', $idImagem)
            ->where('idGalerias', $galeria->idGalerias)
            ->firstOrFail();

        $this->service->removerImagem($galeria, $img);

        return response()->json(['message' => 'Imagem removida com sucesso.']);
    }

    /**
     * PATCH /galerias/{id}/capa/{idImagem}
     * Apenas admin.
     */
    public function definirCapa(int $id, int $idImagem): JsonResponse
    {
        Gate::authorize('manage-galerias');

        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);
        $img = GaleriaImagem::where('idGaleriaImagens', $idImagem)
            ->where('idGalerias', $galeria->idGalerias)
            ->where('status', 1)
            ->firstOrFail();

        $this->service->definirCapa($galeria, $img);

        return response()->json(['message' => 'Capa definida com sucesso.']);
    }
}
