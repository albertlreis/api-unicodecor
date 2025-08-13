<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Loja */
class LojaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // IDs
            'id' => $this->id,

            // Contrato moderno (preferido pelo app novo)
            'razao_social'  => $this->razao,
            'nome_fantasia' => $this->nome,
            'cnpj'          => $this->cnpj,
            'fone'          => $this->fone,
            'endereco'      => $this->endereco,
            'site'          => $this->eletronico,
            'email'         => $this->email,
            'apresentacao'  => $this->apresentacao,
            'status'        => (int)$this->status,
            'logomarca'     => $this->logomarca,
            'logomarca_url' => $this->logomarca_url,

            // Campos legados úteis (para telas antigas ou migração gradual)
            'eletronico'    => $this->eletronico,
            'contato'       => $this->contato,
            'celular'       => $this->celular,
            'contato2'      => $this->contato2,
            'email2'        => $this->email2,
            'celular2'      => $this->celular2,
            'maps'          => $this->maps,
            'estadual'      => $this->estadual,
            'municipal'     => $this->municipal,
            'bairro'        => $this->bairro,
            'cep'           => $this->cep,
            'id_cidade'     => $this->id_cidade,
            'id_estado'     => $this->id_estado,
            'complemento'   => $this->complemento,
        ];
    }
}
