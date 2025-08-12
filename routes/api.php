<?php

use App\Http\Controllers\PremiosProfissionalController;
use App\Http\Controllers\GaleriaController;
use App\Http\Controllers\LojaController;
use App\Http\Controllers\PlantasBaixasController;
use App\Http\Controllers\PremioController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\RateioController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PontuacaoController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Pontuações
    Route::get('/pontuacoes', [PontuacaoController::class, 'index']);
    Route::post('/pontuacoes', [PontuacaoController::class, 'store']);

    // Prêmios (antigo campanhas)
    Route::get('/premios/faixas-profissional', [PremiosProfissionalController::class, 'faixasProfissional'])
        ->name('premios.faixas-profissional');

    Route::get('/premios', [PremioController::class, 'index'])->name('premios.index');

    // Banners relacionados a prêmios (se ainda necessário)
    Route::get('/premios/banners', [BannerController::class, 'index'])->name('premios.banners');

    // Demais recursos
    Route::get('/lojas', [LojaController::class, 'index']);
    Route::get('/usuarios', [UsuarioController::class, 'usuarios']);
    Route::get('/ranking/top100', [RankingController::class, 'top100']);
    Route::get('/ranking/geral', [RankingController::class, 'index']);
    Route::get('/ranking/detalhado', [RankingController::class, 'detalhado']);
    Route::get('/rateio', [RateioController::class, 'index']);
    Route::get('/plantas-baixas', [PlantasBaixasController::class, 'index']);

    Route::prefix('galerias')->group(function () {
        Route::get('/', [GaleriaController::class, 'index']);
        Route::get('{id}', [GaleriaController::class, 'show']);
    });
});
