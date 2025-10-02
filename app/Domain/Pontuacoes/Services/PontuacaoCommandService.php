<?php

namespace App\Domain\Pontuacoes\Services;

use App\Jobs\SendPontuacaoMail;
use App\Mail\PontuacaoAlteradaMail;
use App\Mail\PontuacaoExcluidaMail;
use App\Models\HistoricoEdicaoPonto;
use App\Models\Ponto;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class PontuacaoCommandService
{
    /**
     * Envia e-mail ao admin de forma resiliente, sem bloquear a resposta HTTP.
     *
     * @param  mixed  $mailable
     * @return void
     */
    private function notifyAdminSafely(mixed $mailable): void
    {
        $to = (string) config('mail.admin_address', '');
        if ($to === '') {
            Log::info('Pontuação: admin_address vazio, pulando envio de e-mail.');
            return;
        }

        try {
            SendPontuacaoMail::dispatch($to, $mailable)->afterResponse();
        } catch (Throwable $e) {
            Log::warning('Falha ao agendar e-mail para admin.', [
                'error' => $e->getMessage(),
                'to'    => $to,
                'mail'  => is_object($mailable) ? get_class($mailable) : gettype($mailable),
            ]);
        }
    }

    /**
     * Cria/atualiza registro.
     *
     * @param array<string,mixed> $data
     * @param Usuario $usuario
     * @return \App\Models\Ponto
     */
    public function salvar(array $data, Usuario $usuario): Ponto
    {
        return DB::transaction(function () use ($data, $usuario) {
            $perfil = (int) $usuario->id_perfil;

            if (!in_array($perfil, [1, 3], true)) {
                throw new InvalidArgumentException('Perfil não autorizado para operação.');
            }

            if ($perfil === 1) {
                if (empty($data['id_loja'])) {
                    throw new InvalidArgumentException('id_loja é obrigatório para administradores.');
                }
            } else {
                if (!$usuario->id_loja) {
                    throw new InvalidArgumentException('Usuário lojista sem loja vinculada.');
                }
                $data['id_loja'] = $usuario->id_loja;
            }

            $data['id_lojista'] = $usuario->id;
            $data['dt_edicao']  = now();

            if (isset($data['id'])) {
                /** @var Ponto $ponto */
                $ponto = Ponto::findOrFail((int) $data['id']);

                if ($perfil === 3 && (int) $ponto->id_lojista !== (int) $usuario->id) {
                    throw new InvalidArgumentException('Lojista não pode alterar pontuação de outro usuário.');
                }

                $antes = clone $ponto;

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

                // notifica admin (resiliente)
                $this->notifyAdminSafely(
                    new PontuacaoAlteradaMail($antes, $ponto->fresh(['profissional','loja']), $usuario->nome)
                );

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
     * Exclui (soft delete) e notifica admin.
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

            if ($perfil === 3 && $ponto->id_lojista !== $usuario->id) {
                throw new InvalidArgumentException('Lojista não pode excluir pontuação de outro usuário.');
            }

            $ponto->update([
                'status'     => 0,
                'dt_edicao'  => now(),
            ]);

            // notifica admin (resiliente)
            $this->notifyAdminSafely(
                new PontuacaoExcluidaMail($ponto, $usuario->nome)
            );
        });
    }
}
