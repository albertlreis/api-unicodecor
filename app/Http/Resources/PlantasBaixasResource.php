<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property array $empreendimentos
 */
class PlantasBaixasResource extends JsonResource
{
    /** @inheritDoc */
    public function toArray(Request $request): array
    {
        return [
            'id_construtora' => $this['id_construtora'],
            'razao_social'   => $this['razao_social'],
            'imagem'         => $this['imagem'],
            'empreendimentos'=> collect($this['empreendimentos'])->map(function ($emp) {
                return [
                    'id_empreendimento' => $emp['id_empreendimento'],
                    'nome'               => $emp['nome'],
                    'imagem'             => $emp['imagem'],
                    'plantas'            => collect($emp['plantas'])->map(function ($planta) {
                        $publicUrl = $planta['arquivo']
                            ? Storage::disk('public')->url($planta['arquivo'])
                            : null;

                        return [
                            'id'        => $planta['id'],
                            'titulo'    => $planta['titulo'],
                            'descricao' => $planta['descricao'],
                            'nome'      => $planta['nome'],
                            'arquivo'   => $publicUrl,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
