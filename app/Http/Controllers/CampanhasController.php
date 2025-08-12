<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaixasProfissionalRequest;
use App\Http\Resources\CampanhasFaixasProfissionalResource;
use App\Domain\Campanhas\Services\CampanhaFaixaResolver;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para recursos de campanhas voltados ao PROFISSIONAL.
 */
class CampanhasController extends Controller
{
    public function __construct(
        private readonly CampanhaFaixaResolver $resolver
    ) {}

    /**
     * GET /campanhas/faixas-profissional
     *
     * @param FaixasProfissionalRequest $request
     * @return JsonResponse
     */
    public function faixasProfissional(FaixasProfissionalRequest $request): JsonResponse
    {
        $user  = $request->user(); // autorizado no FormRequest (perfil 2)
        $hoje  = $request->input('data_base'); // ISO Y-m-d ou null
        $incFx = (bool) $request->input('incluir_proximas_faixas', true);
        $incCp = (bool) $request->input('incluir_proximas_campanhas', true);

        $payload = $this->resolver->resolver(
            usuarioId: (int) $user->id,
            dataBase: $hoje,
            incluirProximasFaixas: $incFx,
            incluirProximasCampanhas: $incCp
        );

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Faixas e campanha do profissional',
            'dados'    => (new CampanhasFaixasProfissionalResource($payload))->toArray($request),
        ]);
    }
}
