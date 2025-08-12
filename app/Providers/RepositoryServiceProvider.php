<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Premios\Contracts\PremioRepository;
use App\Infra\Repositories\EloquentPremioRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PremioRepository::class, EloquentPremioRepository::class);
    }
}
