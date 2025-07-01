<?php

namespace App\Http\Controllers;

use App\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Usuario;

class AuthController extends Controller
{
    /**
     * Login do usuário e geração de token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
            'senha' => 'required|string',
        ]);

        $usuario = Usuario::where('login', $request->login)
            ->where('status', 1)
            ->first();

        if (!$usuario || $usuario->senha !== md5($request->senha)) {
            return response()->json(['error' => 'Credenciais inválidas'], 401);
        }

        $token = $usuario->createToken('token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UsuarioResource($usuario),
        ]);
    }

    /**
     * Invalida todos os tokens do usuário autenticado.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    }

    /**
     * Retorna os dados do usuário autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UsuarioResource($request->user())
        ]);
    }
}
