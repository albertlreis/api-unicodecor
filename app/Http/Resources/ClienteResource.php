<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int         $id
 * @property string      $nome
 * @property string|null $cpf
 */
class ClienteResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'   => $this->id,
            'nome' => $this->nome,
            'cpf'  => $this->cpf, // guarda CPF ou CNPJ (apenas dígitos)
        ];
    }
}
