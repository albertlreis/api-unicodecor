<?php

namespace App\Http\Resources;

use App\Services\GaleriaService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $idGaleriaImagens
 * @property string|null $arquivo
 */
class GaleriaImagemResource extends JsonResource
{
    /**
     * @param  mixed  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id:int,arquivo:string}
     */
    public function toArray($request): array
    {
        /** @var GaleriaService $svc */
        $svc = app(GaleriaService::class);
        $url = $this->arquivo ? $svc->urlImagem($this->arquivo) : '';

        return [
            'id'      => $this->idGaleriaImagens,
            'arquivo' => $url,
        ];
    }
}
