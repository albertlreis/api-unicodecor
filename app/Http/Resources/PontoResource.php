<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PontoResource extends JsonResource
{
    /**
     * Transforma o recurso em um array para retorno da API.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'valor'             => $this->valor,
            'valor_formatado'   => $this->valor_formatado,
            'dt_referencia'     => $this->dt_referencia_formatado,
            'dt_cadastro'       => $this->dt_cadastro_formatado,
            'status'            => $this->status,
            'status_label'      => $this->status_label,
            'orcamento'         => $this->orcamento,

            'profissional' => [
                'id'    => $this->profissional?->id,
                'nome'  => $this->profissional?->nome,
                'email' => $this->profissional?->email,
            ],

            'lojista' => [
                'id'    => $this->lojista?->id,
                'nome'  => $this->lojista?->nome,
                'email' => $this->lojista?->email,
            ],

            'cliente' => [
                'id'    => $this->cliente?->id,
                'nome'  => $this->cliente?->nome,
            ],

            'loja' => [
                'id'    => $this->loja?->id,
                'nome'  => $this->loja?->nome,
                'razao' => $this->loja?->razao,
                'email' => $this->loja?->email,
            ],
        ];
    }
}
