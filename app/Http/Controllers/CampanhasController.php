<?php

namespace App\Http\Controllers;

use App\Http\Requests\FaixasProfissionalRequest;
use App\Http\Resources\CampanhasFaixasProfissionalResource;
use App\Models\Premio;
use App\Services\PontuacaoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

/**
 * Controlador para recursos de campanhas voltados ao PROFISSIONAL.
 */
class CampanhasController extends Controller
{
    public function __construct(
        private readonly PontuacaoService $pontuacaoService
    ) {}

    /**
     * GET /campanhas/faixas-profissional
     *
     * @param FaixasProfissionalRequest $request
     * @return JsonResponse
     */
    public function faixasProfissional(FaixasProfissionalRequest $request): JsonResponse
    {
        $user = Auth::user(); // já autorizado pelo FormRequest (perfil 2)
        $tz   = Config::get('app.timezone', 'America/Belem');
        $hoje = CarbonImmutable::parse($request->input('data_base') ?: now($tz)->toDateString(), $tz)
            ->toDateString();

        // Base: campanha/faixas do profissional
        $dadosBase = $this->pontuacaoService->obterCampanhasComPontuacao($user->id, $hoje);

        // Normaliza pontuação numérica
        $pontuacaoAtual = self::toNumber($dadosBase['pontuacao_total'] ?? 0);

        // Garante que a campanha em $dadosBase está ativa por data/status
        $campanha = $dadosBase['campanha'];
        if ($campanha) {
            $estaAtiva = (int)$campanha->status === 1
                && !empty($campanha->dt_inicio) && $campanha->dt_inicio <= $hoje
                && (empty($campanha->dt_fim) || $campanha->dt_fim >= $hoje);

            if (!$estaAtiva) {
                $dadosBase['campanha'] = null;
                $dadosBase['faixa_atual'] = null;
                $dadosBase['proxima_faixa'] = null;
            }
        }

        $incluirProxFaixas = (bool) $request->input('incluir_proximas_faixas', true);
        $incluirProxCamp   = (bool) $request->input('incluir_proximas_campanhas', true);

        // Próximas faixas da campanha vigente
        $proximasFaixas = [];
        if ($incluirProxFaixas && !empty($dadosBase['campanha'])) {
            $campanhaCarregada = Premio::query()
                ->with(['faixas' => fn($q) => $q->orderBy('pontos_min')])
                ->find($dadosBase['campanha']->id);

            if ($campanhaCarregada && $campanhaCarregada->relationLoaded('faixas')) {
                $proximasFaixas = $campanhaCarregada->faixas
                    ->whereNotNull('pontos_min')
                    ->filter(fn ($f) => (float)$f->pontos_min > $pontuacaoAtual)
                    ->sortBy('pontos_min')
                    ->values()
                    ->map(fn ($f) => [
                        'id'                     => $f->id,
                        'range'                  => $f->pontos_range_formatado,
                        'descricao'              => $f->descricao,
                        'acompanhante_label'     => $f->acompanhante_label,
                        'valor_viagem_formatado' => $f->valor_viagem_formatado,
                        'pontos_min'             => $f->pontos_min,
                        'pontos_max'             => $f->pontos_max,
                    ])->all();
            }
        }

        // Próximas campanhas “alcançáveis” (ativas hoje, sem faixas, com 'pontos' > pontuacaoAtual)
        $proximasCampanhas = [];
        if ($incluirProxCamp) {
            $proximasCampanhas = Premio::query()
                ->with('faixas')
                ->where('status', 1)
                ->whereDate('dt_inicio', '<=', $hoje)
                ->where(fn($q) => $q->whereNull('dt_fim')->orWhereDate('dt_fim', '>=', $hoje))
                ->whereDoesntHave('faixas') // tipo TOP 100
                ->where('pontos', '>', $pontuacaoAtual)
                ->orderBy('pontos')
                ->get()
                ->map(fn ($c) => [
                    'id'     => $c->id,
                    'titulo' => $c->titulo,
                    'pontos' => $c->pontos,
                    'faltam' => max(0, (int) round(((float)$c->pontos) - $pontuacaoAtual)),
                ])
                ->values()
                ->all();
        }

        $payload = [
            ...$dadosBase,
            'proximas_faixas'    => $proximasFaixas,
            'proximas_campanhas' => $proximasCampanhas,
        ];

        return response()->json([
            'sucesso'  => true,
            'mensagem' => 'Faixas e campanha do profissional',
            'dados'    => (new CampanhasFaixasProfissionalResource($payload))->toArray($request),
        ]);
    }

    /**
     * Converte "1.234" ou "1.234,56" para float.
     */
    private static function toNumber(int|float|string $valor): float
    {
        if (is_numeric($valor)) return (float) $valor;
        $san = str_replace(['.', ','], ['', '.'], (string) $valor);
        return (float) $san;
    }
}
