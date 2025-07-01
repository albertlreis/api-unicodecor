<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use Illuminate\Http\JsonResponse;

class LojaController extends Controller
{
    /**
     * Retorna lista de lojas ativas.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $lojas = Loja::where('status', 1)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return response()->json([
            'sucesso' => true,
            'dados' => $lojas
        ]);
    }
}
