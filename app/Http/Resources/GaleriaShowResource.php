<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string|null $descricao
 */
class GaleriaShowResource extends JsonResource
{
    /**
     * @return array{descricao:string,imagens:array<int,array{id:int,arquivo:string}>}
     */
    public function toArray($request): array
    {
        return [
            'descricao' => $this->descricao ?? '',
            'imagens'   => GaleriaImagemResource::collection($this->imagens)->resolve(),
        ];
    }
}
