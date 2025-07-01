<?php

use App\Http\Controllers\LojaController;
use App\Http\Controllers\PremioController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PontuacaoController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/pontuacoes', [PontuacaoController::class, 'index']);
    Route::get('/pontuacoes/info-home', [PontuacaoController::class, 'infoHome']);

    Route::get('/ranking/top100', [RankingController::class, 'top100']);
    Route::get('/campanhas/banners', [BannerController::class, 'index']);
    Route::get('/premios', [PremioController::class, 'ativos']);
    Route::get('/lojas', [LojaController::class, 'index']);
    Route::get('/clientes', [UsuarioController::class, 'clientes']);

});

