<?php

namespace App\Services\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\RecuperacaoSenha;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Serviço de recuperação e redefinição de senha.
 */
class PasswordResetService
{
    /**
     * Gera token e envia e-mail com link de redefinição.
     *
     * @param string $email E-mail do usuário (válido e ativo).
     * @param string $appResetBaseUrl Base da URL do app web para redefinição (ex.: https://app.momentounicodecor.com.br/reset-password).
     * @return void
     * @throws \Throwable
     */
    public function sendResetLink(string $email, string $appResetBaseUrl): void
    {
        /** @var Usuario|null $usuario */
        $usuario = Usuario::where('email', $email)->where('status', 1)->first();
        if (!$usuario) {
            // Silenciosamente não revela existência — resposta sempre OK no controller
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expira = CarbonImmutable::now()->addHour();

        DB::transaction(function () use ($email, $token, $expira) {
            RecuperacaoSenha::where('email', $email)->where('utilizado', false)->delete();

            RecuperacaoSenha::create([
                'email'     => $email,
                'token'     => $token,
                'expira_em' => $expira,
                'utilizado' => false,
            ]);
        });

        // Link: pode ser rota web do Laravel OU deep link do app
        $url = rtrim($appResetBaseUrl, '/') . '?token=' . urlencode($token);

        Mail::to($usuario->email)->send(new ResetPasswordMail($usuario->nome, $url));
    }

    /**
     * Confere se token é válido (não expirado nem utilizado).
     */
    public function isValidToken(string $token): bool
    {
        $row = RecuperacaoSenha::where('token', $token)
            ->where('utilizado', false)
            ->where('expira_em', '>', now())
            ->first();

        return (bool) $row;
    }

    /**
     * Reseta a senha via token (mantém MD5 por compatibilidade).
     *
     * @throws \Throwable
     */
    public function resetPassword(string $token, string $novaSenha): void
    {
        $row = RecuperacaoSenha::where('token', $token)
            ->where('utilizado', false)
            ->where('expira_em', '>', now())
            ->first();

        if (!$row) {
            abort(422, 'Token inválido ou expirado.');
        }

        DB::transaction(function () use ($row, $novaSenha) {
            /** @var Usuario|null $usuario */
            $usuario = Usuario::where('email', $row->email)
                ->where('status', 1)
                ->lockForUpdate()
                ->first();

            if (!$usuario) {
                abort(422, 'Usuário não encontrado.');
            }

            // Compatibilidade com legado: MD5
            $usuario->senha = md5($novaSenha);
            $usuario->save();

            $row->utilizado = true;
            $row->save();
        });
    }
}
