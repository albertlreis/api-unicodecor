<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class PlantasBaixasResource
 *
 * Resource que transforma a estrutura agrupada por construtora para o front.
 *
 * @property array $empreendimentos
 */
class PlantasBaixasResource extends JsonResource
{
    /**
     * Transforma os dados da construtora com empreendimentos e plantas.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id_construtora' => $this['id_construtora'],
            'razao_social' => $this['razao_social'],
            'imagem' => $this['imagem'],
            'empreendimentos' => collect($this['empreendimentos'])->map(function ($emp) {
                return [
                    'id_empreendimento' => $emp['id_empreendimento'],
                    'nome' => $emp['nome'],
                    'imagem' => $emp['imagem'],
                    'plantas' => collect($emp['plantas'])->map(function ($planta) {
                        return [
                            'id' => $planta['id'],
                            'titulo' => $planta['titulo'],
                            'descricao' => $planta['descricao'],
                            'nome' => $planta['nome'],
                            'arquivo' => "https://arearestrita.momentounicodecor.com.br/uploads/{$planta['arquivo']}"
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
