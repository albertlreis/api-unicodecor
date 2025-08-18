<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Empreendimento
 */
class EmpreendimentoResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $statusText = match ($this->status) {
            -1 => 'Excluído',
            0  => 'Desabilitado',
            default => 'Ativo',
        };

        return [
            'id'            => $this->idEmpreendimentos,
            'idConstrutoras'=> $this->idConstrutoras,
            'nome'          => $this->nome,
            'site'          => $this->site,
            'status'        => $this->status,
            'status_text'   => $statusText,
            'imagem'        => $this->imagem,      // caminho relativo salvo no BD
            'imagem_url'    => $this->imagem_url,  // URL pública via Storage
            'construtora'   => $this->whenLoaded('construtora', function () {
                return [
                    'id'           => $this->construtora->idConstrutoras,
                    'razao_social' => $this->construtora->razao_social,
                ];
            }),
        ];
    }
}
