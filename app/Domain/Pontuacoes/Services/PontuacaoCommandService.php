<?php

namespace App\Domain\Pontuacoes\Services;

use App\Models\HistoricoEdicaoPonto;
use App\Models\Ponto;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Serviço de comandos de pontuação (criar/atualizar com histórico).
 */
class PontuacaoCommandService
{
    /**
     * Cria/atualiza um registro de ponto com histórico, respeitando as regras por perfil.
     *
     * Regras:
     * - Perfil 1 (Admin): usa id_loja do payload (obrigatório).
     * - Perfil 3 (Lojista): usa id_loja do usuário autenticado, ignorando qualquer payload.id_loja.
     *
     * @param  array<string,mixed> $data   Dados validados (PontuacaoRequest)
     * @param  \App\Models\Usuario $usuario Usuário autenticado
     * @return \App\Models\Ponto
     */
    public function salvar(array $data, Usuario $usuario): Ponto
    {
        return DB::transaction(function () use ($data, $usuario) {
            // Resolve id_loja conforme perfil
            $idLoja = null;

            if ((int)$usuario->id_perfil === 1) {
                // Admin: precisa vir na requisição
                $idLoja = $data['id_loja'] ?? null;
                if (!$idLoja) {
                    throw new InvalidArgumentException('id_loja é obrigatório para administradores.');
                }
            } elseif ((int)$usuario->id_perfil === 3) {
                // Lojista: sempre do usuário
                $idLoja = $usuario->id_loja;
                if (!$idLoja) {
                    throw new InvalidArgumentException('Usuário lojista sem loja vinculada.');
                }
            } else {
                throw new InvalidArgumentException('Perfil não autorizado para operação.');
            }

            // Força id_loja e id_lojista
            $data['id_loja']    = $idLoja;
            $data['id_lojista'] = $usuario->id;
            $data['dt_edicao']  = now();

            if (isset($data['id'])) {
                // ‘UPDATE’ com histórico
                /** @var \App\Models\Ponto $ponto */
                $ponto = Ponto::findOrFail($data['id']);

                HistoricoEdicaoPonto::create([
                    'id_pontos'              => $ponto->id,
                    'id_usuario_alteracao'   => $usuario->id,
                    'valor_anterior'         => $ponto->valor,
                    'valor_novo'             => $data['valor'],
                    'dt_referencia_anterior' => $ponto->dt_referencia,
                    'dt_referencia_novo'     => $data['dt_referencia'],
                    'dt_alteracao'           => Carbon::now(),
                ]);

                $ponto->update($data);
            } else {
                // CREATE
                $data['dt_cadastro'] = now();
                $data['status']      = 1;

                /** @var \App\Models\Ponto $ponto */
                $ponto = Ponto::create($data);
            }

            return $ponto;
        });
    }
}
