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

        $ranking = Ponto::query()
            ->selectRaw('id_profissional, SUM(valor) as pontuacao')
            ->where('status', 1)
            ->whereBetween('dt_referencia', [
                $premio->dt_inicio->format('Y-m-d'),
                $premio->dt_fim->format('Y-m-d'),
            ])
            ->groupBy('id_profissional')
            ->orderByDesc('pontuacao')
            ->get()
            ->map(function ($item) {
                $usuario = Usuario::find($item->id_profissional);

                return (object) [
                    'id_profissional' => $item->id_profissional,
                    'pontuacao' => $item->pontuacao,
                    'profissional' => $usuario ? (object) [
                        'id' => $usuario->id,
                        'nome' => $usuario->nome,
                    ] : null,
                ];
            });

        return [
            'premio' => [
                'id' => $premio->id,
                'titulo' => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio->format('Y-m-d'),
                'dt_fim' => $premio->dt_fim->format('Y-m-d'),
            ],
            'dados' => $ranking,
        ];
    }

    public function obterRankingDetalhadoPorPremio(Request $request): array
    {
        $premioId = $request->input('id_premio');

        $premio = Premio::findOrFail($premioId);

        $result = DB::table('pontos as p')
            ->select(
                'u.id as id_profissional',
                'u.nome as profissional',
                'l.nome as loja',
                DB::raw('SUM(p.valor) as total')
            )
            ->join('usuario as u', 'u.id', '=', 'p.id_profissional')
            ->join('lojas as l', 'l.id', '=', 'p.id_loja')
            ->whereBetween('p.dt_referencia', [$premio->dt_inicio, $premio->dt_fim])
            ->where('p.status', 1)
            ->groupBy('u.id', 'u.nome', 'l.nome')
            ->orderByDesc('total')
            ->get();

        $consolidado = [];

        foreach ($result as $item) {
            $id = $item->id_profissional;

            if (!isset($consolidado[$id])) {
                $consolidado[$id] = [
                    'id_profissional' => $id,
                    'nome' => $item->profissional,
                    'total' => 0,
                    'pontos' => []
                ];
            }

            $consolidado[$id]['pontos'][] = [
                'loja' => $item->loja,
                'total' => (float)$item->total
            ];

            $consolidado[$id]['total'] += (float)$item->total;
        }

        // Separar atingiram e não atingiram
        $atingiram = [];
        $naoAtingiram = [];

        foreach ($consolidado as $prof) {
            if ($prof['total'] >= $premio->pontos) {
                $atingiram[] = $prof;
            } else {
                $naoAtingiram[] = $prof;
            }
        }

        usort($atingiram, fn($a, $b) => $b['total'] <=> $a['total']);
        usort($naoAtingiram, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'atingiram' => $atingiram,
            'nao_atingiram' => $naoAtingiram,
            'premio' => [
                'id' => $premio->id,
                'titulo' => $premio->titulo,
                'dt_inicio' => $premio->dt_inicio,
                'dt_fim' => $premio->dt_fim,
                'pontos' => $premio->pontos,
            ],
        ];
    }
}
