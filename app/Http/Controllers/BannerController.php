<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BannerController extends Controller
{
    /**
     * Retorna todos os banners ativos
     */
    public function index(): JsonResponse
    {
        Log::info('Endpoint de banners acessado.');

        $banners = Banner::where('status', 1)
            ->orderByDesc('idBanners')
            ->get(['idBanners as id', 'titulo', 'imagem', 'link', 'descricao']);

        return response()->json($banners);
    }
}
