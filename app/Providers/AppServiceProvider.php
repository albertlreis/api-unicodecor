<?php

namespace App\Providers;

use App\Domain\Pontuacoes\Contracts\PontoRepository;
use App\Infra\Repositories\EloquentPontoRepository;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PontoRepository::class, EloquentPontoRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
