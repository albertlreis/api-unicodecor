<?php

use App\Http\Controllers\MePremiosController;
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

    Route::get('/me/premios', [MePremiosController::class, 'index']);

    // Pontuações
    Route::get('/pontuacoes', [PontuacaoController::class, 'index']);
    Route::post('/pontuacoes', [PontuacaoController::class, 'store']);

    Route::get('/premios', [PremioController::class, 'index']);

    Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');
    Route::get('/banners/{banner}', [BannerController::class, 'show'])->name('banners.show');
    Route::post('/banners', [BannerController::class, 'store'])->name('banners.store');
    Route::put('/banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
    Route::patch('/banners/{banner}', [BannerController::class, 'update'])->name('banners.patch'); // opcional
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');
    Route::patch('/banners/{banner}/status', [BannerController::class, 'toggleStatus'])->name('banners.toggleStatus');

    // Demais recursos
    Route::get('/lojas', [LojaController::class, 'index']);
    Route::get('/usuarios', [UsuarioController::class, 'usuarios']);
    Route::get('/ranking/top100', [RankingController::class, 'top100']);
    Route::get('/ranking/geral', [RankingController::class, 'index']);
    Route::get('/ranking/detalhado', [RankingController::class, 'detalhado']);
    Route::get('/rateio', [RateioController::class, 'index']);

    Route::get('/plantas-baixas', [PlantasBaixasController::class, 'index']);

    Route::get('/plantas-baixas/construtoras', [PlantasBaixasController::class, 'construtoras']);
    Route::get('/plantas-baixas/construtoras/{id}/empreendimentos', [PlantasBaixasController::class, 'empreendimentosPorConstrutora']);

    Route::post('/plantas-baixas', [PlantasBaixasController::class, 'store']);
    Route::put('/plantas-baixas/{id}', [PlantasBaixasController::class, 'update']);
    Route::delete('/plantas-baixas/{id}', [PlantasBaixasController::class, 'destroy']);

    Route::prefix('galerias')->group(function () {
        Route::get('/', [GaleriaController::class, 'index']);
        Route::get('{id}', [GaleriaController::class, 'show']);
    });
});
