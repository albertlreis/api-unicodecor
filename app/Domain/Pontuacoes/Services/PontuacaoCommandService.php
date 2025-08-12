<?php

namespace App\Domain\Pontuacoes\Services;

use App\Models\HistoricoEdicaoPonto;
use App\Models\Ponto;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de comandos de pontuação (criar/atualizar com histórico).
 */
class PontuacaoCommandService
{
    /**
     * @param array<string,mixed> $data
     */
    public function salvar(array $data, Usuario $usuario): Ponto
    {
        return DB::transaction(function () use ($data, $usuario) {
            $data['id_loja']    = $usuario->id_loja;
            $data['id_lojista'] = $usuario->id;
            $data['dt_edicao']  = now();

            if (isset($data['id'])) {
                $ponto = Ponto::findOrFail($data['id']);

                HistoricoEdicaoPonto::create([
                    'id_pontos'               => $ponto->id,
                    'id_usuario_alteracao'    => $usuario->id,
                    'valor_anterior'          => $ponto->valor,
                    'valor_novo'              => $data['valor'],
                    'dt_referencia_anterior'  => $ponto->dt_referencia,
                    'dt_referencia_novo'      => $data['dt_referencia'],
                    'dt_alteracao'            => Carbon::now(),
                ]);

                $ponto->update($data);
            } else {
                $data['dt_cadastro'] = now();
                $data['status']      = 1;
                $ponto = Ponto::create($data);
            }

            return $ponto;
        });
    }
}
