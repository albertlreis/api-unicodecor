<?php

namespace App\Http\Controllers;

use App\Http\Requests\LojaStoreRequest;
use App\Http\Requests\LojaUpdateRequest;
use App\Http\Resources\LojaResource;
use App\Models\Loja;
use App\Services\LojaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @group Lojas
 *
 * Endpoints para gestão de lojas.
 */
class LojaController extends Controller
{
    public function __construct(private readonly LojaService $service)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Listagem paginada de lojas.
     *
     * GET /lojas?q=&status=&page=&per_page=
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $lista = $this->service->listarPaginado($request->only(['q', 'status', 'page', 'per_page']));

            return LojaResource::collection($lista)
                ->additional([
                    'meta' => [
                        'current_page' => $lista->currentPage(),
                        'last_page'    => $lista->lastPage(),
                        'per_page'     => $lista->perPage(),
                        'total'        => $lista->total(),
                    ],
                ])->response();
        } catch (Throwable $e) {
            report($e);
            $msg = app()->environment('production')
                ? 'Falha ao listar lojas.'
                : ('Falha ao listar lojas: '.$e->getMessage());
            return response()->json(['message' => $msg], 500);
        }
    }

    /**
     * Lista lojas ativas (sem paginação).
     *
     * GET /lojas/ativas
     *
     * @return JsonResponse
     */
    public function ativas(): JsonResponse
    {
        try {
            $itens = $this->service->listarAtivas();
            return LojaResource::collection($itens)->response();
        } catch (Throwable $e) {
            report($e);
            $msg = app()->environment('production')
                ? 'Falha ao listar lojas ativas.'
                : ('Falha ao listar lojas ativas: '.$e->getMessage());
            return response()->json(['message' => $msg], 500);
        }
    }

    /**
     * Cria uma nova loja.
     *
     * POST /lojas
     *
     * @param LojaStoreRequest $request
     * @return JsonResponse
     */
    public function store(LojaStoreRequest $request): JsonResponse
    {
        try {
            $loja = $this->service->criar($request->validated());
            return response()->json(new LojaResource($loja), 201);
        } catch (Throwable $e) {
            report($e);
            $msg = app()->environment('production')
                ? 'Falha ao criar loja.'
                : ('Falha ao criar loja: '.$e->getMessage());
            return response()->json(['message' => $msg], 422);
        }
    }

    /**
     * Exibe uma loja.
     *
     * GET /lojas/{loja}
     *
     * @param Loja $loja
     * @return JsonResponse
     */
    public function show(Loja $loja): JsonResponse
    {
        return response()->json(new LojaResource($loja));
    }


    /**
     * Atualiza uma loja.
     *
     * PUT/PATCH /lojas/{loja}
     *
     * @param LojaUpdateRequest $request
     * @param Loja $loja
     * @return JsonResponse
     */
    public function update(LojaUpdateRequest $request, Loja $loja): JsonResponse
    {
        logger()->info('Headers', [
            'content-type' => request()->headers->get('content-type'),
            'content-length' => request()->headers->get('content-length'),
            'method' => request()->method(),
        ]);
        
        try {
            $validated = $request->validated();

            // Log com contexto E string JSON (independe do formatter)
            logger()->info('Lojas.update: payload validado', [
                'payload'          => $validated,
                'method'           => $request->method(),
                'keys'             => array_keys($request->all()),
                'has_file_logomarca' => $request->hasFile('logomarca'),
            ]);
            logger()->info('Lojas.update: payload validado (json): ' . json_encode(
                    $validated,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));

            if ($request->hasFile('logomarca')) {
                $f = $request->file('logomarca');
                logger()->info('Lojas.update: logomarca recebida', [
                    'original' => $f->getClientOriginalName(),
                    'mime'     => $f->getClientMimeType(),
                    'size'     => $f->getSize(),
                ]);
            }

            $atualizada = $this->service->atualizar($loja, $validated);
            return response()->json(new LojaResource($atualizada));
        } catch (Throwable $e) {
            report($e);
            $msg = app()->environment('production')
                ? 'Falha ao atualizar loja.'
                : ('Falha ao atualizar loja: ' . $e->getMessage());
            return response()->json(['message' => $msg], 422);
        }
    }

    /**
     * Exclui uma loja (delete real).
     *
     * DELETE /lojas/{loja}
     *
     * @param Loja $loja
     * @return JsonResponse
     */
    public function destroy(Loja $loja): JsonResponse
    {
        try {
            $this->service->remover($loja);
            return response()->json(['message' => 'Loja removida com sucesso.']);
        } catch (Throwable $e) {
            report($e);
            $msg = app()->environment('production')
                ? 'Falha ao remover loja.'
                : ('Falha ao remover loja: '.$e->getMessage());
            return response()->json(['message' => $msg], 422);
        }
    }

    /**
     * Altera status (0|1).
     *
     * PATCH /lojas/{loja}/status
     * Body: { "status": 0|1 }
     *
     * @param Request $request
     * @param Loja $loja
     * @return JsonResponse
     */
    public function alterarStatus(Request $request, Loja $loja): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'integer', 'in:0,1']]);
        $loja->update(['status' => (int) $validated['status']]);
        return response()->json(new LojaResource($loja));
    }
}
