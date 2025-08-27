<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de usuário com metadados essenciais para sessão do app.
 *
 * @property int $id
 * @property string $nome
 * @property string|null $email
 * @property int|null $id_perfil
 * @property int|null $id_loja
 */
class UsuarioResource extends JsonResource
{
    /**
     * Transforma os dados do usuário para resposta da API.
     *
     * @param Request $request
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'nome'      => $this->nome,
            'email'     => $this->email,
            'perfil_id' => $this->id_perfil,
            'perfil'    => $this->perfil?->nome,
            'loja_id'   => $this->id_loja,
            'loja'      => $this->whenLoaded('loja', fn () => [
                'id'            => $this->loja->id,
                'nome'          => $this->loja->nome,
                'cnpj'          => $this->loja->cnpj,
                'logomarca_url' => $this->loja->logomarca_url,
            ]),
            'status'    => $this->status,
        ];
    }
}
