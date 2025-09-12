<?php

namespace App\Services;

use App\Models\Banner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Camada de serviço de Banners.
 *
 * Regras chaves:
 * - Salvar somente o HASH no campo imagem, arquivo em storage público;
 * - Ao criar, validar dimensões já ocorreu no Request;
 * - Ao editar, não trocar imagem;
 * - Listagem: se "status" não for enviado (filtro "Todos"), ordenar por status ASC e título ASC;
 */
class BannerService
{
    /**
     * Lista paginada de banners com filtros e ordenação.
     *
     * @param  array<string,mixed> $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listar(array $params): LengthAwarePaginator
    {
        $query = Banner::query();

        if (!empty($params['q'])) {
            $q = trim((string) $params['q']);
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%$q%")
                    ->orWhere('descricao', 'like', "%$q%");
            });
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        $sort    = $params['sort'] ?? null;
        $order   = strtolower($params['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) ($params['per_page'] ?? 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        if (!isset($params['status']) || $params['status'] === '') {
            $query->orderBy('status', 'desc')
                ->orderBy('titulo', 'asc');
        } else {
            $sortable = ['idBanners', 'titulo', 'status'];
            $sort = in_array($sort, $sortable, true) ? $sort : 'idBanners';
            $query->orderBy($sort, $order);
        }

        return $query->paginate($perPage)->appends($params);
    }


    /**
     * Cria banner com upload.
     *
     * @param  array<string,mixed> $data
     * @param  UploadedFile $arquivo
     * @return Banner
     */
    public function criarComUpload(array $data, UploadedFile $arquivo): Banner
    {
        $contents = file_get_contents($arquivo->getRealPath());
        $hash = sha1($contents ?: uniqid('banner_', true));

        $nomeArquivo = "$hash.jpg";

        Storage::disk('public')->put("banners/$nomeArquivo", (string) $contents);

        /** @var Banner $banner */
        $banner = Banner::query()->create([
            'titulo'    => $data['titulo'],
            'imagem'    => $nomeArquivo,          // salva somente o hash (#5)
            'link'      => $data['link']      ?? null,
            'descricao' => $data['descricao'] ?? null,
            'status'    => (int) ($data['status'] ?? 1),
        ]);

        return $banner->refresh();
    }

    /**
     * Atualiza campos editáveis (NÃO troca imagem).
     *
     * @param  Banner $banner
     * @param  array<string,mixed> $data
     * @return Banner
     */
    public function atualizar(Banner $banner, array $data): Banner
    {
        $banner->fill([
            'titulo'    => $data['titulo']    ?? $banner->titulo,
            'link'      => $data['link']      ?? $banner->link,
            'descricao' => $data['descricao'] ?? $banner->descricao,
            'status'    => array_key_exists('status', $data) ? (int) $data['status'] : $banner->status,
        ])->save();

        return $banner->refresh();
    }

    public function alterarStatus(Banner $banner, int|bool $status): Banner
    {
        $banner->status = (int)((bool)$status);
        $banner->save();
        return $banner->refresh();
    }

    public function remover(Banner $banner): void
    {
        $banner->delete();
    }
}
