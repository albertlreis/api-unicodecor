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
 * @property string|null $dt_inicio
 * @property string|null $dt_fim
 * @property string|null $dt_cadastro
 * @property int|null $status
 */
class PremioResource extends JsonResource
{
    /**
     * Transforma o recurso em um array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'titulo'         => $this->titulo,
            'descricao'      => $this->descricao,
            'regras'         => $this->regras,
            'regulamento'    => $this->regulamento,
            'site'           => $this->site,
            'banner'         => $this->banner,
            'pontos'         => $this->pontos,
            'pontos_formatado' => number_format($this->pontos ?? 0, 0, '', '.'),
            'valor_viagem'   => $this->valor_viagem,
            'valor_viagem_formatado' => $this->valor_viagem
                ? number_format($this->valor_viagem, 2, ',', '.')
                : null,
            'dt_inicio'      => optional($this->dt_inicio)->format('Y-m-d'),
            'dt_fim'         => optional($this->dt_fim)->format('Y-m-d'),
            'dt_inicio_formatado' => $this->dt_inicio ? $this->dt_inicio->format('d/m/Y') : null,
            'dt_fim_formatado'    => $this->dt_fim ? $this->dt_fim->format('d/m/Y') : null,
            'status'         => $this->status,

            // Inclui faixas se estiver carregado
            'faixas'         => $this->whenLoaded('faixas', function () {
                return $this->faixas->map(fn($faixa) => [
                    'id'         => $faixa->id,
                    'pontos_min' => $faixa->pontos_min,
                    'pontos_max' => $faixa->pontos_max,
                    'range'      => $faixa->pontos_range_formatado,
                    'acompanhante' => $faixa->acompanhante_label,
                    'descricao' => $faixa->descricao,
                    'valor'     => $faixa->valor_viagem_formatado,
                ]);
            }),
        ];
    }
}
