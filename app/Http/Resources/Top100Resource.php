<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Top100Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'colocacao' => $this['colocacao'],
            'pontuacaoTotal' => $this['pontuacao_total'],
            'dataFimCampanha' => $this['data_fim_campanha'],
            'diasRestantes' => $this['dias_restantes'],
        ];
    }
}
