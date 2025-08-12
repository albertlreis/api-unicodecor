<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaixasProfissionalRequest;
use App\Http\Resources\PremiosFaixasProfissionalResource;
use App\Domain\Premios\Services\PremioFaixaResolver;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de PRÊMIOS voltado ao PROFISSIONAL (perfil 2).
 */
class PremiosProfissionalController extends Controller
{
    public function __construct(
        private readonly PremioFaixaResolver $resolver
    ) {}

    /**
     * GET /premios/faixas-profissional
     *
     * Retorna faixas e informações da campanha (prêmio) atual do profissional,
     * com opção de incluir próximas faixas e próximos prêmios.
     *
     * @param FaixasProfissionalRequest $request
     * @return JsonResponse
     */
    public function faixasProfissional(FaixasProfissionalRequest $request): JsonResponse
    {
        $user  = $request->user(); // Autorizado no FormRequest (perfil 2)
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
            'mensagem' => 'Faixas e prêmios do profissional',
            'dados'    => (new PremiosFaixasProfissionalResource($payload))->toArray($request),
        ]);
    }
}
