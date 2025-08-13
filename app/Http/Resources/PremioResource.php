<?php

namespace App\Http\Resources;

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
 * @property \Illuminate\Support\Carbon|string|null $dt_inicio
 * @property \Illuminate\Support\Carbon|string|null $dt_fim
 * @property int|null $status
 */
class PremioResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'titulo'              => $this->titulo,
            'descricao'           => $this->descricao,
            'regras'              => $this->regras,
            'regulamento'         => $this->regulamento,
            'site'                => $this->site,
            'banner'              => $this->banner,
            'pontos'              => $this->pontos,
            'dt_inicio'           => $this->dt_inicio ? $this->dt_inicio->format('Y-m-d') : null,
            'dt_fim'              => $this->dt_fim ? $this->dt_fim->format('Y-m-d') : null,
            'dt_inicio_formatado' => $this->dt_inicio ? $this->dt_inicio->format('d/m/Y') : null,
            'dt_fim_formatado'    => $this->dt_fim ? $this->dt_fim->format('d/m/Y') : null,
            'status'              => $this->status,
            'faixas'              => $this->whenLoaded('faixas', function () {
                return $this->faixas->map(function ($f) {
                    return [
                        'id'                     => $f->id,
                        'pontos_min'             => $f->pontos_min,
                        'pontos_max'             => $f->pontos_max,
                        'vl_viagem'              => $f->vl_viagem,
                        'acompanhante'           => (int) $f->acompanhante,
                        'range'                  => $f->pontos_range_formatado,
                        'acompanhante_label'     => $f->acompanhante_label,
                        'valor_viagem_formatado' => $f->valor_viagem_formatado,
                        'descricao'              => $f->descricao,
                    ];
                });
            }),
        ];
    }
}
