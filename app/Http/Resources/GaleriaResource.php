<?php

namespace App\Http\Resources;

use App\Services\GaleriaService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $idGalerias
 * @property string|null $descricao
 * @property int $status
 * @property mixed $imagens_count
 * @property mixed $imagemCapa
 */
class GaleriaResource extends JsonResource
{
    /**
     * @return array{id:int,descricao:string,quantidade:int,capa:?string,status:int}
     */
    public function toArray($request): array
    {
        $capaUrl = null;
        if ($this->imagemCapa?->arquivo) {
            /** @var GaleriaService $svc */
            $svc = app(GaleriaService::class);
            $capaUrl = $svc->urlImagem($this->imagemCapa->arquivo);
        }

        return [
            'id'         => $this->idGalerias,
            'descricao'  => $this->descricao ?? '',
            'quantidade' => (int) ($this->imagens_count ?? 0),
            'capa'       => $capaUrl,
            'status'     => $this->status,
        ];
    }
}
