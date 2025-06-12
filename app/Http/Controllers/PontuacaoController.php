<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PontuacaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $id = $request->user()->id;

        $pontos = DB::table('profissional_pontos')
            ->where('id_profissional', $id)
            ->first();

        return response()->json([
            'total' => $pontos->total ?? 0,
            'mensal' => $pontos->mensal ?? 0,
            'semanal' => $pontos->semanal ?? 0,
            'diario' => $pontos->diario ?? 0,
        ]);
    }
}
