<?php

namespace App\Services;

use App\Models\Banner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Camada de serviço para operações de Banner.
 * Mantém as regras de negócio e consultas complexas fora do Controller.
 */
class BannerService
{
    /**
     * Lista banners com filtros e paginação.
     *
     * @param  array{
     *   q?: string|null,
     *   status?: int|null,
     *   sort?: string|null,
     *   order?: 'asc'|'desc'|null,
     *   per_page?: int|null
     * } $params
     * @return LengthAwarePaginator
     */
    public function listar(array $params): LengthAwarePaginator
    {
        $query = Banner::query();

        // Filtro de busca (titulo/descricao)
        if (!empty($params['q'])) {
            $q = trim((string) $params['q']);
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%$q%")
                    ->orWhere('descricao', 'like', "%$q%");
            });
        }

        // Filtro de status (0/1)
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        // Ordenação segura (whitelist)
        $sort = $params['sort'] ?? 'idBanners';
        $order = strtolower($params['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $sortable = ['idBanners', 'titulo', 'status'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'idBanners';
        }

        $query->orderBy($sort, $order);

        // Paginação
        $perPage = (int) ($params['per_page'] ?? 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return $query->paginate($perPage)->appends($params);
    }

    /**
     * Cria um banner.
     *
     * @param  array<string, mixed> $data
     * @return Banner
     */
    public function criar(array $data): Banner
    {
        /** @var Banner $banner */
        $banner = Banner::query()->create($data);
        return $banner->refresh();
    }

    /**
     * Atualiza um banner.
     *
     * @param  Banner $banner
     * @param  array<string, mixed> $data
     * @return Banner
     */
    public function atualizar(Banner $banner, array $data): Banner
    {
        $banner->fill($data)->save();
        return $banner->refresh();
    }

    /**
     * Alterna/define status.
     *
     * @param  Banner $banner
     * @param  int|bool $status
     * @return Banner
     */
    public function alterarStatus(Banner $banner, int|bool $status): Banner
    {
        $banner->status = (int) ((bool) $status);
        $banner->save();

        return $banner->refresh();
    }

    /**
     * Remove um banner.
     *
     * @param  Banner $banner
     * @return void
     */
    public function remover(Banner $banner): void
    {
        $banner->delete();
    }
}
