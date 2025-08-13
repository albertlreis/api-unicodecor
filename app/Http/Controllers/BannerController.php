<?php

namespace App\Http\Controllers;

use App\Http\Requests\BannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class BannerController extends Controller
{
    public function __construct(
        private readonly BannerService $service
    ) {}

    /**
     * GET /banners
     * Listagem com filtros, ordenação e paginação.
     *
     * Filtros suportados:
     * - q: string (busca em título/descrição)
     * - status: 0|1
     * - sort: idBanners|titulo|status
     * - order: asc|desc
     * - per_page: int (1..100)
     *
     * @param  Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        Log::info('GET /banners', ['query' => $request->query()]);
        $paginado = $this->service->listar($request->query());

        return BannerResource::collection($paginado)
            ->additional(['meta' => [
                'current_page' => $paginado->currentPage(),
                'per_page'     => $paginado->perPage(),
                'total'        => $paginado->total(),
            ]])
            ->response();
    }

    /**
     * GET /banners/{banner}
     *
     * @param  Banner $banner
     * @return JsonResponse
     */
    public function show(Banner $banner): JsonResponse
    {
        Log::info('GET /banners/{id}', ['id' => $banner->idBanners]);
        return (new BannerResource($banner))->response();
    }

    /**
     * POST /banners
     *
     * @param  BannerRequest $request
     * @return JsonResponse
     */
    public function store(BannerRequest $request): JsonResponse
    {
        Log::info('POST /banners', ['payload' => $request->validated()]);
        $banner = $this->service->criar($request->validated());

        return (new BannerResource($banner))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT/PATCH /banners/{banner}
     *
     * @param  BannerRequest $request
     * @param  Banner $banner
     * @return JsonResponse
     */
    public function update(BannerRequest $request, Banner $banner): JsonResponse
    {
        Log::info('PUT/PATCH /banners/{id}', [
            'id'      => $banner->idBanners,
            'payload' => $request->validated()
        ]);

        $banner = $this->service->atualizar($banner, $request->validated());

        return (new BannerResource($banner))->response();
    }

    /**
     * DELETE /banners/{banner}
     *
     * @param  Banner $banner
     * @return JsonResponse
     */
    public function destroy(Banner $banner): JsonResponse
    {
        Log::warning('DELETE /banners/{id}', ['id' => $banner->idBanners]);
        $this->service->remover($banner);

        return response()->json([], 204);
    }

    /**
     * PATCH /banners/{banner}/status
     * Corpo: { "status": true|false }
     *
     * @param  Request $request
     * @param  Banner  $banner
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, Banner $banner): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'boolean']
        ]);

        Log::info('PATCH /banners/{id}/status', [
            'id'     => $banner->idBanners,
            'status' => $request->boolean('status')
        ]);

        $banner = $this->service->alterarStatus($banner, $request->boolean('status'));

        return (new BannerResource($banner))->response();
    }
}
