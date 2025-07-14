<?php

namespace App\Http\Controllers;

use App\Models\Galeria;
use Illuminate\Http\JsonResponse;

class GaleriaController extends Controller
{
    /**
     * Lista galerias com descrição, imagem de capa e total de imagens.
     */
    public function index(): JsonResponse
    {
        $galerias = Galeria::where('status', '!=', -1)
            ->withCount(['imagens'])
            ->with('imagemCapa')
            ->orderByDesc('dt_criacao')
            ->get()
            ->map(function ($galeria) {
                return [
                    'id' => $galeria->idGalerias,
                    'descricao' => $galeria->descricao,
                    'quantidade' => $galeria->imagens_count,
                    'capa' => $galeria->imagemCapa
                        ? "https://arearestrita.momentounicodecor.com.br/uploads/galerias/{$galeria->imagemCapa->arquivo}"
                        : null,
                ];
            });

        return response()->json($galerias);
    }

    /**
     * Mostra todas as imagens de uma galeria.
     */
    public function show(int $id): JsonResponse
    {
        $galeria = Galeria::where('status', 1)->findOrFail($id);

        $imagens = $galeria->imagens()->orderBy('dt_criacao', 'desc')->get();

        return response()->json([
            'descricao' => $galeria->descricao,
            'imagens' => $imagens->map(function ($img) {
                return [
                    'id' => $img->idGaleriaImagens,
                    'arquivo' => "https://arearestrita.momentounicodecor.com.br/uploads/galerias/{$img->arquivo}"
                ];
            }),
        ]);
    }
}
