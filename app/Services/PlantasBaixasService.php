<?php

namespace App\Services;

use App\Models\PlantaBaixa;

/**
 * Class PlantasBaixasService
 *
 * Serviço responsável por consolidar os dados das plantas baixas agrupadas.
 */
class PlantasBaixasService
{
    /**
     * Retorna plantas baixas agrupadas por construtora e empreendimento.
     *
     * @return array
     */
    public function listarAgrupado(): array
    {
        $plantas = PlantaBaixa::with([
            'empreendimento.construtora'
        ])->where('status', 1)->get();

        $agrupado = [];

        foreach ($plantas as $planta) {
            $const = $planta->empreendimento->construtora;
            $emp = $planta->empreendimento;

            $idConst = $const->idConstrutoras;
            $idEmp = $emp->idEmpreendimentos;

            if (!isset($agrupado[$idConst])) {
                $agrupado[$idConst] = [
                    'id_construtora' => $idConst,
                    'razao_social' => $const->razao_social,
                    'imagem' => $const->imagem,
                    'empreendimentos' => [],
                ];
            }

            if (!isset($agrupado[$idConst]['empreendimentos'][$idEmp])) {
                $agrupado[$idConst]['empreendimentos'][$idEmp] = [
                    'id_empreendimento' => $idEmp,
                    'nome' => $emp->nome,
                    'imagem' => $emp->imagem,
                    'plantas' => [],
                ];
            }

            $agrupado[$idConst]['empreendimentos'][$idEmp]['plantas'][] = [
                'id' => $planta->idPlantasBaixas,
                'titulo' => $planta->titulo,
                'descricao' => $planta->descricao,
                'nome' => $planta->nome,
                'arquivo' => $planta->arquivo,
            ];
        }

        return array_values($agrupado);
    }
}
