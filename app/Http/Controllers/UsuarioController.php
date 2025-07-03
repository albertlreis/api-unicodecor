<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Usuario;

class UsuarioController extends Controller
{
    /**
     * Retorna lista de clientes (usuários com perfil_id = 6).
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function usuarios(Request $request): JsonResponse
    {
        // Permite buscar o perfil por query string, body JSON ou formulário
        $perfilId = $request->input('id_perfil', 6);

        $clientes = Usuario::where('id_perfil', $perfilId)
            ->where('status', 1)
            ->orderBy('nome')
            ->get(['id', 'nome', 'cpf']);

        return response()->json([
            'sucesso' => true,
            'dados' => $clientes
        ]);
    }
}
