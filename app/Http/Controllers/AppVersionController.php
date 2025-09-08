<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Fornece informações de versão do app.
 */
class AppVersionController extends Controller
{
    /**
     * Retorna latest/minSupported/mensagem/URL da App Store.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $platform = $request->query('platform', 'ios');

        $version = AppVersion::where('platform', $platform)->latest('id')->first();

        if (!$version) {
            return response()->json([
                'latest' => config('app.version', '1.0.0'),
                'minSupported' => '1.0.0',
                'message' => null,
                'storeUrl' => null,
            ]);
        }

        return response()->json([
            'latest'       => $version->latest,
            'minSupported' => $version->min_supported,
            'message'      => $version->message,
            'storeUrl'     => $version->store_url,
        ]);
    }
}
