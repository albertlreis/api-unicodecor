<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @phpdoc
 * Resource de usuário administrativo para respostas padronizadas.
 * @property int         $id
 * @property string      $nome
 * @property string|null $email
 * @property string|null $cpf
 * @property int         $id_perfil
 * @property int|null    $id_loja
 * @property int         $status
 */
class UsuarioAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'nome'      => $this->nome,
            'email'     => $this->email,
            'cpf'       => $this->cpf,
            'id_perfil' => (int) $this->id_perfil,
            'id_loja'   => $this->id_loja ? (int) $this->id_loja : null,
            'status'    => (int) $this->status,
        ];
    }
}
