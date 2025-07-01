<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\JsonResponse;

class UsuarioController extends Controller
{
    /**
     * Retorna lista de clientes (usuários com perfil_id = 6).
     *
     * @return JsonResponse
     */
    public function clientes(): JsonResponse
    {
        $clientes = Usuario::where('id_perfil', 6)
            ->where('status', 1)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return response()->json([
            'sucesso' => true,
            'dados' => $clientes
        ]);
    }
}
