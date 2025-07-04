<?php

namespace App\Services;

use App\Models\Ponto;
use App\Models\Premio;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RankingService
{
    public function getTop100Data(int $userId): array
    {
        $row = DB::table('ranking_geral_anual')
            ->select('colocacao', 'total')
            ->where('id_profissional', $userId)
            ->first();

        $fimCampanha = Carbon::create(2025, 7, 31);
        $hoje = now();
        $diasRestantes = $fimCampanha->isFuture() ? $hoje->diffInDays($fimCampanha) : 0;

        return [
            'colocacao' => $row?->colocacao ?? null,
            'pontuacao_total' => number_format($row?->total ?? 0, 2, ',', '.'),
            'data_fim_campanha' => $fimCampanha->format('d/m/Y'),
            'dias_restantes' => $diasRestantes,
        ];
    }

    /**
     * Retorna o ranking geral com base no prêmio (obrigatório).
     *
     * @param Request $request
     * @return array
     */
    public function listar(Request $request): array
    {
        $idPremio = $request->input('id_premio');
        $premio = Premio::findOrFail($idPremio);

        $dados = DB::select('CALL sp_ranking_geral_profissionais(?)', [$idPremio]);

        return [
            'premio' => [
                'id' => $premio->id,
                'titulo' => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim' => $premio->dt_fim->format('Y-m-d'),
            ],
            'dados' => $dados,
        ];
    }

    /**
     * Retorna o ranking detalhado (profissionais que atingiram e que não atingiram).
     *
     * @param Request $request
     * @return array
     */
    public function obterRankingDetalhadoPorPremio(Request $request): array
    {
        $premioId = $request->input('id_premio');
        $premio = Premio::findOrFail($premioId);

        // 1. Profissionais que atingiram pelo menos uma faixa
        $linhasAtingiram = DB::select('CALL sp_ranking_detalhado_por_loja(?)', [$premioId]);
        $atingiram = $this->consolidarPorProfissional($linhasAtingiram);

        // 2. Profissionais que não atingiram nenhuma faixa
        $linhasNaoAtingiram = DB::select('CALL sp_ranking_detalhado_nao_atingiram(?)', [$premioId]);
        $naoAtingiram = $this->consolidarPorProfissional($linhasNaoAtingiram);

        return [
            'premio' => [
                'id' => $premio->id,
                'titulo' => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim' => $premio->dt_fim->format('Y-m-d'),
                'pontos' => $premio->pontos,
            ],
            'atingiram' => $atingiram,
            'nao_atingiram' => $naoAtingiram,
        ];
    }

    /**
     * Converte linhas de ranking em estrutura agrupada por profissional.
     *
     * @param array $linhas
     * @return array
     */
    private function consolidarPorProfissional(array $linhas): array
    {
        $consolidado = [];

        foreach ($linhas as $linha) {
            $id = $linha->id_profissional;

            if (!isset($consolidado[$id])) {
                $consolidado[$id] = [
                    'id_profissional' => $id,
                    'nome' => $linha->nome_profissional,
                    'total' => 0.0,
                    'pontos' => [],
                ];
            }

            $valor = floatval($linha->total);
            $consolidado[$id]['pontos'][] = [
                'loja' => $linha->loja,
                'total' => $valor, // ainda sem formatar, será formatado no resource
            ];

            $consolidado[$id]['total'] += $valor;
        }

        // Ordena por pontuação decrescente
        $resultado = array_values($consolidado);
        usort($resultado, fn($a, $b) => $b['total'] <=> $a['total']);

        return $resultado;
    }

}
