<?php

use App\Http\Controllers\PremioController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PontuacaoController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/pontuacoes', [PontuacaoController::class, 'index']);

    Route::get('/ranking/top100', [RankingController::class, 'top100']);
    Route::get('/campanhas/banners', [BannerController::class, 'index']);
    Route::get('/premios', [PremioController::class, 'ativos']);

});

