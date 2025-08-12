<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string|null $titulo
 * @property string|null $descricao
 * @property string|null $regras
 * @property string|null $regulamento
 * @property string|null $site
 * @property string|null $banner
 * @property float|null $pontos
 * @property float|null $valor_viagem
 * @property \Illuminate\Support\Carbon|string|null $dt_inicio
 * @property \Illuminate\Support\Carbon|string|null $dt_fim
 * @property int|null $status
 */
class PremioResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'titulo'                => $this->titulo,
            'descricao'             => $this->descricao,
            'regras'                => $this->regras,
            'regulamento'           => $this->regulamento,
            'site'                  => $this->site,
            'banner'                => $this->banner,
            'pontos'                => $this->pontos,
            'pontos_formatado'      => number_format($this->pontos ?? 0, 0, '', '.'),
            'valor_viagem'          => $this->valor_viagem,
            'valor_viagem_formatado'=> $this->valor_viagem !== null
                ? number_format((float) $this->valor_viagem, 2, ',', '.')
                : null,
            'dt_inicio'             => optional($this->dt_inicio)->format('Y-m-d'),
            'dt_fim'                => optional($this->dt_fim)->format('Y-m-d'),
            'dt_inicio_formatado'   => $this->dt_inicio ? $this->dt_inicio->format('d/m/Y') : null,
            'dt_fim_formatado'      => $this->dt_fim ? $this->dt_fim->format('d/m/Y') : null,
            'status'                => $this->status,

            'faixas' => $this->whenLoaded('faixas', function () {
                return $this->faixas->map(fn ($faixa) => [
                    'id'                       => $faixa->id,
                    'range'                    => $faixa->pontos_range_formatado, // "X a Y" ou "≥ X"
                    'descricao'                => $faixa->descricao,
                    'acompanhante_label'       => $faixa->acompanhante_label,
                    'valor_viagem_formatado'   => $faixa->valor_viagem_formatado,
                ]);
            }),
        ];
    }
}
