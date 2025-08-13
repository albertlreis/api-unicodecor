<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $idBanners
 * @property string $titulo
 * @property string|null $imagem
 * @property string|null $link
 * @property string|null $descricao
 * @property int $status
 */
class BannerResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->idBanners,
            'titulo'    => $this->titulo,
            'imagem'    => $this->imagem,
            'link'      => $this->link,
            'descricao' => $this->descricao,
            'status'    => $this->status,
        ];
    }
}
