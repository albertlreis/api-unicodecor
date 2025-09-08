<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de recuperação e redefinição de senha.
 */
class PasswordResetController extends Controller
{
    /**
     * @param \App\Services\Auth\PasswordResetService $service
     */
    public function __construct(
        private readonly PasswordResetService $service
    ) {}

    /**
     * POST /password/forgot
     *
     * Envia e-mail com link de redefinição (resposta genérica por segurança).
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        // Base do link – pode ser uma rota web (Blade) ou um deep link do app
        $baseUrl = config('app.reset_password_url', url('/reset-password'));
        $this->service->sendResetLink($request->email, $baseUrl);

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Se o e-mail existir, enviaremos um link para redefinir a senha.',
        ]);
    }

    /**
     * GET /password/validate?token=...
     *
     * Verifica se o token é válido.
     */
    public function validateToken(Request $request): JsonResponse
    {
        $token = (string) $request->query('token', '');
        $ok = $this->service->isValidToken($token);

        return response()->json([
            'valido' => $ok,
            'mensagem' => $ok ? 'Token válido.' : 'Token inválido ou expirado.',
        ], $ok ? 200 : 422);
    }

    /**
     * POST /password/reset
     *
     * Redefine a senha usando token válido.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->service->resetPassword($request->token, $request->senha);

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Senha atualizada com sucesso.',
        ]);
    }
}
