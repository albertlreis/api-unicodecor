<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Normaliza a saída do Profissional para o front.
 *
 * @mixin \App\Models\Profissional
 */
class ProfissionalResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'id_perfil'    => $this->id_perfil,
            'id_loja'      => $this->id_loja,
            'nome'         => $this->nome,
            'cpf'          => $this->cpf,
            'profissao'    => $this->profissao,
            'area_atuacao' => $this->area_atuacao,
            'endereco'     => $this->endereco,
            'complemento'  => $this->complemento,
            'bairro'       => $this->bairro,
            'cep'          => $this->cep,
            'id_estado'    => $this->id_estado,
            'id_cidade'    => $this->id_cidade,
            'site'         => $this->site,
            'email'        => $this->email,
            'fone'         => $this->fone,
            'fax'          => $this->fax,
            'cel'          => $this->cel,
            'dt_nasc'      => $this->dt_nasc,
            'reg_crea'     => $this->reg_crea,
            'reg_abd'      => $this->reg_abd,
            'login'        => $this->login,
            'acesso'       => $this->acesso,
            'status'       => $this->status,
            'dtCriacao'    => $this->dtCriacao,
        ];
    }
}
