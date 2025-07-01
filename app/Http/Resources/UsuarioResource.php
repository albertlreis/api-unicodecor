<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    /**
     * Transforma os dados do usuário para resposta da API.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'nome'      => $this->nome,
            'email'     => $this->email,
            'perfil_id' => $this->id_perfil,
            'perfil'    => $this->perfil?->nome,
            'loja_id'   => $this->id_loja,
            'status'    => $this->status,
        ];
    }
}
