<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Construtora
 */
class ConstrutoraResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->idConstrutoras,
            'razao_social' => $this->razao_social,
            'cnpj'         => $this->cnpj,
            'imagem'       => $this->imagem_url,
            'status'       => $this->status,
        ];
    }
}
