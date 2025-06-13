<?php

namespace App\Services;

use Carbon\Carbon;
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
}
