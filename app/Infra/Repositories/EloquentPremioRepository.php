<?php

namespace App\Infra\Repositories;

use App\Domain\Premios\Contracts\PremioRepository;
use App\Models\Premio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class EloquentPremioRepository implements PremioRepository
{
    public function listarAtivos(): Collection
    {
        $tz = Config::get('app.timezone', 'America/Belem');
        $hoje = CarbonImmutable::now($tz)->toDateString();

        return Premio::query()
            ->with(['faixas' => fn ($q) => $q->orderBy('pontos_min')])
            ->where('status', 1)
            ->whereDate('dt_inicio', '<=', $hoje)
            ->where(function ($q) use ($hoje) {
                $q->whereNull('dt_fim')->orWhereDate('dt_fim', '>=', $hoje);
            })
            ->orderByDesc('dt_inicio')
            ->get();
    }
}
