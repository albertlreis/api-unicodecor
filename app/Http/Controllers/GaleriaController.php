<?php

namespace App\Http\Controllers;

use App\Models\Galeria;
use App\Models\GaleriaImagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de Galerias (álbuns) e Imagens.
 *
 * @phpstan-type GaleriaListItem array{id:int,descricao:string,quantidade:int,capa:?string}
 * @phpstan-type GaleriaShowImage array{id:int,arquivo:string}
 * @phpstan-type GaleriaShow array{descricao:string,imagens:array<int, GaleriaShowImage>}
 */
class GaleriaController extends Controller
{
    /**
     * Lista galerias com descrição, imagem de capa e total de imagens.
     *
     * @return JsonResponse
     * @phpstan-return JsonResponse<array<int, GaleriaListItem>>
     */
    public function index(): JsonResponse
    {
        $galerias = Galeria::where('status', '!=', -1)
            ->withCount(['imagens'])
            ->with('imagemCapa')
            ->orderByDesc('dt_criacao')
            ->get()
            ->map(function (Galeria $galeria) {
                return [
                    'id' => $galeria->idGalerias,
                    'descricao' => (string) $galeria->descricao,
                    'quantidade' => (int) $galeria->imagens_count,
                    'capa' => $galeria->imagemCapa
                        ? $this->urlImagem($galeria->imagemCapa->arquivo)
                        : null,
                ];
            });

        return response()->json($galerias->values());
    }

    /**
     * Mostra todas as imagens de uma galeria.
     *
     * @param int $id
     * @return JsonResponse
     * @phpstan-return JsonResponse<GaleriaShow>
     */
    public function show(int $id): JsonResponse
    {
        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);
        $imagens = $galeria->imagens()->orderBy('dt_criacao', 'desc')->get();

        return response()->json([
            'descricao' => (string) $galeria->descricao,
            'imagens' => $imagens->map(function (GaleriaImagem $img) {
                return [
                    'id' => $img->idGaleriaImagens,
                    'arquivo' => $this->urlImagem($img->arquivo),
                ];
            })->values(),
        ]);
    }

    /**
     * Cria uma galeria.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateGaleria($request);

        $galeria = new Galeria();
        $galeria->descricao = $data['descricao'];
        $galeria->status = 1;
        $galeria->save();

        return response()->json([
            'message' => 'Galeria criada com sucesso.',
            'id' => $galeria->idGalerias,
        ], 201);
    }

    /**
     * Atualiza uma galeria (somente descrição por ora).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $galeria = Galeria::findOrFail($id);
        $data = $this->validateGaleria($request);

        $galeria->descricao = $data['descricao'];
        $galeria->save();

        return response()->json(['message' => 'Galeria atualizada com sucesso.']);
    }

    /**
     * Exclui uma galeria (soft delete lógico por status = -1).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $galeria = Galeria::findOrFail($id);
        $galeria->status = -1;
        $galeria->save();

        return response()->json(['message' => 'Galeria removida com sucesso.']);
    }

    /**
     * Envia/associa uma imagem à galeria. Recebe multipart (campo "arquivo").
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function storeImagem(Request $request, int $id): JsonResponse
    {
        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);

        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'], // até 5MB
        ]);

        $file = $request->file('arquivo');
        if (!$file) {
            throw ValidationException::withMessages(['arquivo' => 'Arquivo inválido.']);
        }

        // salva na pasta public/galerias e guarda apenas o nome do arquivo
        $path = $file->store('galerias');
        $filename = basename($path);

        $img = new GaleriaImagem();
        $img->idGalerias = $galeria->idGalerias;
        $img->arquivo = $filename; // somente o nome
        $img->status = 1;
        $img->save();

        return response()->json([
            'message' => 'Imagem enviada com sucesso.',
            'id' => $img->idGaleriaImagens,
            'arquivo' => $this->urlImagem($filename),
        ], 201);
    }

    /**
     * Remove uma imagem específica da galeria.
     *
     * @param int $id
     * @param int $idImagem
     * @return JsonResponse
     */
    public function destroyImagem(int $id, int $idImagem): JsonResponse
    {
        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);

        $img = GaleriaImagem::where('idGaleriaImagens', $idImagem)
            ->where('idGalerias', $galeria->idGalerias)
            ->firstOrFail();

        DB::transaction(function () use ($galeria, $img) {
            // Se era capa, limpa a referência
            if ((int) $galeria->idGaleriaImagens === (int) $img->idGaleriaImagens) {
                $galeria->idGaleriaImagens = null;
                $galeria->save();
            }

            $img->status = -1;
            $img->save();

            // Opcional: remover arquivo físico (se quiser manter, comente)
            if ($img->arquivo && Storage::exists("public/galerias/{$img->arquivo}")) {
                Storage::delete("public/galerias/{$img->arquivo}");
            }
        });

        return response()->json(['message' => 'Imagem removida com sucesso.']);
    }

    /**
     * Define a imagem como capa da galeria.
     *
     * @param int $id
     * @param int $idImagem
     * @return JsonResponse
     */
    public function definirCapa(int $id, int $idImagem): JsonResponse
    {
        $galeria = Galeria::where('status', '!=', -1)->findOrFail($id);

        $img = GaleriaImagem::where('idGaleriaImagens', $idImagem)
            ->where('idGalerias', $galeria->idGalerias)
            ->where('status', 1)
            ->firstOrFail();

        $galeria->idGaleriaImagens = $img->idGaleriaImagens;
        $galeria->save();

        return response()->json(['message' => 'Capa definida com sucesso.']);
    }

    /**
     * Valida payload de galeria (create/update).
     *
     * @param Request $request
     * @return array{descricao:string}
     */
    private function validateGaleria(Request $request): array
    {
        /** @var array{descricao:string} $data */
        $data = $request->validate([
            'descricao' => ['required', 'string', 'max:3000'],
        ]);

        return $data;
    }

    /**
     * Gera URL pública para a imagem (armazenada em storage/app/public/galerias).
     *
     * @param string $filename
     * @return string
     */
    private function urlImagem(string $filename): string
    {
        // Se já vier com http(s), apenas retorna (defensivo)
        if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
            return $filename;
        }
        return asset("storage/galerias/{$filename}");
    }
}
