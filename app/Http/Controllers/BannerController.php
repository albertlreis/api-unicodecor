<?php

namespace App\Http\Controllers;

use App\Http\Requests\BannerStoreRequest;
use App\Http\Requests\BannerUpdateRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(private readonly BannerService $service) {}

    /**
     * GET /banners (filtros + paginação)
     *
     * Retorna:
     * {
     *   "data": [...],
     *   "links": {...},
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 5,
     *     "per_page": 15,
     *     "total": 74,
     *     ...
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $paginado = $this->service->listar($request->query());

        // Deixe o Laravel montar links/meta (inclui last_page)
        return BannerResource::collection($paginado)->response();
    }

    /** GET /banners/ativos – lista apenas ativos (para slider da Home) */
    public function ativos(Request $request): JsonResponse
    {
        $req = $request->merge(['status' => 1, 'per_page' => (int)($request->query('per_page', 50))]);
        $paginado = $this->service->listar($req->query());

        // Também mantém o meta padrão aqui
        return BannerResource::collection($paginado)->response();
    }

    /** GET /banners/{banner} */
    public function show(Banner $banner): JsonResponse
    {
        return (new BannerResource($banner))->response();
    }

    /** POST /banners (com upload) */
    public function store(BannerStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $arquivo = $request->file('arquivo');

        $banner = $this->service->criarComUpload($payload, $arquivo);

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    /** PUT/PATCH /banners/{banner} (sem trocar imagem) */
    public function update(BannerUpdateRequest $request, Banner $banner): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('arquivo')) {
            $arquivo = $request->file('arquivo');
            $banner = $this->service->atualizarComUpload($banner, $data, $arquivo);
        } else {
            $banner = $this->service->atualizar($banner, $data);
        }

        $banner = $this->service->atualizar($banner, $request->validated());
        return (new BannerResource($banner))->response();
    }

    /** DELETE /banners/{banner} */
    public function destroy(Banner $banner): JsonResponse
    {
        $this->service->remover($banner);
        return response()->json([], 204);
    }

    /** PATCH /banners/{banner}/status */
    public function toggleStatus(Request $request, Banner $banner): JsonResponse
    {
        $request->validate(['status' => ['required', 'boolean']]);
        $banner = $this->service->alterarStatus($banner, $request->boolean('status'));
        return (new BannerResource($banner))->response();
    }
}
