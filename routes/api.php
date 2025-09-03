<?php

use App\Http\Controllers\ConstrutorasController;
use App\Http\Controllers\EmpreendimentoController;
use App\Http\Controllers\MePremiosController;
use App\Http\Controllers\GaleriaController;
use App\Http\Controllers\LojaController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PlantasBaixasController;
use App\Http\Controllers\PontuacaoOpcoesController;
use App\Http\Controllers\PremioController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\ProfissionalController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\RateioController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PontuacaoController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
Route::get('/password/validate', [PasswordResetController::class, 'validateToken']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me/premios', [MePremiosController::class, 'index']);

    // Pontuações
    Route::get('/pontuacoes', [PontuacaoController::class, 'index']);
    Route::post('/pontuacoes', [PontuacaoController::class, 'store']);
    Route::get('/pontuacoes/opcoes', [PontuacaoOpcoesController::class, 'index']);
    Route::match(['put','patch'], '/pontuacoes/{ponto}', [PontuacaoController::class, 'update']);
    Route::delete('/pontuacoes/{ponto}', [PontuacaoController::class, 'destroy']);
    Route::get('/pontuacoes/{ponto}', [PontuacaoController::class, 'show']);

    Route::get('/premios', [PremioController::class, 'index']);
    Route::get('/premios/{premio}', [PremioController::class, 'show']);
    Route::post('/premios', [PremioController::class, 'store']);
    Route::patch('/premios/faixas/{faixa}/valor-viagem', [PremioController::class, 'atualizarValorViagemFaixa']);
    Route::match(['put','patch'], '/premios/{premio}', [PremioController::class, 'update']);

    Route::get('/banners', [BannerController::class, 'index'])->name('banners.index');
    Route::get('/banners/{banner}', [BannerController::class, 'show'])->name('banners.show');
    Route::post('/banners', [BannerController::class, 'store'])->name('banners.store');
    Route::put('/banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
    Route::patch('/banners/{banner}', [BannerController::class, 'update'])->name('banners.patch'); // opcional
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');
    Route::patch('/banners/{banner}/status', [BannerController::class, 'toggleStatus'])->name('banners.toggleStatus');

    Route::get('/lojas/ativas', [LojaController::class, 'ativas']);

    Route::get('/lojas', [LojaController::class, 'index']);
    Route::post('/lojas', [LojaController::class, 'store']);
    Route::get('/lojas/{loja}', [LojaController::class, 'show']);
    Route::match(['put','patch'], '/lojas/{loja}', [LojaController::class, 'update']);
    Route::delete('/lojas/{loja}', [LojaController::class, 'destroy']);

    // Extras
    Route::post('/lojas/{id}/restore', [LojaController::class, 'restore']);
    Route::patch('/lojas/{loja}/status', [LojaController::class, 'alterarStatus']);

    Route::get('/usuarios', [UsuarioController::class, 'usuarios']);
    Route::get('/usuarios/administrativos', [UsuarioController::class, 'administrativos']);
    Route::post('/usuarios/administrativos', [UsuarioController::class, 'store']);
    Route::match(['put','patch'], '/usuarios/administrativos/{usuario}', [UsuarioController::class, 'update']);
    Route::delete('/usuarios/administrativos/{usuario}', [UsuarioController::class, 'destroy']);
    Route::get('/usuarios/aniversariantes', [UsuarioController::class, 'aniversariantes']);

    Route::get('/ranking/premios', [RankingController::class, 'premiosOptions']);
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

        Route::post('/', [GaleriaController::class, 'store']);
        Route::match(['put', 'patch'], '{id}', [GaleriaController::class, 'update']);
        Route::delete('{id}', [GaleriaController::class, 'destroy']);

        Route::post('{id}/imagens', [GaleriaController::class, 'storeImagem']); // multipart
        Route::delete('{id}/imagens/{idImagem}', [GaleriaController::class, 'destroyImagem']);
        Route::patch('{id}/capa/{idImagem}', [GaleriaController::class, 'definirCapa']);
    });

    Route::get('/construtoras', [ConstrutorasController::class, 'index']);
    Route::post('/construtoras', [ConstrutorasController::class, 'store']);
    Route::get('/construtoras/{id}', [ConstrutorasController::class, 'show']);
    Route::match(['put','patch'], '/construtoras/{id}', [ConstrutorasController::class, 'update']);
    Route::delete('/construtoras/{id}', [ConstrutorasController::class, 'destroy']);
    Route::patch('/construtoras/{id}/status', [ConstrutorasController::class, 'setStatus']);

    Route::apiResource('empreendimentos', EmpreendimentoController::class);
    Route::patch('empreendimentos/{empreendimento}/status', [EmpreendimentoController::class, 'updateStatus']);

    Route::get('/profissionais',        [ProfissionalController::class, 'index']);
    Route::post('/profissionais',       [ProfissionalController::class, 'store']);
    Route::get('/profissionais/{id}',   [ProfissionalController::class, 'show']);
    Route::put('/profissionais/{id}',   [ProfissionalController::class, 'update']);
    Route::delete('/profissionais/{id}',[ProfissionalController::class, 'destroy']);

});
