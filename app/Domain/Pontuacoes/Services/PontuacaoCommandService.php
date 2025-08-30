<?php

namespace App\Domain\Pontuacoes\Services;

use App\Mail\PontuacaoAlteradaMail;
use App\Mail\PontuacaoExcluidaMail;
use App\Models\HistoricoEdicaoPonto;
use App\Models\Ponto;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Serviço de comandos de pontuação (criar, atualizar e excluir) com histórico e RBAC.
 */
class PontuacaoCommandService
{
    /**
     * Cria/atualiza um registro de ponto com histórico.
     *
     * Regras:
     * - Admin (perfil=1): usa id_loja do payload (obrigatório).
     * - Lojista (perfil=3): força id_loja do usuário autenticado; só pode alterar seus próprios lançamentos (id_lojista = user.id).
     *
     * @param  array<string,mixed> $data
     * @param  Usuario             $usuario
     * @return Ponto
     */
    public function salvar(array $data, Usuario $usuario): Ponto
    {
        return DB::transaction(function () use ($data, $usuario) {
            $perfil = (int) $usuario->id_perfil;

            if (!in_array($perfil, [1, 3], true)) {
                throw new InvalidArgumentException('Perfil não autorizado para operação.');
            }

            // Resolve id_loja conforme perfil
            if ($perfil === 1) {
                if (empty($data['id_loja'])) {
                    throw new InvalidArgumentException('id_loja é obrigatório para administradores.');
                }
            } else { // perfil 3
                if (!$usuario->id_loja) {
                    throw new InvalidArgumentException('Usuário lojista sem loja vinculada.');
                }
                $data['id_loja'] = $usuario->id_loja; // força loja do lojista
            }

            $data['id_lojista'] = $usuario->id;
            $data['dt_edicao']  = now();

            if (isset($data['id'])) {
                /** @var Ponto $ponto */
                $ponto = Ponto::findOrFail((int) $data['id']);

                // Lojista só pode alterar o que ele mesmo lançou
                if ($perfil === 3 && (int)$ponto->id_lojista !== (int)$usuario->id) {
                    throw new InvalidArgumentException('Lojista não pode alterar pontuação de outro usuário.');
                }

                $antes = clone $ponto; // para o e-mail

                HistoricoEdicaoPonto::create([
                    'id_pontos'              => $ponto->id,
                    'id_usuario_alteracao'   => $usuario->id,
                    'valor_anterior'         => $ponto->valor,
                    'valor_novo'             => $data['valor'] ?? $ponto->valor,
                    'dt_referencia_anterior' => $ponto->dt_referencia,
                    'dt_referencia_novo'     => $data['dt_referencia'] ?? $ponto->dt_referencia,
                    'dt_alteracao'           => Carbon::now(),
                ]);

                $ponto->update($data);

                // notifica admin
                Mail::to(config('mail.admin_address'))
                    ->queue(new PontuacaoAlteradaMail($antes, $ponto->fresh(['profissional','loja']), $usuario->nome));

                return $ponto;
            }

            // CREATE
            $data['dt_cadastro'] = now();
            $data['status']      = 1;

            /** @var Ponto $ponto */
            $ponto = Ponto::create($data);
            return $ponto;
        });
    }

    /**
     * Exclui (soft delete) uma pontuação e notifica admin.
     *
     * @param  int     $idPonto
     * @param  Usuario $usuario
     * @return void
     */
    public function excluir(int $idPonto, Usuario $usuario): void
    {
        DB::transaction(function () use ($idPonto, $usuario) {
            $perfil = (int) $usuario->id_perfil;

            if (!in_array($perfil, [1, 3], true)) {
                throw new InvalidArgumentException('Perfil não autorizado para exclusão.');
            }

            /** @var Ponto $ponto */
            $ponto = Ponto::with(['profissional','loja'])->findOrFail($idPonto);

            if ($perfil === 3 && $ponto->id_lojista !== (int)$usuario->id) {
                throw new InvalidArgumentException('Lojista não pode excluir pontuação de outro usuário.');
            }

            // soft delete via status (mantendo histórico)
            $ponto->update([
                'status'     => 0,
                'dt_edicao'  => now(),
            ]);

            Mail::to(config('mail.admin_address'))
                ->queue(new PontuacaoExcluidaMail($ponto, $usuario->nome));
        });
    }
}
