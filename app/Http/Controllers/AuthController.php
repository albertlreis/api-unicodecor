<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Usuario;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $usuario = Usuario::where('login', $request->login)
            ->where('status', 1)
            ->first();

        if (!$usuario || $usuario->senha !== md5($request->senha)) {
            return response()->json(['error' => 'Credenciais inválidas'], 401);
        }

        $token = $usuario->createToken('token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $usuario->id,
                'nome' => $usuario->nome,
                'email' => $usuario->email,
                'perfil' => $usuario->id_perfil
            ],
            'token' => $token
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
